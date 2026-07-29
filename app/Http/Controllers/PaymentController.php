<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtPaymentMethod;
use App\Models\Payment;
use App\Notifications\PlatformNotification;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        abort_if(in_array($booking->status->value, ['cancelled', 'rejected', 'completed'], true), 422, 'This reservation no longer accepts payments.');

        $data = $request->validate([
            'court_payment_method_id' => ['nullable', 'integer', 'exists:court_payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reference' => ['nullable', 'string', 'max:120', 'required_without:proof'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120', 'required_without:reference'],
        ]);

        $method = isset($data['court_payment_method_id'])
            ? CourtPaymentMethod::where('court_id', $booking->court_id)->where('is_active', true)->findOrFail($data['court_payment_method_id'])
            : null;

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'court_payment_method_id' => $method?->id,
            'method_label' => $method?->label ?? 'Payment submission',
            'amount_centavos' => (int) round(((float) $data['amount']) * 100),
            'reference' => $data['reference'] ?? null,
            'proof_path' => $request->file('proof')?->store("payment-proofs/{$booking->id}", 'local'),
            'status' => PaymentStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $booking->update(['payment_status' => PaymentStatus::Submitted]);
        Notification::send($booking->court->managers, new PlatformNotification(
            'Payment submitted',
            "{$booking->reference} has a payment awaiting verification.",
            '/owner/bookings',
        ));
        AuditService::record('payment.submitted', $payment, ['booking' => $booking->reference]);

        return back()->with('success', 'Payment details submitted for verification.');
    }

    public function download(Request $request, Payment $payment): StreamedResponse
    {
        $booking = $payment->booking()->with('court.managers')->firstOrFail();
        $allowed = $booking->user_id === $request->user()->id
            || $request->user()->isAdmin()
            || $booking->court->isManagedBy($request->user());

        abort_unless($allowed && $payment->proof_path, 403);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path);
    }
}
