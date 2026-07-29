<?php

namespace App\Http\Controllers;

use App\Models\WaitlistOffer;
use App\Services\BookingService;
use App\Services\WaitlistService;
use Illuminate\Http\Request;

class WaitlistOfferController extends Controller
{
    public function __invoke(
        Request $request,
        WaitlistOffer $offer,
        WaitlistService $waitlists,
        BookingService $bookings,
    ) {
        $booking = $waitlists->accept($offer, $request->user(), $bookings);

        return redirect()->route('bookings.show', $booking)->with('success', 'Waitlist offer claimed and reservation created.');
    }
}
