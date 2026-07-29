<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Court;
use App\Models\CourtVerification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = (string) env('ADMIN_PASSWORD', 'password');

        if (app()->isProduction() && ($adminPassword === 'password' || mb_strlen($adminPassword) < 12)) {
            throw new RuntimeException('Set ADMIN_PASSWORD to a unique value of at least 12 characters before production seeding.');
        }

        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@kabacanpickleplay.test')],
            [
                'name' => env('ADMIN_NAME', 'Kabacan PicklePlay Admin'),
                'phone' => env('ADMIN_PHONE'),
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'status' => 'active',
                'notification_email' => false,
                'email_verified_at' => now(),
            ],
        );

        foreach ([
            ['Parking', 'parking', 'parking'],
            ['Restrooms', 'restrooms', 'restroom'],
            ['Court lighting', 'court-lighting', 'light'],
            ['Equipment rental', 'equipment-rental', 'paddle'],
            ['Seating area', 'seating-area', 'seat'],
            ['Water station', 'water-station', 'water'],
            ['Wheelchair accessible', 'wheelchair-accessible', 'accessibility'],
            ['Showers', 'showers', 'shower'],
            ['Locker area', 'locker-area', 'locker'],
            ['Pro shop', 'pro-shop', 'shop'],
        ] as [$name, $slug, $icon]) {
            Amenity::updateOrCreate(['slug' => $slug], compact('name', 'icon'));
        }

        $usm = Court::updateOrCreate(
            ['slug' => 'university-of-southern-mindanao-outdoor-pickleball-court'],
            [
                'name' => 'University of Southern Mindanao Outdoor Pickle Ball Court',
                'short_description' => 'Officially identified outdoor pickleball court at the USM Kabacan campus. Booking details remain unpublished until verified.',
                'address_line' => 'Bai Matabay Plang Avenue',
                'barangay' => 'Poblacion',
                'municipality' => 'Kabacan',
                'province' => 'Cotabato',
                'postal_code' => '9407',
                'environment' => 'outdoor',
                'venue_type' => 'dedicated',
                'verification_status' => 'unverified',
                'status' => 'draft',
                'payment_policy' => 'pay_on_site',
            ],
        );

        CourtVerification::updateOrCreate(
            [
                'court_id' => $usm->id,
                'source_url' => 'https://www.usm.edu.ph/portfolio-item/outdoor-pickle-ball-court/',
            ],
            [
                'type' => 'official_page',
                'notes' => 'The official USM page confirms the court name and campus address only. Rates, schedules, photos for reuse, amenities, contacts, and availability still require owner or field verification.',
                'submitted_by' => $admin->id,
                'status' => 'pending',
            ],
        );
    }
}
