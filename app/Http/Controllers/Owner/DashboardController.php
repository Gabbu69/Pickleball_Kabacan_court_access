<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $courtIds = $request->user()->isAdmin()
            ? Court::pluck('id')
            : $request->user()->courts()->pluck('courts.id');

        return view('owner.dashboard', [
            'courts' => Court::whereIn('id', $courtIds)->withCount('bookings')->orderBy('name')->get(),
            'pendingBookings' => Booking::whereIn('court_id', $courtIds)->where('status', BookingStatus::Pending->value)->count(),
            'todayBookings' => Booking::whereIn('court_id', $courtIds)->whereDate('starts_at', today())->count(),
            'verifiedRevenue' => Payment::whereHas('booking', fn ($query) => $query->whereIn('court_id', $courtIds))
                ->where('status', 'verified')
                ->sum('amount_centavos'),
            'latestBookings' => Booking::whereIn('court_id', $courtIds)
                ->with(['court', 'courtUnit', 'user'])
                ->latest()
                ->take(8)
                ->get(),
        ]);
    }
}
