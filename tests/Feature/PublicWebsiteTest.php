<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCourtFixtures;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use CreatesCourtFixtures;
    use RefreshDatabase;

    public function test_public_experience_renders_with_original_branding(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kabacan PicklePlay')
            ->assertSee('No made-up listings')
            ->assertSee('images/hero/kabacan-court-hero.webp', false)
            ->assertSee('images/hero/pickleplay-smash-paddle-v3.webp', false)
            ->assertSee('data-theme="light"', false)
            ->assertSee('kpp-theme', false)
            ->assertSee('theme-toggle', false)
            ->assertSee('images/hero/pickleplay-ball-real-v2.webp', false)
            ->assertDontSee('images/hero/pickleplay-smash-paddle-v2.webp', false)
            ->assertDontSee('Kabacan court energy')
            ->assertDontSee('kabacan-pickleplay-motion-reference.mp4', false);

        $this->get('/courts')
            ->assertOk()
            ->assertSee('Kabacan court directory');
    }

    public function test_theme_toggle_is_shared_across_public_guest_and_account_layouts(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('theme-toggle', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('theme-toggle', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('theme-toggle', false);
    }

    public function test_seeded_usm_reference_stays_hidden_until_venue_facts_are_verified(): void
    {
        $this->seed();

        $usm = Court::where('slug', 'university-of-southern-mindanao-outdoor-pickleball-court')->firstOrFail();

        $this->assertSame('draft', $usm->status->value);
        $this->assertSame('unverified', $usm->verification_status);
        $this->get('/courts')->assertDontSee($usm->name);
        $this->get('/courts/'.$usm->slug)->assertNotFound();
    }

    public function test_published_verified_court_appears_in_directory_and_availability_api(): void
    {
        ['court' => $court, 'date' => $date] = $this->createPublishedCourt();

        $this->get('/courts')
            ->assertOk()
            ->assertSee($court->name)
            ->assertSee('Poblacion');

        $this->getJson(route('courts.availability', [
            'court' => $court,
            'date' => $date->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('timezone', 'Asia/Manila')
            ->assertJsonPath('slots.0.status', 'available')
            ->assertJsonPath('slots.0.price_centavos', 27500);

        $court->photos()->delete();

        $this->get('/courts')->assertDontSee($court->name);
        $this->get(route('courts.show', $court))->assertNotFound();
        $this->getJson(route('courts.availability', [
            'court' => $court,
            'date' => $date->toDateString(),
        ]))->assertNotFound();
    }
}
