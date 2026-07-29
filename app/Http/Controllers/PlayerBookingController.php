<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;

class PlayerBookingController extends Controller
{
    public function index(Request $request, MaintenanceService $maintenance)
    {
        $maintenance->run();
        $bookings = $request->user()->bookings()
            ->with(['court.primaryPhoto', 'courtUnit', 'payments', 'attendance'])
            ->latest('starts_at')
            ->paginate(12);

        return view('bookings.index', [
            'bookings' => $bookings,
            'favorites' => $request->user()->favoriteCourts()->with('primaryPhoto')->take(6)->get(),
            'waitlist' => WaitlistEntry::where('user_id', $request->user()->id)
                ->whereIn('status', ['waiting', 'offered'])
                ->with(['court', 'latestOffer'])
                ->latest()
                ->get(),
            'notifications' => $request->user()->notifications()->latest()->take(10)->get(),
            'ownerApplication' => $request->user()->ownerApplications()->latest()->first(),
        ]);
    }

    public function show(Request $request, Booking $booking, MaintenanceService $maintenance)
    {
        $maintenance->run();
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $booking->refresh()->load(['court.paymentMethods', 'courtUnit', 'payments.refunds', 'refunds', 'review', 'attendance']);

        return view('bookings.show', compact('booking'));
    }

    public function store(StoreBookingRequest $request, Court $court, BookingService $bookings)
    {
        abort_unless($court->isPubliclyDiscoverable(), 404);

        $data = $request->validated();

        abort_unless($court->activeUnits()->whereKey($data['court_unit_id'])->exists(), 422);

        $booking = $bookings->create(
            $request->user(),
            $court,
            (int) $data['court_unit_id'],
            $data['date'],
            $data['start_time'],
            $data['player_notes'] ?? null,
        );

        return redirect()->route('bookings.show', $booking)->with('success', 'Reservation request created.');
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookings)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $bookings->cancelByPlayer($booking, $request->user(), $data['reason']);

        return back()->with('success', 'Reservation cancelled and the slot has been released.');
    }
}
