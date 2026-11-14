<?php

namespace Tests\Feature\PetMarketplace;

use App\Enums\ListingModerationActionType;
use App\Enums\PetListingStatus;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PetListingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_manage_only_their_own_listings(): void
    {
        $listing = PetListing::factory()->create();
        $anotherSeller = User::factory()->create(['role' => 'seller']);

        $this->assertTrue(Gate::forUser($listing->seller)->allows('update', $listing));
        $this->assertTrue(Gate::forUser($listing->seller)->allows('delete', $listing));
        $this->assertFalse(Gate::forUser($anotherSeller)->allows('update', $listing));
        $this->assertFalse(Gate::forUser($anotherSeller)->allows('delete', $listing));
    }

    public function test_only_sellers_can_create_listings_and_admins_do_not_manage_owner_content(): void
    {
        $listing = PetListing::factory()->create();
        $customer = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue(Gate::forUser($listing->seller)->allows('create', PetListing::class));
        $this->assertFalse(Gate::forUser($customer)->allows('create', PetListing::class));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $listing));
    }

    public function test_only_admins_can_moderate_and_moderation_records_history(): void
    {
        $listing = PetListing::factory()->create();
        $listing->submitForReview($listing->seller);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue(Gate::forUser($admin)->allows('moderate', $listing));
        $this->assertFalse(Gate::forUser($listing->seller)->allows('moderate', $listing));

        $action = $listing->moderate($admin, ListingModerationActionType::Approved, 'Approved after review.');

        $this->assertSame(PetListingStatus::Published, $listing->refresh()->status);
        $this->assertSame(ListingModerationActionType::Approved, $action->action);
        $this->assertDatabaseHas('listing_moderation_actions', [
            'id' => $action->id,
            'pet_listing_id' => $listing->id,
            'admin_user_id' => $admin->id,
            'action' => 'approved',
        ]);
    }

    public function test_listing_lifecycle_requires_valid_state_transitions(): void
    {
        $listing = PetListing::factory()->create();
        $seller = $listing->seller;
        $admin = User::factory()->create(['role' => 'admin']);

        $listing->submitForReview($seller);
        $listing->moderate($admin, ListingModerationActionType::Rejected, 'Please correct the description.');
        $listing->refresh()->submitForReview($seller);
        $listing->moderate($admin, ListingModerationActionType::Approved);
        $listing->refresh()->pause($seller);
        $listing->refresh()->markCompleted($seller);

        $this->assertSame(PetListingStatus::Completed, $listing->refresh()->status);
        $this->assertCount(2, $listing->moderationActions);
    }

    public function test_seller_cannot_submit_another_owners_listing_for_review(): void
    {
        $listing = PetListing::factory()->create();
        $otherSeller = User::factory()->create(['role' => 'seller']);

        $this->expectException(AuthorizationException::class);
        $listing->submitForReview($otherSeller);
    }

    public function test_invalid_moderation_transition_is_rejected(): void
    {
        $listing = PetListing::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->expectException(ValidationException::class);
        $listing->moderate($admin, ListingModerationActionType::Approved);
    }

    public function test_public_visibility_allows_viewing_published_listings_only(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create();

        $published = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::Published]);
        $draft = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::Draft]);
        $pending = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::PendingReview]);
        $paused = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::Paused]);
        $rejected = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::Rejected]);
        $removed = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::Removed]);
        $completed = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::Completed]);

        // Guests (unauthenticated)
        $this->assertTrue(Gate::forUser(null)->allows('view', $published));
        $this->assertFalse(Gate::forUser(null)->allows('view', $draft));
        $this->assertFalse(Gate::forUser(null)->allows('view', $pending));
        $this->assertFalse(Gate::forUser(null)->allows('view', $paused));
        $this->assertFalse(Gate::forUser(null)->allows('view', $rejected));
        $this->assertFalse(Gate::forUser(null)->allows('view', $removed));
        $this->assertFalse(Gate::forUser(null)->allows('view', $completed));

        // Customers
        $this->assertTrue(Gate::forUser($customer)->allows('view', $published));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $draft));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $pending));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $paused));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $rejected));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $removed));
        $this->assertFalse(Gate::forUser($customer)->allows('view', $completed));
    }

    public function test_seller_and_admin_visibility_for_non_published_listings(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $otherSeller = User::factory()->create(['role' => 'seller']);
        $admin = User::factory()->create(['role' => 'admin']);

        $draft = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::Draft]);
        $paused = PetListing::factory()->for($seller, 'seller')->create(['status' => PetListingStatus::Paused]);

        // Owner can view non-published listings
        $this->assertTrue(Gate::forUser($seller)->allows('view', $draft));
        $this->assertTrue(Gate::forUser($seller)->allows('view', $paused));

        // Other seller cannot view
        $this->assertFalse(Gate::forUser($otherSeller)->allows('view', $draft));
        $this->assertFalse(Gate::forUser($otherSeller)->allows('view', $paused));

        // Admin can view any listing for moderation and oversight
        $this->assertTrue(Gate::forUser($admin)->allows('view', $draft));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $paused));
    }

    public function test_seller_cannot_pause_or_complete_another_owners_listing(): void
    {
        $listing = PetListing::factory()->create(['status' => PetListingStatus::Published]);
        $otherSeller = User::factory()->create(['role' => 'seller']);

        try {
            $listing->pause($otherSeller);
            $this->fail('Other seller must not be able to pause another owner\'s listing.');
        } catch (AuthorizationException) {
            $this->assertSame(PetListingStatus::Published, $listing->refresh()->status);
        }

        try {
            $listing->markCompleted($otherSeller);
            $this->fail('Other seller must not be able to complete another owner\'s listing.');
        } catch (AuthorizationException) {
            $this->assertSame(PetListingStatus::Published, $listing->refresh()->status);
        }
    }

    public function test_non_admin_cannot_moderate_listing(): void
    {
        $listing = PetListing::factory()->create(['status' => PetListingStatus::PendingReview]);
        $customer = User::factory()->create();
        $seller = $listing->seller;

        $this->expectException(AuthorizationException::class);
        $listing->moderate($customer, ListingModerationActionType::Approved);
    }
}
