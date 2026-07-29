<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\MaintenanceService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request, MaintenanceService $maintenance)
    {
        $maintenance->run();
        $courtIds = $request->user()->isAdmin()
            ? Court::pluck('id')
            : $request->user()->courts()->pluck('courts.id');

        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_column(BookingStatus::cases(), 'value'))],
            'court' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ]);

        $query = Booking::whereIn('court_id', $courtIds)->with(['court', 'courtUnit', 'user', 'payments.refunds', 'attendance']);
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

    public function verifyPayment(Request $request, Payment $payment, PaymentService $payments)
    {
        $this->authorizeBooking($request, $payment->booking);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $payments->verify($payment, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Payment verified.');
    }

    public function rejectPayment(Request $request, Payment $payment, PaymentService $payments)
    {
        $this->authorizeBooking($request, $payment->booking);
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        $payments->reject($payment, $request->user(), $data['notes']);

        return back()->with('success', 'Payment marked as rejected.');
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        abort_unless($booking->court->isManagedBy($request->user()), 403);
    }
}
