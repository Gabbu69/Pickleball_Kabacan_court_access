<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use Illuminate\Http\Request;

class PlayerBookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()
            ->with(['court.primaryPhoto', 'courtUnit', 'payments'])
            ->latest('starts_at')
            ->paginate(12);

        return view('bookings.index', [
            'bookings' => $bookings,
            'favorites' => $request->user()->favoriteCourts()->with('primaryPhoto')->take(6)->get(),
            'waitlist' => WaitlistEntry::where('user_id', $request->user()->id)->whereIn('status', ['waiting', 'notified'])->with('court')->latest()->get(),
            'notifications' => $request->user()->notifications()->latest()->take(10)->get(),
            'ownerApplication' => $request->user()->ownerApplications()->latest()->first(),
        ]);
    }

    public function show(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $booking->load(['court.paymentMethods', 'courtUnit', 'payments', 'review']);

        return view('bookings.show', compact('booking'));
    }

    public function store(Request $request, Court $court, BookingService $bookings)
    {
        abort_unless($court->isPubliclyDiscoverable(), 404);

        $data = $request->validate([
            'court_unit_id' => ['required', 'integer', 'exists:court_units,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:+60 days'],
            'start_time' => ['required', 'date_format:H:i'],
            'player_notes' => ['nullable', 'string', 'max:1000'],
        ]);

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
