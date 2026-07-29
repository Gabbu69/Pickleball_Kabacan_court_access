<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCourtFixtures;
use Tests\TestCase;

class PaymentAndReviewTest extends TestCase
{
    use CreatesCourtFixtures;
    use RefreshDatabase;

    public function test_player_submits_payment_details_and_manager_verifies_them(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $player = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);

        $this->actingAs($player)->post(route('bookings.store', $court), [
            'court_unit_id' => $unit->id,
            'date' => $date->toDateString(),
            'start_time' => '18:00',
        ]);

        $booking = Booking::firstOrFail();

        $this->actingAs($player)->post(route('payments.store', $booking), [
            'amount' => '275.00',
            'reference' => 'GCASH-TEST-123',
        ])->assertSessionHasNoErrors();

        $payment = Payment::firstOrFail();
        $this->assertSame(27500, $payment->amount_centavos);
        $this->assertSame(PaymentStatus::Submitted, $booking->fresh()->payment_status);

        $this->actingAs($owner)
            ->patch(route('owner.payments.verify', $payment), ['notes' => 'Reference matched.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Verified, $booking->fresh()->payment_status);
    }

    public function test_review_requires_the_players_completed_booking(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $player = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);

        $this->actingAs($player)->post(route('bookings.store', $court), [
            'court_unit_id' => $unit->id,
            'date' => $date->toDateString(),
            'start_time' => '18:00',
        ]);

        $booking = Booking::firstOrFail();
        $review = ['rating' => 5, 'body' => 'The verified court experience was excellent.'];

        $this->actingAs($player)->post(route('reviews.store', $booking), $review)->assertStatus(422);

        $booking->update([
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);
        BookingAttendance::create([
            'booking_id' => $booking->id,
            'token_hash' => hash('sha256', 'fixture-token'),
            'status' => 'checked_in',
            'checked_in_by' => $owner->id,
            'checked_in_at' => now(),
        ]);

        $this->actingAs($player)
            ->post(route('reviews.store', $booking), $review)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'user_id' => $player->id,
            'status' => 'pending',
            'rating' => 5,
        ]);
    }
}
