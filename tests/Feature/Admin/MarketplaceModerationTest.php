<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingModerationActionType;
use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Models\Breed;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_pet_listing_and_record_action(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $seller = User::factory()->create(['role' => 'seller']);
        $category = PetCategory::create(['name' => 'Dogs', 'slug' => 'dogs']);
        $breed = Breed::create(['pet_category_id' => $category->id, 'name' => 'Beagle', 'slug' => 'beagle']);

        $listing = PetListing::create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'breed_id' => $breed->id,
            'title' => 'Gentle Tri-Color Beagle Puppy',
            'description' => 'Vaccinated, microchipped, loving temperament.',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 45000,
            'location' => 'Seattle, WA',
        ]);
        $listing->status = PetListingStatus::PendingReview;
        $listing->save();

        $response = $this->actingAs($admin)->post(route('admin.marketplace.moderation.approve', $listing), [
            'note' => 'Vaccination documents verified. Listing compliant with ethical standards.',
        ]);

        $response->assertRedirect(route('admin.marketplace.moderation.index'));
        $response->assertSessionHas('success');

        $listing->refresh();
        $this->assertSame(PetListingStatus::Published, $listing->status);

        $this->assertDatabaseHas('listing_moderation_actions', [
            'pet_listing_id' => $listing->id,
            'admin_user_id' => $admin->id,
            'action' => ListingModerationActionType::Approved->value,
            'note' => 'Vaccination documents verified. Listing compliant with ethical standards.',
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'listing.moderate.approved',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_reject_pet_listing_with_mandatory_reason(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $seller = User::factory()->create(['role' => 'seller']);
        $category = PetCategory::create(['name' => 'Cats', 'slug' => 'cats']);
        $breed = Breed::create(['pet_category_id' => $category->id, 'name' => 'Siamese', 'slug' => 'siamese']);

        $listing = PetListing::create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'breed_id' => $breed->id,
            'title' => 'Siamese Kitten',
            'description' => 'Available for sale.',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 30000,
            'location' => 'Tacoma, WA',
        ]);
        $listing->status = PetListingStatus::PendingReview;
        $listing->save();

        // 1. Rejection without note fails validation
        $emptyResponse = $this->actingAs($admin)->post(route('admin.marketplace.moderation.reject', $listing), [
            'note' => '',
        ]);
        $emptyResponse->assertSessionHasErrors(['note']);

        // 2. Rejection with reason succeeds
        $rejectResponse = $this->actingAs($admin)->post(route('admin.marketplace.moderation.reject', $listing), [
            'note' => 'Incomplete seller background check and missing veterinary certificates.',
        ]);

        $rejectResponse->assertRedirect(route('admin.marketplace.moderation.index'));

        $listing->refresh();
        $this->assertSame(PetListingStatus::Rejected, $listing->status);

        $this->assertDatabaseHas('listing_moderation_actions', [
            'pet_listing_id' => $listing->id,
            'admin_user_id' => $admin->id,
            'action' => ListingModerationActionType::Rejected->value,
            'note' => 'Incomplete seller background check and missing veterinary certificates.',
        ]);
    }
}
