<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Support\DemoMode;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LocalDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['pickleplay.demo' => true, 'mail.default' => 'array']);
    }

    public function test_seed_requires_explicit_demo_mode_before_writing_records(): void
    {
        config(['pickleplay.demo' => false]);
        try {
            $this->seed(LocalDemoSeeder::class);
            $this->fail('Disabled demo mode must reject fixture writes.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Enable PICKLEPLAY_DEMO', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('courts', 0);
    }

    public function test_production_environment_cannot_enable_demo_mode(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        DemoMode::assertEnabled();
    }

    public function test_normal_database_cannot_be_used_for_demo_fixtures(): void
    {
        config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
        $this->expectException(RuntimeException::class);
        DemoMode::assertEnabled();
    }

    public function test_demo_mail_cannot_use_an_external_provider(): void
    {
        config(['mail.default' => 'smtp']);
        $this->expectException(RuntimeException::class);
        DemoMode::assertEnabled();
    }

    public function test_demo_database_cannot_be_served_after_disabling_the_banner(): void
    {
        config(['pickleplay.demo' => false, 'database.connections.sqlite.database' => database_path('pickleplay.demo.sqlite')]);
        $this->get('/')->assertStatus(503);
    }

    public function test_remote_requests_cannot_access_local_demo_accounts(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.25'])->get('/')->assertForbidden();
    }

    public function test_seed_refuses_a_database_containing_other_people(): void
    {
        User::factory()->create();
        try {
            $this->seed(LocalDemoSeeder::class);
            $this->fail('Existing non-demo records must prevent seeding.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('other records', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('courts', 0);
    }

    public function test_fixture_supports_discovery_booking_owner_confirmation_and_cancellation(): void
    {
        $this->seed(LocalDemoSeeder::class);
        $this->seed(LocalDemoSeeder::class);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('courts', 1);
        $this->assertDatabaseCount('court_schedule_rules', 7);
        $court = Court::firstOrFail();
        $unit = $court->units()->firstOrFail();
        $player = User::where('role', 'player')->firstOrFail();
        $owner = User::where('role', 'owner')->firstOrFail();
        $date = now()->addDays(2)->toDateString();

        $this->get(route('courts.index'))->assertOk()
            ->assertSee('Fictional Demo Court')
            ->assertSee('Fictional local demonstration.');
        $this->getJson(route('courts.availability', ['court' => $court, 'date' => $date]))
            ->assertOk()->assertJsonCount(3, 'slots')->assertJsonPath('slots.0.status', 'available');

        $this->actingAs($player)->post(route('bookings.store', $court), [
            'court_unit_id' => $unit->id, 'date' => $date, 'start_time' => '18:00', 'price_centavos' => 1,
        ])->assertSessionHasNoErrors();
        $booking = Booking::firstOrFail();
        $this->assertSame(27500, $booking->price_centavos);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->actingAs($owner)->patch(route('owner.bookings.update', $booking), ['status' => 'confirmed'])
            ->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->actingAs($player)->patch(route('bookings.cancel', $booking), ['reason' => 'End of fictional demonstration.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->getJson(route('courts.availability', ['court' => $court, 'date' => $date]))
            ->assertOk()->assertJsonPath('slots.0.status', 'available');
    }
}
