<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Court;
use App\Models\OwnerApplication;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'counts' => [
                'Published courts' => Court::published()->count(),
                'Courts awaiting review' => Court::where('status', 'pending_verification')->count(),
                'Pending owner applications' => OwnerApplication::where('status', 'pending')->count(),
                'Pending reservations' => Booking::where('status', 'pending')->count(),
                'Payments to verify' => Payment::where('status', 'submitted')->count(),
                'Registered users' => User::count(),
            ],
            'verifiedRevenue' => Payment::where('status', 'verified')->sum('amount_centavos'),
            'completedBookings' => Booking::where('status', 'completed')->count(),
            'publishedReviews' => Review::where('status', 'published')->count(),
            'auditLogs' => AuditLog::with('actor')->latest()->take(12)->get(),
        ]);
    }
}
