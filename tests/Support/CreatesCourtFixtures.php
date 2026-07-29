<?php

namespace Tests\Support;

use App\Enums\CourtStatus;
use App\Models\Court;
use App\Models\CourtPhoto;
use App\Models\CourtScheduleRule;
use App\Models\CourtUnit;
use App\Models\CourtVerification;
use App\Models\User;
use Carbon\CarbonImmutable;

trait CreatesCourtFixtures
{
    /**
     * @return array{court: Court, unit: CourtUnit, rule: CourtScheduleRule, date: CarbonImmutable}
     */
    protected function createPublishedCourt(?User $manager = null, array $overrides = []): array
    {
        $date = CarbonImmutable::today('Asia/Manila')->addDays(3);

        $court = Court::create(array_merge([
            'name' => 'Kabacan Community Pickle Court',
            'slug' => 'kabacan-community-pickle-court',
            'short_description' => 'A verified test venue in Kabacan.',
            'address_line' => 'Test Avenue',
            'barangay' => 'Poblacion',
            'municipality' => 'Kabacan',
            'province' => 'Cotabato',
            'postal_code' => '9407',
            'latitude' => 7.1061000,
            'longitude' => 124.8292000,
            'environment' => 'outdoor',
            'venue_type' => 'dedicated',
            'phone' => '09170000000',
            'verification_status' => 'verified',
            'status' => CourtStatus::Published,
            'payment_policy' => 'pay_on_site',
            'cancellation_cutoff_hours' => 4,
            'verified_at' => now(),
            'published_at' => now(),
        ], $overrides));

        if ($manager) {
            $court->managers()->attach($manager, ['role' => 'manager']);
        }

        $unit = CourtUnit::create([
            'court_id' => $court->id,
            'name' => 'Court 1',
            'environment' => 'outdoor',
            'is_active' => true,
        ]);

        $rule = CourtScheduleRule::create([
            'court_unit_id' => $unit->id,
            'day_of_week' => $date->dayOfWeek,
            'starts_at' => '18:00',
            'ends_at' => '21:00',
            'slot_minutes' => 60,
            'price_centavos' => 27500,
            'is_active' => true,
        ]);

        CourtPhoto::create([
            'court_id' => $court->id,
            'path' => 'storage/court-photos/verified-test-court.jpg',
            'alt_text' => 'Verified test court',
            'is_primary' => true,
            'rights_confirmed_at' => now(),
        ]);

        CourtVerification::create([
            'court_id' => $court->id,
            'type' => 'field_verification',
            'notes' => 'Fixture evidence.',
            'status' => 'accepted',
            'reviewed_at' => now(),
        ]);

        $court->operatingHours()->create([
            'day_of_week' => $date->dayOfWeek,
            'opens_at' => '17:00',
            'closes_at' => '22:00',
            'is_closed' => false,
        ]);

        return compact('court', 'unit', 'rule', 'date');
    }
}
