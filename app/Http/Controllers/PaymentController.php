<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Booking;
use App\Models\CourtPaymentMethod;
use App\Models\Payment;
use App\Notifications\PlatformNotification;
use App\Services\MediaStorageService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Booking $booking, PaymentService $payments)
    {
        $this->authorize('pay', $booking);
        abort_if(in_array($booking->status->value, ['cancelled', 'rejected', 'completed', 'expired', 'no_show'], true), 422, 'This reservation no longer accepts payments.');

        $data = $request->validated();

        $method = isset($data['court_payment_method_id'])
            ? CourtPaymentMethod::where('court_id', $booking->court_id)->where('is_active', true)->findOrFail($data['court_payment_method_id'])
            : null;

        $payment = $payments->submit(
            $booking,
            $request->user(),
            (int) round(((float) $data['amount']) * 100),
            $method,
            $data['reference'] ?? null,
            $request->file('proof'),
        );

        try {
            Notification::send($booking->court->managers, new PlatformNotification(
                'Payment submitted',
                "{$booking->reference} has a payment awaiting verification.",
                '/owner/bookings',
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Payment details submitted for verification.');
    }

    public function download(Request $request, Payment $payment, MediaStorageService $media)
    {
        $this->authorize('view', $payment);
        abort_unless($payment->proof_path, 404);

        if ($payment->proof_disk === 'vercel_blob_private') {
            $remote = $media->privateDownload($payment->proof_path, $payment->proof_disk, $payment->proof_url);

            return response($remote->body(), 200, [
                'Content-Type' => $payment->proof_mime ?: $remote->header('Content-Type', 'application/octet-stream'),
                'Content-Disposition' => 'inline; filename="payment-proof-'.$payment->id.'"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        abort_unless(Storage::disk($payment->proof_disk ?: 'local')->exists($payment->proof_path), 404);

        return Storage::disk($payment->proof_disk ?: 'local')->download($payment->proof_path);
    }
}
