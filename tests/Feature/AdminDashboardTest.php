<?php

namespace Tests\Feature;

use App\Enums\CourtStatus;
use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCourtFixtures;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use CreatesCourtFixtures;
    use RefreshDatabase;

    public function test_admin_area_requires_an_active_admin_account(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $player = User::factory()->create();
        $this->actingAs($player)->get('/admin')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('control room');
    }

    public function test_incomplete_court_cannot_be_published(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $court = Court::create([
            'name' => 'Incomplete Venue',
            'slug' => 'incomplete-venue',
            'address_line' => 'Kabacan',
            'municipality' => 'Kabacan',
            'province' => 'Cotabato',
            'status' => CourtStatus::PendingVerification,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.courts.index'))
            ->patch(route('admin.courts.publish', $court))
            ->assertSessionHasErrors('publish');

        $this->assertSame(CourtStatus::PendingVerification, $court->fresh()->status);
        $this->assertNull($court->fresh()->published_at);
    }

    public function test_admin_can_archive_a_complete_verified_listing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ['court' => $court] = $this->createPublishedCourt();

        $this->actingAs($admin)
            ->patch(route('admin.courts.archive', $court))
            ->assertSessionHasNoErrors();

        $this->assertSame(CourtStatus::Archived, $court->fresh()->status);
        $this->assertNull($court->fresh()->published_at);
    }
}
