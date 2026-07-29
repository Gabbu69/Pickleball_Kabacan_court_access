<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\WaitlistEntry;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function store(Request $request, Court $court, AvailabilityService $availability)
    {
        abort_unless($court->isPubliclyDiscoverable(), 404);
        $data = $request->validate([
            'court_unit_id' => ['required', 'integer', 'exists:court_units,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:+60 days'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        $slot = $availability->findSlot($court, (int) $data['court_unit_id'], $data['date'], $data['start_time']);
        abort_unless($slot && $slot['status'] === 'booked', 422, 'Only a future reserved slot can be waitlisted.');

        WaitlistEntry::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'court_unit_id' => $slot['unit_id'],
                'starts_at' => $slot['start_at'],
            ],
            [
                'court_id' => $court->id,
                'court_schedule_rule_id' => $slot['rule_id'],
                'ends_at' => $slot['end_at'],
                'status' => 'waiting',
                'notified_at' => null,
            ],
        );

        return back()->with('success', 'You joined the waitlist for that slot.');
    }

    public function destroy(Request $request, WaitlistEntry $waitlist)
    {
        abort_unless($waitlist->user_id === $request->user()->id, 403);
        $waitlist->update(['status' => 'cancelled']);

        return back()->with('success', 'Waitlist request cancelled.');
    }
}
