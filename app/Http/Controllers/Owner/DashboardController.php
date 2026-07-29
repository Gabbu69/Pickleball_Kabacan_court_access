<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $courtIds = $request->user()->isAdmin()
            ? Court::pluck('id')
            : $request->user()->courts()->pluck('courts.id');

        $grossRevenue = (int) Payment::whereHas('booking', fn ($query) => $query->whereIn('court_id', $courtIds))
            ->where('status', 'verified')
            ->sum('amount_centavos');
        $refunds = (int) PaymentRefund::whereHas('booking', fn ($query) => $query->whereIn('court_id', $courtIds))
            ->sum('amount_centavos');

        return view('owner.dashboard', [
            'courts' => Court::whereIn('id', $courtIds)->withCount('bookings')->orderBy('name')->get(),
            'pendingBookings' => Booking::whereIn('court_id', $courtIds)->where('status', BookingStatus::Pending->value)->count(),
            'todayBookings' => Booking::whereIn('court_id', $courtIds)->whereDate('starts_at', today())->count(),
            'grossRevenue' => $grossRevenue,
            'refunds' => $refunds,
            'netRevenue' => max(0, $grossRevenue - $refunds),
            'latestBookings' => Booking::whereIn('court_id', $courtIds)
                ->with(['court', 'courtUnit', 'user'])
                ->latest()
                ->take(8)
                ->get(),
        ]);
    }
}
