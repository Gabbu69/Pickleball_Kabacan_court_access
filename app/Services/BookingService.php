<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingSlotClaim;
use App\Models\Court;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private AvailabilityService $availability) {}

    public function create(User $user, Court $court, int $unitId, string $date, string $startTime, ?string $notes = null): Booking
    {
        $slot = $this->availability->findAvailableSlot($court, $unitId, $date, $startTime);

        if (! $slot) {
            throw ValidationException::withMessages(['slot' => 'That time slot is no longer available. Please choose another one.']);
        }

        try {
            $booking = DB::transaction(function () use ($user, $court, $slot, $notes) {
                $this->releaseExpiredClaim((int) $slot['unit_id'], $slot['start_at']);

                $booking = Booking::create([
                    'reference' => $this->reference(),
                    'user_id' => $user->id,
                    'court_id' => $court->id,
                    'court_unit_id' => $slot['unit_id'],
                    'court_schedule_rule_id' => $slot['rule_id'],
                    'starts_at' => $slot['start_at'],
                    'ends_at' => $slot['end_at'],
                    'status' => BookingStatus::Pending,
                    'payment_status' => PaymentStatus::Unpaid,
                    'expires_at' => now()->addHours(12),
                    'price_centavos' => $slot['price_centavos'],
                    'currency' => 'PHP',
                    'player_notes' => $notes,
                ]);

                BookingSlotClaim::create([
                    'booking_id' => $booking->id,
                    'court_unit_id' => $slot['unit_id'],
                    'slot_starts_at' => $slot['start_at'],
                    'slot_ends_at' => $slot['end_at'],
                ]);

                return $booking;
            }, 3);
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'slot' => 'Another player just reserved that slot. Please choose another one.',
            ]);
        }

        $booking->load(['court.managers', 'courtUnit', 'user']);
        $recipients = $booking->court->managers
            ->merge(User::query()->where('role', 'admin')->where('status', 'active')->get())
            ->unique('id');

        $this->safeNotify($recipients, new PlatformNotification(
            'New reservation '.$booking->reference,
            "{$user->name} requested {$booking->courtUnit->name} on {$booking->starts_at->format('M j, Y g:i A')}.",
            '/owner/bookings',
        ));

        $this->safeNotify(collect([$user]), new PlatformNotification(
            'Reservation received',
            "Your request {$booking->reference} is held for 12 hours while awaiting payment or court-owner approval.",
            '/bookings/'.$booking->reference,
        ));

        AuditService::record('booking.created', $booking, ['reference' => $booking->reference]);

        return $booking;
    }

    public function cancelByPlayer(Booking $booking, User $user, string $reason): Booking
    {
        if (! $booking->canBeCancelledBy($user)) {
            throw ValidationException::withMessages(['cancellation' => 'This reservation is outside the court cancellation window.']);
        }

        $booking = DB::transaction(function () use ($booking, $user, $reason) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);
            $booking->slotClaims()->delete();

            return $booking;
        });

        app(WaitlistService::class)->offerNextForBooking($booking);
        $this->notifyManagers($booking, 'Reservation cancelled', "{$booking->reference} was cancelled by {$user->name}.");
        AuditService::record('booking.cancelled_by_player', $booking, ['reason' => $reason]);

        return $booking;
    }

    public function transition(Booking $booking, User $actor, BookingStatus $status, ?string $notes = null): Booking
    {
        $booking = DB::transaction(function () use ($booking, $actor, $status, $notes) {
            $booking = Booking::query()->with(['court', 'attendance'])->lockForUpdate()->findOrFail($booking->id);
            $allowed = match ($status) {
                BookingStatus::Confirmed, BookingStatus::Rejected => $booking->status === BookingStatus::Pending,
                BookingStatus::Cancelled => in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true),
                BookingStatus::Completed => $booking->status === BookingStatus::Confirmed
                    && $booking->ends_at->isPast()
                    && $booking->attendance?->status === 'checked_in',
                default => false,
            };

            if (! $allowed) {
                throw ValidationException::withMessages(['status' => 'That reservation status change is not allowed.']);
            }

            if ($status === BookingStatus::Confirmed
                && $booking->court->payment_policy === 'proof_required'
                && ! in_array($booking->payment_status, [PaymentStatus::Verified], true)) {
                throw ValidationException::withMessages(['status' => 'Verify the required payment before confirming this reservation.']);
            }

            $data = ['status' => $status, 'owner_notes' => $notes];

            if ($status === BookingStatus::Confirmed) {
                $data += ['approved_by' => $actor->id, 'approved_at' => now(), 'expires_at' => null];
            }

            if ($status === BookingStatus::Completed) {
                $data['completed_at'] = now();
            }

            if ($status === BookingStatus::Cancelled) {
                $data += ['cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $notes];
            }

            $booking->update($data);

            if (in_array($status, [BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
                $booking->slotClaims()->delete();
            }

            return $booking;
        });

        if (in_array($status, [BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
            app(WaitlistService::class)->offerNextForBooking($booking);
        }

        $this->safeNotify(collect([$booking->user]), new PlatformNotification(
            'Reservation '.str_replace('_', ' ', $status->value),
            "{$booking->reference} for {$booking->court->name} is now ".str_replace('_', ' ', $status->value).'.',
            '/bookings/'.$booking->reference,
        ));

        AuditService::record('booking.'.$status->value, $booking, ['notes' => $notes]);

        return $booking;
    }

    public function expire(Booking $booking): bool
    {
        $expired = DB::transaction(function () use ($booking) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->status !== BookingStatus::Pending || ! $booking->expires_at?->isPast()) {
                return false;
            }

            $booking->update(['status' => BookingStatus::Expired]);
            $booking->slotClaims()->delete();
            AuditService::record('booking.expired', $booking);

            return true;
        });

        if ($expired) {
            app(WaitlistService::class)->offerNextForBooking($booking->fresh());
        }

        return $expired;
    }

    private function releaseExpiredClaim(int $unitId, string $startsAt): void
    {
        $claim = BookingSlotClaim::query()
            ->where('court_unit_id', $unitId)
            ->where('slot_starts_at', $startsAt)
            ->with('booking')
            ->lockForUpdate()
            ->first();

        if (! $claim) {
            return;
        }

        if ($claim->booking->status === BookingStatus::Pending && $claim->booking->expires_at?->isPast()) {
            $claim->booking->update(['status' => BookingStatus::Expired]);
            $claim->delete();

            return;
        }

        throw ValidationException::withMessages(['slot' => 'That slot has already been reserved.']);
    }

    private function notifyManagers(Booking $booking, string $title, string $message): void
    {
        $this->safeNotify($booking->court->managers, new PlatformNotification($title, $message, '/owner/bookings'));
    }

    private function safeNotify($recipients, PlatformNotification $notification): void
    {
        try {
            Notification::send($recipients, $notification);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'KPP-'.Str::upper(Str::random(8));
        } while (Booking::where('reference', $reference)->exists());

        return $reference;
    }
}
