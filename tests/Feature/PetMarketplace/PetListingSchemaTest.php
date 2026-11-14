<?php

namespace Tests\Feature\PetMarketplace;

use App\Models\Breed;
use App\Models\ListingImage;
use App\Models\ListingModerationAction;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetListingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_breed_listing_and_owner_relationships_are_available(): void
    {
        $category = PetCategory::factory()->create();
        $breed = Breed::factory()->for($category, 'petCategory')->create();
        $seller = User::factory()->create(['role' => 'seller']);
        $listing = PetListing::factory()
            ->for($category, 'petCategory')
            ->for($breed)
            ->for($seller, 'seller')
            ->create();

        $this->assertTrue($category->breeds->contains($breed));
        $this->assertTrue($category->listings->contains($listing));
        $this->assertTrue($breed->listings->contains($listing));
        $this->assertSame($seller->id, $listing->seller->id);
    }

    public function test_breed_can_be_deleted_without_deleting_its_listing(): void
    {
        $breed = Breed::factory()->create();
        $listing = PetListing::factory()
            ->for($breed->petCategory, 'petCategory')
            ->for($breed)
            ->create();

        $breed->delete();

        $this->assertDatabaseHas('pet_listings', [
            'id' => $listing->id,
            'breed_id' => null,
        ]);
    }

    public function test_category_cannot_be_deleted_while_breeds_or_listings_reference_it(): void
    {
        $category = PetCategory::factory()->create();
        Breed::factory()->for($category, 'petCategory')->create();

        try {
            $category->delete();
            $this->fail('A category referenced by a breed must not be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('pet_categories', ['id' => $category->id]);
        }
    }

    public function test_category_cannot_be_deleted_while_listings_directly_reference_it(): void
    {
        $category = PetCategory::factory()->create();
        PetListing::factory()->for($category, 'petCategory')->create();

        try {
            $category->delete();
            $this->fail('A category referenced by a listing must not be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('pet_categories', ['id' => $category->id]);
        }
    }

    public function test_category_and_breed_slugs_are_unique_in_their_scopes(): void
    {
        $category = PetCategory::factory()->create(['slug' => 'cats']);

        try {
            PetCategory::factory()->create(['slug' => 'cats']);
            $this->fail('Category slugs must be unique.');
        } catch (QueryException) {
            $this->assertDatabaseCount('pet_categories', 1);
        }

        Breed::factory()->for($category, 'petCategory')->create(['slug' => 'tabby']);

        $this->expectException(QueryException::class);
        Breed::factory()->for($category, 'petCategory')->create(['slug' => 'tabby']);
    }

    public function test_owner_cannot_be_deleted_while_they_own_listings(): void
    {
        $listing = PetListing::factory()->create();

        $this->expectException(QueryException::class);
        $listing->seller->delete();
    }

    public function test_listing_deletion_cascades_images_and_preserves_history_without_listing_reference(): void
    {
        $listing = PetListing::factory()->create();
        $image = ListingImage::factory()->for($listing, 'petListing')->create();
        $action = $listing->moderationActions()->create([
            'admin_user_id' => User::factory()->create(['role' => 'admin'])->id,
            'action' => 'approved',
        ]);

        $listing->delete();

        $this->assertDatabaseMissing('listing_images', ['id' => $image->id]);
        $this->assertDatabaseHas('listing_moderation_actions', [
            'id' => $action->id,
            'pet_listing_id' => null,
        ]);
    }

    public function test_deleting_an_admin_preserves_moderation_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $action = ListingModerationAction::factory()
            ->for($admin, 'admin')
            ->create();

        $admin->delete();

        $this->assertDatabaseHas('listing_moderation_actions', [
            'id' => $action->id,
            'admin_user_id' => null,
        ]);
    }

    public function test_listing_images_are_returned_in_position_order(): void
    {
        $listing = PetListing::factory()->create();
        $last = ListingImage::factory()->for($listing, 'petListing')->create(['position' => 3]);
        $first = ListingImage::factory()->for($listing, 'petListing')->create(['position' => 0]);

        $this->assertSame(
            [$first->id, $last->id],
            $listing->images->modelKeys(),
        );
    }

    public function test_listing_image_positions_must_be_unique_per_listing(): void
    {
        $listing = PetListing::factory()->create();
        ListingImage::factory()->for($listing, 'petListing')->create(['position' => 1]);

        $this->expectException(QueryException::class);
        ListingImage::factory()->for($listing, 'petListing')->create(['position' => 1]);
    }
}
