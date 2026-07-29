<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class BookingPassController extends Controller
{
    public function __invoke(Request $request, Booking $booking, AttendanceService $attendance)
    {
        $this->authorize('view', $booking);
        $booking->load(['court', 'courtUnit', 'attendance']);

        return view('bookings.pass', [
            'booking' => $booking,
            'qrPayload' => $booking->attendance?->status === 'checked_in'
                ? null
                : $attendance->issuePass($booking),
        ]);
    }
}
