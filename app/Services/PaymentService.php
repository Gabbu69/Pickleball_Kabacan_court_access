<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtPaymentMethod;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private MediaStorageService $media) {}

    public function submit(
        Booking $booking,
        User $user,
        int $amountCentavos,
        ?CourtPaymentMethod $method,
        ?string $reference,
        ?UploadedFile $proof,
    ): Payment {
        $stored = $proof ? $this->media->store($proof, "payment-proofs/{$booking->id}", 'private') : null;

        try {
            $payment = DB::transaction(function () use ($booking, $user, $amountCentavos, $method, $reference, $stored) {
                $booking = Booking::query()->with(['payments', 'refunds'])->lockForUpdate()->findOrFail($booking->id);

                abort_unless($booking->user_id === $user->id, 403);

                if ($amountCentavos <= 0) {
                    throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
                }

                if ($booking->payments()->where('status', PaymentStatus::Submitted->value)->exists()) {
                    throw ValidationException::withMessages(['amount' => 'A payment is already awaiting review.']);
                }

                if ($amountCentavos > $booking->outstanding_centavos) {
                    throw ValidationException::withMessages(['amount' => 'The amount exceeds the outstanding reservation balance.']);
                }

                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'user_id' => $user->id,
                    'court_payment_method_id' => $method?->id,
                    'method_label' => $method?->label ?? 'Payment submission',
                    'amount_centavos' => $amountCentavos,
                    'reference' => $reference,
                    'proof_path' => $stored['path'] ?? null,
                    'proof_disk' => $stored['disk'] ?? 'local',
                    'proof_url' => $stored['url'] ?? null,
                    'proof_mime' => $stored['mime'] ?? null,
                    'proof_bytes' => $stored['bytes'] ?? null,
                    'status' => PaymentStatus::Submitted,
                    'submitted_at' => now(),
                ]);

                $this->recalculateBooking($booking);

                return $payment;
            });
        } catch (\Throwable $exception) {
            if ($stored) {
                $this->media->delete($stored['path'], $stored['disk'], $stored['url']);
            }

            throw $exception;
        }

        AuditService::record('payment.submitted', $payment, ['booking' => $booking->reference]);

        return $payment;
    }

    public function verify(Payment $payment, User $reviewer, ?string $notes = null): Payment
    {
        $payment = DB::transaction(function () use ($payment, $reviewer, $notes) {
            $payment = Payment::query()->with('booking')->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Submitted) {
                throw ValidationException::withMessages(['payment' => 'Only submitted payments can be verified.']);
            }

            $remaining = $payment->booking->outstanding_centavos;
            if ($payment->amount_centavos > $remaining) {
                throw ValidationException::withMessages(['payment' => 'This payment exceeds the current outstanding balance.']);
            }

            $payment->update([
                'status' => PaymentStatus::Verified,
                'verified_by' => $reviewer->id,
                'verified_at' => now(),
                'reviewer_notes' => $notes,
            ]);
            $this->recalculateBooking($payment->booking);
            AuditService::record('payment.verified', $payment);

            return $payment->fresh(['booking.user']);
        });

        $this->safeNotify($payment, 'Payment verified', "Payment for {$payment->booking->reference} has been verified.");

        return $payment;
    }

    public function reject(Payment $payment, User $reviewer, string $notes): Payment
    {
        $payment = DB::transaction(function () use ($payment, $reviewer, $notes) {
            $payment = Payment::query()->with('booking')->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Submitted) {
                throw ValidationException::withMessages(['payment' => 'Only submitted payments can be rejected.']);
            }

            $payment->update([
                'status' => PaymentStatus::Rejected,
                'verified_by' => $reviewer->id,
                'verified_at' => now(),
                'reviewer_notes' => $notes,
            ]);
            $this->recalculateBooking($payment->booking);
            AuditService::record('payment.rejected', $payment, ['notes' => $notes]);

            return $payment->fresh(['booking.user']);
        });

        $this->safeNotify($payment, 'Payment needs attention', "Payment for {$payment->booking->reference} was not accepted: {$notes}");

        return $payment;
    }

    public function refund(Payment $payment, User $admin, int $amountCentavos, string $reason, ?string $reference): PaymentRefund
    {
        return DB::transaction(function () use ($payment, $admin, $amountCentavos, $reason, $reference) {
            $payment = Payment::query()->with(['booking', 'refunds'])->lockForUpdate()->findOrFail($payment->id);

            if ($amountCentavos <= 0
                || $payment->status !== PaymentStatus::Verified
                || $amountCentavos > $payment->refundable_centavos) {
                throw ValidationException::withMessages(['amount' => 'Refund amount exceeds the verified refundable balance.']);
            }

            $refund = PaymentRefund::create([
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'amount_centavos' => $amountCentavos,
                'reference' => $reference,
                'reason' => $reason,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
            $this->recalculateBooking($payment->booking);
            AuditService::record('payment.refunded', $refund, ['payment_id' => $payment->id]);

            return $refund;
        });
    }

    public function recalculateBooking(Booking $booking): void
    {
        $booking->refresh();
        $verified = (int) $booking->payments()->where('status', PaymentStatus::Verified->value)->sum('amount_centavos');
        $refunded = (int) $booking->refunds()->sum('amount_centavos');
        $net = max(0, $verified - $refunded);

        $status = match (true) {
            $net >= $booking->price_centavos => PaymentStatus::Verified,
            $booking->payments()->where('status', PaymentStatus::Submitted->value)->exists() => PaymentStatus::Submitted,
            $verified > 0 && $net === 0 && $refunded > 0 => PaymentStatus::Refunded,
            $net > 0 => PaymentStatus::PartiallyPaid,
            $booking->payments()->where('status', PaymentStatus::Rejected->value)->exists() => PaymentStatus::Rejected,
            default => PaymentStatus::Unpaid,
        };

        $booking->update(['payment_status' => $status]);
    }

    private function safeNotify(Payment $payment, string $title, string $message): void
    {
        try {
            $payment->booking->user->notify(new PlatformNotification(
                $title,
                $message,
                '/bookings/'.$payment->booking->reference,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
