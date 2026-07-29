<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtScheduleRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public const TIMEZONE = 'Asia/Manila';

    public function forCourt(Court $court, CarbonInterface|string $date): array
    {
        $day = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE)->startOfDay()
            : CarbonImmutable::parse($date, self::TIMEZONE)->startOfDay();

        $court->loadMissing(['activeUnits.scheduleRules', 'operatingHours']);
        $unitIds = $court->activeUnits->pluck('id');
        $dayStart = $day->startOfDay();
        $dayEnd = $day->endOfDay();

        $bookings = Booking::query()
            ->whereIn('court_unit_id', $unitIds)
            ->occupying()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['court_unit_id', 'starts_at', 'ends_at']);

        $blackouts = $court->blackouts()
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['court_unit_id', 'starts_at', 'ends_at', 'reason', 'is_public']);

        $slots = [];

        foreach ($court->activeUnits as $unit) {
            $rules = $unit->scheduleRules
                ->where('is_active', true)
                ->where('day_of_week', $day->dayOfWeek)
                ->filter(fn (CourtScheduleRule $rule) => $this->ruleAppliesOn($rule, $day));

            foreach ($rules as $rule) {
                $windowStart = CarbonImmutable::parse($day->toDateString().' '.$rule->starts_at, self::TIMEZONE);
                $windowEnd = CarbonImmutable::parse($day->toDateString().' '.$rule->ends_at, self::TIMEZONE);

                for ($start = $windowStart; $start->addMinutes($rule->slot_minutes)->lte($windowEnd); $start = $start->addMinutes($rule->slot_minutes)) {
                    $end = $start->addMinutes($rule->slot_minutes);
                    $booking = $this->overlap($bookings->where('court_unit_id', $unit->id), $start, $end);
                    $blackout = $this->overlap($blackouts->filter(fn ($item) => ! $item->court_unit_id || $item->court_unit_id === $unit->id), $start, $end);
                    $isPast = $start->lte(CarbonImmutable::now(self::TIMEZONE));
                    $available = ! $booking && ! $blackout && ! $isPast;

                    $slots[] = [
                        'rule_id' => $rule->id,
                        'unit_id' => $unit->id,
                        'unit_name' => $unit->name,
                        'start_at' => $start->toIso8601String(),
                        'end_at' => $end->toIso8601String(),
                        'start_time' => $start->format('H:i'),
                        'label' => $start->format('g:i A').' – '.$end->format('g:i A'),
                        'price_centavos' => $rule->price_centavos,
                        'price_label' => '₱'.number_format($rule->price_centavos / 100, 2),
                        'status' => $available ? 'available' : ($isPast ? 'past' : ($blackout ? 'blocked' : 'booked')),
                        'reason' => $blackout?->is_public ? $blackout->reason : null,
                    ];
                }
            }
        }

        return [
            'date' => $day->toDateString(),
            'timezone' => self::TIMEZONE,
            'slots' => collect($slots)->sortBy(['start_at', 'unit_name'])->values()->all(),
        ];
    }

    public function findAvailableSlot(Court $court, int $unitId, string $date, string $startTime): ?array
    {
        return collect($this->forCourt($court, $date)['slots'])
            ->first(fn (array $slot) => $slot['unit_id'] === $unitId
                && $slot['start_time'] === $startTime
                && $slot['status'] === 'available');
    }

    public function findSlot(Court $court, int $unitId, string $date, string $startTime): ?array
    {
        return collect($this->forCourt($court, $date)['slots'])
            ->first(fn (array $slot) => $slot['unit_id'] === $unitId && $slot['start_time'] === $startTime);
    }

    private function ruleAppliesOn(CourtScheduleRule $rule, CarbonImmutable $day): bool
    {
        if ($rule->valid_from && $day->lt($rule->valid_from->startOfDay())) {
            return false;
        }

        if ($rule->valid_until && $day->gt($rule->valid_until->endOfDay())) {
            return false;
        }

        return true;
    }

    private function overlap(Collection $items, CarbonImmutable $start, CarbonImmutable $end): mixed
    {
        return $items->first(fn ($item) => $start->lt($item->ends_at) && $end->gt($item->starts_at));
    }
}
