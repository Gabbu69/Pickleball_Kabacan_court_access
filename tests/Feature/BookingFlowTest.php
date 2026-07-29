<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCourtFixtures;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use CreatesCourtFixtures;
    use RefreshDatabase;

    public function test_price_and_schedule_are_derived_server_side_and_double_booking_is_rejected(): void
    {
        $player = User::factory()->create();
        $secondPlayer = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt();

        $payload = [
            'court_unit_id' => $unit->id,
            'date' => $date->toDateString(),
            'start_time' => '18:00',
            'price_centavos' => 1,
        ];

        $response = $this->actingAs($player)->post(route('bookings.store', $court), $payload);

        $booking = Booking::firstOrFail();
        $response->assertRedirect(route('bookings.show', $booking));
        $this->assertSame(27500, $booking->price_centavos);
        $this->assertSame(BookingStatus::Pending, $booking->status);

        $this->actingAs($secondPlayer)
            ->from(route('courts.show', $court))
            ->post(route('bookings.store', $court), $payload)
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseCount('bookings', 1);

        $this->actingAs($secondPlayer)
            ->post(route('waitlist.store', $court), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $secondPlayer->id,
            'court_unit_id' => $unit->id,
            'status' => 'waiting',
        ]);

        $this->actingAs($player)
            ->patch(route('bookings.cancel', $booking), ['reason' => 'Releasing this time.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $secondPlayer->id,
            'court_unit_id' => $unit->id,
            'status' => 'notified',
        ]);
    }

    public function test_player_can_cancel_inside_policy_window_and_slot_reopens(): void
    {
        $player = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt();

        $this->actingAs($player)->post(route('bookings.store', $court), [
            'court_unit_id' => $unit->id,
            'date' => $date->toDateString(),
            'start_time' => '18:00',
        ]);

        $booking = Booking::firstOrFail();

        $this->actingAs($player)
            ->patch(route('bookings.cancel', $booking), ['reason' => 'Plans changed.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);

        $this->getJson(route('courts.availability', [
            'court' => $court,
            'date' => $date->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('slots.0.status', 'available');
    }

    public function test_only_a_court_manager_can_change_its_booking_status(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $player = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);

        $this->actingAs($player)->post(route('bookings.store', $court), [
            'court_unit_id' => $unit->id,
            'date' => $date->toDateString(),
            'start_time' => '18:00',
        ]);

        $booking = Booking::firstOrFail();

        $this->actingAs($otherOwner)
            ->patch(route('owner.bookings.update', $booking), ['status' => 'confirmed'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('owner.bookings.update', $booking), ['status' => 'confirmed'])
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }
}
