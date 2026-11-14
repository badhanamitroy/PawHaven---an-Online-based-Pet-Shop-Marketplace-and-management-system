<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingModerationActionType;
use App\Models\ListingModerationAction;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_moderation_enum_renders_using_its_backed_value(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $listing = PetListing::factory()->create();

        ListingModerationAction::factory()->create([
            'pet_listing_id' => $listing->id,
            'admin_user_id' => $admin->id,
            'action' => ListingModerationActionType::Approved,
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Approved');
    }
}
