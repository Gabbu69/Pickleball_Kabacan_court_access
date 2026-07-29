<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\BookingAttendance;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Services\AttendanceService;
use App\Services\BookingService;
use App\Services\CourtVerificationService;
use App\Services\MaintenanceService;
use App\Services\PaymentService;
use App\Services\ReportService;
use App\Services\WaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesCourtFixtures;
use Tests\TestCase;

class PlatformIntegrityTest extends TestCase
{
    use CreatesCourtFixtures;
    use RefreshDatabase;

    public function test_expired_pending_booking_releases_its_unique_slot_claim(): void
    {
        $player = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt();
        $booking = app(BookingService::class)->create($player, $court, $unit->id, $date->toDateString(), '18:00');

        $this->assertDatabaseHas('booking_slot_claims', ['booking_id' => $booking->id]);
        $booking->update(['expires_at' => now()->subMinute()]);

        $result = app(MaintenanceService::class)->run();

        $this->assertSame(1, $result['expiredBookings']);
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertDatabaseMissing('booking_slot_claims', ['booking_id' => $booking->id]);
    }

    public function test_editing_a_verified_fact_invalidates_its_claim_and_unpublishes_the_court(): void
    {
        ['court' => $court] = $this->createPublishedCourt();

        $this->get(route('courts.index'))->assertSee($court->name);

        app(CourtVerificationService::class)->invalidate(
            $court,
            ['address'],
            'Address was edited by its manager.',
        );

        $court->refresh();
        $this->assertSame('pending_verification', $court->status->value);
        $this->assertSame('pending', $court->verification_status);
        $this->assertNull($court->published_at);
        $this->assertDatabaseHas('court_verification_claims', [
            'court_id' => $court->id,
            'field_key' => 'address',
            'status' => 'invalidated',
        ]);
        $this->get(route('courts.index'))->assertDontSee($court->name);
    }

    public function test_partial_payments_and_refunds_recalculate_the_booking_balance(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);
        $booking = app(BookingService::class)->create($player, $court, $unit->id, $date->toDateString(), '18:00');
        $payments = app(PaymentService::class);

        $first = $payments->submit($booking, $player, 10000, null, 'PARTIAL-ONE', null);
        $payments->verify($first, $owner);

        $this->assertSame(PaymentStatus::PartiallyPaid, $booking->fresh()->payment_status);
        $this->assertSame(17500, $booking->outstanding_centavos);

        $second = $payments->submit($booking, $player, 17500, null, 'PARTIAL-TWO', null);
        $payments->verify($second, $owner);

        $this->assertSame(PaymentStatus::Verified, $booking->fresh()->payment_status);
        $this->assertSame(0, $booking->outstanding_centavos);

        $payments->refund($second, $admin, 5000, 'Approved partial refund.', 'REFUND-001');

