<?php

namespace Tests\Feature\PetMarketplace;

use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingVisibilityAndFavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_listing_details_are_visible_only_to_owner_and_admin(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $staff = User::factory()->create(['role' => 'staff']);
        $otherSeller = User::factory()->create(['role' => 'seller']);
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = $this->createListing($owner, PetListingStatus::PendingReview);

        $this->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs($customer)->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs($staff)->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs($otherSeller)->get(route('listings.show', $listing))->assertNotFound();
        $this->actingAs($owner)->get(route('listings.show', $listing))->assertOk();
        $this->actingAs($admin)->get(route('listings.show', $listing))->assertOk();
    }

    public function test_published_listing_details_remain_public(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $listing = $this->createListing($owner, PetListingStatus::Published);

        $this->get(route('listings.show', $listing))->assertOk();
        $this->actingAs($customer)->get(route('listings.show', $listing))->assertOk();
        $this->actingAs($staff)->get(route('listings.show', $listing))->assertOk();
        $this->actingAs($owner)->get(route('listings.show', $listing))->assertOk();
        $this->actingAs($admin)->get(route('listings.show', $listing))->assertOk();
    }

    public function test_unpublished_listings_never_appear_in_public_browse_results(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $published = $this->createListing($owner, PetListingStatus::Published, 'Public listing');
        $unpublished = $this->createListing($owner, PetListingStatus::Paused, 'Private listing');

        $this->get(route('listings.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($unpublished->title);
    }

    public function test_customer_cannot_favorite_unpublished_listing_and_no_favorite_is_created(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $listing = $this->createListing($owner, PetListingStatus::PendingReview);

        $this->actingAs($customer)
            ->post(route('listings.favorite', $listing))
            ->assertNotFound();

        $this->assertDatabaseMissing('saved_pets', [
            'user_id' => $customer->id,
            'pet_listing_id' => $listing->id,
        ]);
    }

    public function test_customer_can_favorite_and_unfavorite_a_published_listing(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $listing = $this->createListing($owner, PetListingStatus::Published, 'Published favorite');

        $this->actingAs($customer)
            ->post(route('listings.favorite', $listing))
            ->assertRedirect();

        $this->assertDatabaseHas('saved_pets', [
            'user_id' => $customer->id,
            'pet_listing_id' => $listing->id,
        ]);

        $this->actingAs($customer)
            ->post(route('listings.favorite', $listing))
            ->assertRedirect();

        $this->assertDatabaseMissing('saved_pets', [
            'user_id' => $customer->id,
            'pet_listing_id' => $listing->id,
        ]);
    }

    public function test_dashboard_hides_unpublished_historical_favorites_without_deleting_them(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $nowUnpublished = $this->createListing($owner, PetListingStatus::Paused, 'Previously saved unpublished listing');
        $published = $this->createListing($owner, PetListingStatus::Published, 'Still published favorite');

        $customer->savedPets()->attach([$nowUnpublished->id, $published->id]);

        $this->actingAs($customer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($nowUnpublished->title);

        $this->assertDatabaseHas('saved_pets', [
            'user_id' => $customer->id,
            'pet_listing_id' => $nowUnpublished->id,
        ]);
    }

    public function test_only_customers_can_submit_favorite_requests(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $staff = User::factory()->create(['role' => 'staff']);
        $listing = $this->createListing($owner, PetListingStatus::Published);

        $this->post(route('listings.favorite', $listing))->assertRedirect(route('login'));
        $this->actingAs($staff)->post(route('listings.favorite', $listing))->assertForbidden();

        $this->assertDatabaseCount('saved_pets', 0);
    }

    private function createListing(
        User $seller,
        PetListingStatus $status,
        string $title = 'Test pet listing',
    ): PetListing {
        $category = PetCategory::create([
            'name' => 'Test pets '.uniqid(),
            'slug' => 'test-pets-'.uniqid(),
        ]);

        return PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => $title,
            'listing_type' => PetListingType::Sale,
            'status' => $status,
        ]);
    }
}
