<?php

namespace Tests\Feature\Admin;

use App\Facades\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageCustomizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_homepage_customizer_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.homepage.update'), [
            'hero_headline' => 'Find Your True Lifelong Companion Today',
            'hero_supporting' => 'Hand-curated single shop supplies and verified adoption listings.',
            'cta_primary_label' => 'Browse Pets Now',
            'cta_primary_url' => '#find-pets',
            'cta_secondary_label' => 'Explore Shop Catalog',
            'cta_secondary_url' => '#pet-shop',
            'cta_adoption_label' => 'Adopt a Shelter Pet',
            'cta_adoption_url' => '#adoption-rescue',
            'announcement_enabled' => '1',
            'announcement_text' => 'Spring adoption event: zero adoption fees this weekend!',
            'section_categories_visible' => '1',
            'section_marketplace_visible' => '1',
            'section_shop_visible' => '1',
            'section_adoption_visible' => '1',
            'section_breeds_visible' => '0', // Hide breeds section
            'section_how_it_works_visible' => '1',
            'section_trust_safety_visible' => '1',
        ]);

        $response->assertRedirect(route('admin.homepage.index'));
        $response->assertSessionHas('success');

        // Check settings stored
        $this->assertSame('Find Your True Lifelong Companion Today', Settings::get('homepage.hero_headline'));
        $this->assertFalse(Settings::get('homepage.section_breeds_visible'));

        // Check homepage output
        $homeResponse = $this->get(route('public.home'));
        $homeResponse->assertOk()
            ->assertSee('Find Your True Lifelong Companion Today')
            ->assertSee('Browse Pets Now')
            ->assertSee('Spring adoption event: zero adoption fees this weekend!')
            ->assertDontSee('Curated Breed Spotlights • Encyclopedia Preview');
    }

    public function test_admin_can_save_draft_and_isolate_from_public(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // 1. Initial live content
        Settings::set('homepage.hero_headline', 'Original Live Headline');

        // 2. Admin saves draft
        $response = $this->actingAs($admin)->post(route('admin.homepage.draft'), [
            'hero_headline' => 'Staged Draft Headline That Is Not Live',
            'hero_supporting' => 'Draft supporting description text',
            'cta_primary_label' => 'Draft Primary',
            'cta_primary_url' => '#find-pets',
            'cta_secondary_label' => 'Draft Secondary',
            'cta_secondary_url' => '#pet-shop',
            'cta_adoption_label' => 'Draft Adoption',
            'cta_adoption_url' => '#adoption-rescue',
            'announcement_enabled' => '1',
            'announcement_text' => 'Draft announcement text',
            'section_categories_visible' => '1',
            'section_marketplace_visible' => '1',
            'section_shop_visible' => '1',
            'section_adoption_visible' => '1',
            'section_breeds_visible' => '1',
            'section_how_it_works_visible' => '1',
            'section_trust_safety_visible' => '1',
        ]);

        $response->assertRedirect(route('admin.homepage.index'));
        $this->assertTrue((bool) Settings::get('homepage.draft_exists'));

        // 3. Public visitor MUST see original headline, NOT draft
        $publicResponse = $this->get(route('public.home'));
        $publicResponse->assertOk()
            ->assertSee('Original Live Headline')
            ->assertDontSee('Staged Draft Headline That Is Not Live');

        // 4. Admin can view draft in authorized preview
        $previewResponse = $this->actingAs($admin)->get(route('admin.homepage.preview'));
        $previewResponse->assertOk()
            ->assertSee('DRAFT PREVIEW MODE')
            ->assertSee('Staged Draft Headline That Is Not Live');

        // 5. Guest is denied preview access
        $this->post(route('logout'));
        $guestPreviewResponse = $this->get(route('admin.homepage.preview'));
        $guestPreviewResponse->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_publish_and_discard_draft(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        Settings::set('homepage.hero_headline', 'Old Headline');
        Settings::set('homepage.draft.hero_headline', 'New Approved Headline');
        Settings::set('homepage.draft_exists', true);

        // Discard draft first test
        $this->actingAs($admin)->post(route('admin.homepage.discard'));
        $this->assertFalse((bool) Settings::get('homepage.draft_exists'));

        // Re-stage and publish
        Settings::set('homepage.draft.hero_headline', 'Published Headline');
        Settings::set('homepage.draft_exists', true);

        $publishResponse = $this->actingAs($admin)->post(route('admin.homepage.publish'));
        $publishResponse->assertRedirect(route('admin.homepage.index'));

        $this->assertFalse((bool) Settings::get('homepage.draft_exists'));
        $this->assertSame('Published Headline', Settings::get('homepage.hero_headline'));

        // Public visitor now sees the published headline
        $homeResponse = $this->get(route('public.home'));
        $homeResponse->assertOk()->assertSee('Published Headline');
    }
}
