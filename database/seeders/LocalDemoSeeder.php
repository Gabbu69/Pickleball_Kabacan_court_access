<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        DemoMode::assertEnabled();
        $emails = ['player@pickleplay.example.invalid', 'owner@pickleplay.example.invalid'];
        if (Court::where('slug', '!=', 'fictional-demo-court')->exists()
            || User::whereNotIn('email', $emails)->exists()) {
            throw new RuntimeException('Refusing to add demo fixtures to a database containing other records.');
        }

        DB::transaction(function () use ($emails): void {
            $accounts = [];
            foreach (['player', 'owner'] as $index => $role) {
                $accounts[$role] = User::updateOrCreate(['email' => $emails[$index]], [
                    'name' => 'Fictional Demo '.ucfirst($role),
                    'password' => Hash::make('local-demo-only-2026'),
                    'role' => $role,
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'notification_email' => false,
                ]);
            }

            $court = Court::updateOrCreate(['slug' => 'fictional-demo-court'], [
                'name' => 'Fictional Demo Court - Not a real venue',
                'short_description' => 'Synthetic booking fixture. No real court, address, rates, or availability is represented.',
                'description' => 'Local demonstration only. The illustration and map point are examples; do not travel to this location.',
                'address_line' => 'Fictional address - do not use for travel',
                'barangay' => 'Poblacion', 'municipality' => 'Kabacan', 'province' => 'Cotabato',
                'postal_code' => '9407', 'latitude' => 7.1061, 'longitude' => 124.8292,
                'environment' => 'outdoor', 'venue_type' => 'dedicated',
                'email' => 'court@pickleplay.example.invalid',
                'status' => 'published', 'verification_status' => 'verified',
                'verified_at' => now(), 'published_at' => now(),
                'payment_policy' => 'pay_on_site', 'cancellation_cutoff_hours' => 4,
            ]);
            $court->managers()->syncWithoutDetaching([$accounts['owner']->id => ['role' => 'manager']]);
            $unit = $court->units()->updateOrCreate(['name' => 'Demo Court 1'], ['environment' => 'outdoor', 'is_active' => true]);
            foreach (range(0, 6) as $day) {
                $court->operatingHours()->updateOrCreate(['day_of_week' => $day], [
                    'opens_at' => '17:00', 'closes_at' => '22:00', 'is_closed' => false,
                ]);
                $unit->scheduleRules()->updateOrCreate(['day_of_week' => $day, 'starts_at' => '18:00'], [
                    'ends_at' => '21:00', 'slot_minutes' => 60, 'price_centavos' => 27500, 'is_active' => true,
                ]);
            }
            $court->photos()->updateOrCreate(['path' => 'images/demo-court.svg'], [
                'alt_text' => 'Authored diagram for a fictional court, not a venue photograph',
                'caption' => 'Fictional illustration - not a real court',
                'is_primary' => true, 'rights_confirmed_at' => now(),
            ]);
            $court->verifications()->updateOrCreate(['type' => 'synthetic_fixture'], [
                'notes' => 'Synthetic fixture acceptance only. This is not real venue verification.',
                'submitted_by' => $accounts['owner']->id, 'verified_by' => $accounts['owner']->id,
                'status' => 'accepted', 'reviewed_at' => now(),
            ]);
        });
    }
}
