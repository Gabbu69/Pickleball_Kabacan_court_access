<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * @param  Collection<int, int>|array<int, int>  $courtIds
     */
    public function summarize(Collection|array $courtIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $courtIds = collect($courtIds)->map(fn ($id) => (int) $id)->values();
        $bookings = Booking::query()->whereIn('court_id', $courtIds)->whereBetween('starts_at', [$from, $to]);
        $reservedMinutes = (clone $bookings)
            ->whereIn('status', ['confirmed', 'completed', 'no_show'])
            ->get()
            ->sum(fn (Booking $booking) => (int) $booking->starts_at->diffInMinutes($booking->ends_at));
        $sellableMinutes = $this->sellableMinutes($courtIds, $from, $to);

        $verifiedRevenue = (int) Payment::query()
            ->whereHas('booking', fn ($query) => $query
                ->whereIn('court_id', $courtIds)
                ->whereBetween('starts_at', [$from, $to]))
            ->where('status', 'verified')
            ->sum('amount_centavos');
        $refunds = (int) PaymentRefund::query()
            ->whereHas('booking', fn ($query) => $query
                ->whereIn('court_id', $courtIds)
                ->whereBetween('starts_at', [$from, $to]))
            ->sum('amount_centavos');
        $pendingPayments = (int) Payment::query()
            ->whereHas('booking', fn ($query) => $query
                ->whereIn('court_id', $courtIds)
                ->whereBetween('starts_at', [$from, $to]))
            ->where('status', 'submitted')
            ->sum('amount_centavos');
        $bookingCount = (clone $bookings)->count();
        $completedCount = (clone $bookings)->where('status', 'completed')->count();
        $cancelledCount = (clone $bookings)->whereIn('status', ['cancelled', 'rejected', 'expired'])->count();

        return [
            'bookingCount' => $bookingCount,
            'completedCount' => $completedCount,
            'cancelledCount' => $cancelledCount,
            'noShowCount' => (clone $bookings)->where('status', 'no_show')->count(),
            'completionRate' => $bookingCount > 0 ? round(($completedCount / $bookingCount) * 100, 1) : 0,
            'cancellationRate' => $bookingCount > 0 ? round(($cancelledCount / $bookingCount) * 100, 1) : 0,
            'reservedMinutes' => $reservedMinutes,
            'sellableMinutes' => $sellableMinutes,
            'utilizationPercent' => $sellableMinutes > 0 ? min(100, round(($reservedMinutes / $sellableMinutes) * 100, 1)) : 0,
            'grossRevenue' => $verifiedRevenue,
            'refunds' => $refunds,
            'netRevenue' => max(0, $verifiedRevenue - $refunds),
            'pendingPayments' => $pendingPayments,
        ];
    }

    private function sellableMinutes(Collection $courtIds, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $courts = Court::query()
            ->whereIn('id', $courtIds)
            ->with(['activeUnits.scheduleRules', 'operatingHours', 'blackouts'])
            ->get();
        $minutes = 0;

        for ($date = $from->startOfDay(); $date->lte($to->endOfDay()); $date = $date->addDay()) {
            foreach ($courts as $court) {
                $slots = $this->availability->forCourt($court, $date)['slots'];
                foreach ($slots as $slot) {
                    if ($slot['status'] !== 'blocked') {
                        $minutes += CarbonImmutable::parse($slot['start_at'])
                            ->diffInMinutes(CarbonImmutable::parse($slot['end_at']));
                    }
                }
            }
        }

        return (int) $minutes;
    }
}
