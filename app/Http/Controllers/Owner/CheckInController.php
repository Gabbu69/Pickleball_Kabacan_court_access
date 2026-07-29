<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function index()
    {
        return view('owner.check-ins.index');
    }

    public function store(Request $request, AttendanceService $attendance)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
        ]);

        $record = $attendance->checkIn(
            $data['token'],
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'message' => 'Check-in confirmed.',
            'booking' => [
                'reference' => $record->booking->reference,
                'player' => $record->booking->user->name,
                'court' => $record->booking->court->name,
                'unit' => $record->booking->courtUnit->name,
                'time' => $record->booking->starts_at->format('M j, Y g:i A'),
                'payment_status' => str_replace('_', ' ', $record->booking->payment_status->value),
            ],
        ]);
    }

    public function update(Request $request, Booking $booking, AttendanceService $attendance)
    {
        abort_unless($booking->court->isManagedBy($request->user()), 403);
        $data = $request->validate(['token' => ['required', 'string', 'max:200']]);
        $record = $attendance->checkIn($data['token'], $request->user(), $request->ip(), $request->userAgent());
        abort_unless($record->booking_id === $booking->id, 422);

        return back()->with('success', 'Player attendance recorded.');
    }
}