        $this->assertSame(PaymentStatus::PartiallyPaid, $booking->fresh()->payment_status);
        $this->assertSame(22500, $booking->net_paid_centavos);
        $this->assertSame(5000, $booking->outstanding_centavos);
    }

    public function test_qr_check_in_enforces_court_ownership_time_window_and_idempotency(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $player = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);
        $booking = app(BookingService::class)->create($player, $court, $unit->id, $date->toDateString(), '18:00');
        $booking->update([
            'status' => BookingStatus::Confirmed,
            'starts_at' => now()->addMinutes(20),
            'ends_at' => now()->addMinutes(80),
            'expires_at' => null,
        ]);
        $token = app(AttendanceService::class)->issuePass($booking->fresh());

        $this->actingAs($otherOwner)
            ->postJson(route('owner.check-ins.scan'), ['token' => $token])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson(route('owner.check-ins.scan'), ['token' => $token])
            ->assertOk()
            ->assertJsonPath('booking.reference', $booking->reference);

        $this->actingAs($owner)
            ->postJson(route('owner.check-ins.scan'), ['token' => $token])
            ->assertOk();

        $this->assertDatabaseCount('booking_attendances', 1);
        $this->assertSame('checked_in', $booking->attendance()->firstOrFail()->status);

        ['court' => $futureCourt, 'unit' => $futureUnit, 'date' => $futureDate] = $this->createPublishedCourt($owner, [
            'name' => 'Future QR Test Court',
            'slug' => 'future-qr-test-court',
        ]);
        $future = app(BookingService::class)->create($player, $futureCourt, $futureUnit->id, $futureDate->toDateString(), '18:00');
        $future->update([
            'status' => BookingStatus::Confirmed,
            'starts_at' => now()->addMinutes(31),
            'ends_at' => now()->addMinutes(91),
            'expires_at' => null,
        ]);
        $futureToken = app(AttendanceService::class)->issuePass($future->fresh());

        $this->actingAs($owner)
            ->postJson(route('owner.check-ins.scan'), ['token' => $futureToken])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
    }

    public function test_ended_confirmed_bookings_become_completed_or_no_show_from_attendance(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $checkedInPlayer = User::factory()->create();
        $absentPlayer = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);
        $checkedIn = app(BookingService::class)->create($checkedInPlayer, $court, $unit->id, $date->toDateString(), '18:00');
        $checkedIn->update([
            'status' => BookingStatus::Confirmed,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'expires_at' => null,
        ]);
        BookingAttendance::create([
            'booking_id' => $checkedIn->id,
            'token_hash' => hash('sha256', 'checked-in-token'),
            'status' => 'checked_in',
            'checked_in_by' => $owner->id,
            'checked_in_at' => now()->subHours(2),
        ]);

        ['court' => $otherCourt, 'unit' => $otherUnit, 'date' => $otherDate] = $this->createPublishedCourt($owner, [
            'name' => 'No Show Test Court',
            'slug' => 'no-show-test-court',
        ]);
        $absent = app(BookingService::class)->create($absentPlayer, $otherCourt, $otherUnit->id, $otherDate->toDateString(), '18:00');
        $absent->update([
            'status' => BookingStatus::Confirmed,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'expires_at' => null,
        ]);

        $result = app(MaintenanceService::class)->run();

        $this->assertSame(2, $result['closedBookings']);
        $this->assertSame(BookingStatus::Completed, $checkedIn->fresh()->status);
        $this->assertSame(BookingStatus::NoShow, $absent->fresh()->status);
        $this->assertNotNull($checkedIn->fresh()->completed_at);
        $this->assertNotNull($absent->fresh()->no_show_at);
    }

    public function test_private_blackout_reason_is_not_returned_by_public_availability(): void
    {
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt();
        $court->blackouts()->create([
            'court_unit_id' => $unit->id,
            'starts_at' => $date->setTime(18, 0),
            'ends_at' => $date->setTime(19, 0),
            'reason' => 'Private maintenance detail',
            'is_public' => false,
        ]);

        $this->getJson(route('courts.availability', [
            'court' => $court,
            'date' => $date->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('slots.0.status', 'blocked')
            ->assertJsonPath('slots.0.reason', null)
            ->assertJsonMissing(['reason' => 'Private maintenance detail']);
    }

    public function test_the_final_active_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), [
                'role' => 'player',
                'status' => 'active',
            ])
            ->assertUnprocessable();

        $this->assertTrue($admin->fresh()->isAdmin());

        User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), [
                'role' => 'player',
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($admin->fresh()->isAdmin());
    }

    public function test_unrelated_users_cannot_download_private_payment_evidence(): void
    {
        Storage::fake('local');
        $player = User::factory()->create();
        $unrelated = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt();
        $booking = app(BookingService::class)->create($player, $court, $unit->id, $date->toDateString(), '18:00');
        Storage::disk('local')->put('payment-proofs/private-proof.pdf', 'private evidence');
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $player->id,
            'method_label' => 'Reference',
            'amount_centavos' => 10000,
            'proof_path' => 'payment-proofs/private-proof.pdf',
            'proof_disk' => 'local',
            'proof_mime' => 'application/pdf',
            'status' => PaymentStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($unrelated)
            ->get(route('payments.proof', $payment))
            ->assertForbidden();

        $this->actingAs($player)
            ->get(route('payments.proof', $payment))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_expired_waitlist_offer_advances_to_the_next_player(): void
    {
        $bookedPlayer = User::factory()->create();
        $firstWaiting = User::factory()->create();
        $secondWaiting = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'rule' => $rule, 'date' => $date] = $this->createPublishedCourt();
        $booking = app(BookingService::class)->create($bookedPlayer, $court, $unit->id, $date->toDateString(), '18:00');

        foreach ([$firstWaiting, $secondWaiting] as $waitingPlayer) {
            WaitlistEntry::create([
                'user_id' => $waitingPlayer->id,
                'court_id' => $court->id,
                'court_unit_id' => $unit->id,
                'court_schedule_rule_id' => $rule->id,
                'starts_at' => $date->setTime(18, 0),
                'ends_at' => $date->setTime(19, 0),
                'status' => 'waiting',
            ]);
        }

        app(BookingService::class)->cancelByPlayer($booking, $bookedPlayer, 'Plans changed.');
        $firstOffer = WaitlistOffer::firstOrFail();
        $firstOffer->update(['expires_at' => now()->subSecond()]);

        app(WaitlistService::class)->expireDueOffers();

        $this->assertSame('expired', $firstOffer->fresh()->status);
        $this->assertDatabaseHas('waitlist_entries', [
            'user_id' => $secondWaiting->id,
            'status' => 'offered',
        ]);
        $this->assertDatabaseHas('waitlist_offers', [
            'waitlist_entry_id' => WaitlistEntry::where('user_id', $secondWaiting->id)->value('id'),
            'status' => 'active',
        ]);
    }

    public function test_schedule_rules_cannot_overlap_or_extend_outside_operating_hours(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);

        $this->actingAs($owner)
            ->from(route('owner.courts.manage', $court))
            ->post(route('owner.courts.schedules.store', $court), [
                'court_unit_id' => $unit->id,
                'day_of_week' => $date->dayOfWeek,
                'starts_at' => '18:30',
                'ends_at' => '19:30',
                'slot_minutes' => 60,
                'price' => 300,
            ])
            ->assertSessionHasErrors('starts_at');

        $this->actingAs($owner)
            ->from(route('owner.courts.manage', $court))
            ->post(route('owner.courts.schedules.store', $court), [
                'court_unit_id' => $unit->id,
                'day_of_week' => $date->dayOfWeek,
                'starts_at' => '16:00',
                'ends_at' => '17:00',
                'slot_minutes' => 60,
                'price' => 300,
            ])
            ->assertSessionHasErrors('starts_at');
    }

    public function test_reports_use_sellable_hours_and_the_verified_payment_ledger(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $player = User::factory()->create();
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);
        $booking = app(BookingService::class)->create($player, $court, $unit->id, $date->toDateString(), '18:00');
        $booking->update(['status' => BookingStatus::Confirmed, 'expires_at' => null]);

        $payment = app(PaymentService::class)->submit(
            $booking,
            $player,
            $booking->price_centavos,
            null,
            'REPORT-PAYMENT',
            null,
        );
        app(PaymentService::class)->verify($payment, $owner);
        app(PaymentService::class)->refund($payment, $admin, 5000, 'Report fixture refund.', 'REPORT-REFUND');

        $summary = app(ReportService::class)->summarize(
            [$court->id],
            $date->startOfDay(),
            $date->endOfDay(),
        );

        $this->assertSame(60, $summary['reservedMinutes']);
        $this->assertSame(180, $summary['sellableMinutes']);
        $this->assertSame(33.3, $summary['utilizationPercent']);
        $this->assertSame(27500, $summary['grossRevenue']);
        $this->assertSame(5000, $summary['refunds']);
        $this->assertSame(22500, $summary['netRevenue']);
    }

    public function test_csv_exports_neutralize_spreadsheet_formula_cells(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $player = User::factory()->create(['name' => '=HYPERLINK("https://example.test","unsafe")']);
        ['court' => $court, 'unit' => $unit, 'date' => $date] = $this->createPublishedCourt($owner);
        app(BookingService::class)->create($player, $court, $unit->id, $date->toDateString(), '18:00');

        $response = $this->actingAs($owner)->get(route('owner.reports.export', [
            'from' => $date->toDateString(),
            'to' => $date->toDateString(),
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            '\'=HYPERLINK',
            $response->streamedContent(),
        );
    }

    public function test_health_and_cron_endpoints_report_database_state_without_exposing_secrets(): void
    {
        $this->getJson(route('deployment.health'))
            ->assertOk()
            ->assertExactJsonStructure(['status', 'database', 'time'])
            ->assertJsonPath('database', 'ready');

        config(['services.cron.secret' => 'a-secure-test-secret']);

        $this->getJson(route('cron.maintenance'))->assertUnauthorized();
        $this->withToken('a-secure-test-secret')
            ->getJson(route('cron.maintenance'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissing(['secret']);
    }
}
