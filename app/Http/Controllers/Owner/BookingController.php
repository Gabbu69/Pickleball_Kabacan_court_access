<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Notifications\PlatformNotification;
use App\Services\AuditService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $courtIds = $request->user()->isAdmin()
            ? Court::pluck('id')
            : $request->user()->courts()->pluck('courts.id');

        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_column(BookingStatus::cases(), 'value'))],
            'court' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ]);

        $query = Booking::whereIn('court_id', $courtIds)->with(['court', 'courtUnit', 'user', 'payments']);
        $query->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $query->when($data['court'] ?? null, fn ($q, $court) => $q->where('court_id', $court));
        $query->when($data['date'] ?? null, fn ($q, $date) => $q->whereDate('starts_at', $date));

        return view('owner.bookings.index', [
            'bookings' => $query->latest('starts_at')->paginate(20)->withQueryString(),
            'courts' => Court::whereIn('id', $courtIds)->orderBy('name')->get(),
            'filters' => $data,
        ]);
    }

    public function update(Request $request, Booking $booking, BookingService $bookings)
    {
        $this->authorizeBooking($request, $booking);
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'rejected', 'cancelled', 'completed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $bookings->transition($booking, $request->user(), BookingStatus::from($data['status']), $data['notes'] ?? null);

        return back()->with('success', 'Reservation status updated.');
    }

    public function verifyPayment(Request $request, Payment $payment)
    {
        $this->authorizeBooking($request, $payment->booking);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $payment->update([
            'status' => PaymentStatus::Verified,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'reviewer_notes' => $data['notes'] ?? null,
        ]);
        $payment->booking->update(['payment_status' => PaymentStatus::Verified]);
        $payment->booking->user->notify(new PlatformNotification(
            'Payment verified',
            "Payment for {$payment->booking->reference} has been verified.",
            '/bookings/'.$payment->booking->reference,
        ));
        AuditService::record('payment.verified', $payment);

        return back()->with('success', 'Payment verified.');
    }

    public function rejectPayment(Request $request, Payment $payment)
    {
        $this->authorizeBooking($request, $payment->booking);
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        $payment->update([
            'status' => PaymentStatus::Rejected,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'reviewer_notes' => $data['notes'],
        ]);
        $payment->booking->update(['payment_status' => PaymentStatus::Rejected]);
        $payment->booking->user->notify(new PlatformNotification(
            'Payment needs attention',
            "Payment for {$payment->booking->reference} was not accepted: {$data['notes']}",
            '/bookings/'.$payment->booking->reference,
        ));
        AuditService::record('payment.rejected', $payment, ['notes' => $data['notes']]);

        return back()->with('success', 'Payment marked as rejected.');
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        abort_unless($booking->court->isManagedBy($request->user()), 403);
    }
}
