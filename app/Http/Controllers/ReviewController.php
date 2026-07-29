<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        abort_unless(
            $booking->status === BookingStatus::Completed
            && $booking->attendance()->where('status', 'checked_in')->exists()
            && ! $booking->review()->exists(),
            422,
        );

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $review = Review::create($data + [
            'booking_id' => $booking->id,
            'user_id' => $request->user()->id,
            'court_id' => $booking->court_id,
            'status' => 'pending',
        ]);

        AuditService::record('review.created', $review);

        return back()->with('success', 'Thanks for reviewing this court. Your verified review is awaiting moderation.');
    }
}
