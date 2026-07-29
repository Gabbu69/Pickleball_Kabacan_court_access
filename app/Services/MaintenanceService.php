<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;

class MaintenanceService
{
    public function run(): array
    {
        $expiredBookings = 0;
        Booking::query()
            ->where('status', BookingStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->eachById(function (Booking $booking) use (&$expiredBookings) {
                $expiredBookings += app(BookingService::class)->expire($booking) ? 1 : 0;
            });

        $closedBookings = 0;
        Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->where('ends_at', '<=', now()->subMinutes(30))
            ->with('attendance')
            ->eachById(function (Booking $booking) use (&$closedBookings) {
                $checkedIn = $booking->attendance?->status === 'checked_in';
                $booking->update([
                    'status' => $checkedIn ? BookingStatus::Completed : BookingStatus::NoShow,
                    'completed_at' => $checkedIn ? now() : null,
                    'no_show_at' => $checkedIn ? null : now(),
                ]);
                AuditService::record($checkedIn ? 'booking.completed_automatically' : 'booking.no_show', $booking);
                $closedBookings++;
            });

        $expiredOffers = app(WaitlistService::class)->expireDueOffers();

        return compact('expiredBookings', 'closedBookings', 'expiredOffers');
    }
}
