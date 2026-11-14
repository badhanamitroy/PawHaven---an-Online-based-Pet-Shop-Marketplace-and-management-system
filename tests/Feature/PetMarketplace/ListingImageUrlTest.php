<?php

namespace Tests\Feature\PetMarketplace;

use App\Enums\PetListingStatus;
use App\Models\ListingImage;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingImageUrlTest extends TestCase
{
    use RefreshDatabase;

    private const EXTERNAL_IMAGE_URL = 'https://images.example.test/pet.jpg';

    public function test_external_http_image_urls_are_returned_without_a_storage_prefix(): void
    {
        foreach ([
            'http://images.example.test/pet.jpg',
            self::EXTERNAL_IMAGE_URL,
        ] as $url) {
            $image = new ListingImage(['path' => $url]);

            $this->assertSame($url, $image->url());
        }
    }

    public function test_relative_image_paths_use_the_public_storage_url(): void
    {
        $image = new ListingImage(['path' => 'listing-images/pet.jpg']);

        $this->assertStringEndsWith('/storage/listing-images/pet.jpg', $image->url());
    }

    public function test_public_listing_pages_render_external_image_urls_directly(): void
    {
        $listing = PetListing::factory()->create(['status' => PetListingStatus::Published]);
        ListingImage::factory()->create([
            'pet_listing_id' => $listing->id,
            'path' => self::EXTERNAL_IMAGE_URL,
        ]);

        foreach ([
            route('home'),
            route('listings.index'),
            route('listings.show', $listing),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('src="'.self::EXTERNAL_IMAGE_URL.'"', false);
        }
    }

    public function test_customer_dashboard_renders_saved_pet_images(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
        $listing = PetListing::factory()->create([
            'seller_id' => $customer->id,
            'status' => PetListingStatus::Published,
        ]);
        ListingImage::factory()->create([
            'pet_listing_id' => $listing->id,
            'path' => self::EXTERNAL_IMAGE_URL,
        ]);
        $customer->savedPets()->attach($listing->id);

        $this->actingAs($customer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('src="'.self::EXTERNAL_IMAGE_URL.'"', false);
    }

    public function test_seller_dashboard_renders_listing_images(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);
        $listing = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'status' => PetListingStatus::Published,
        ]);
        ListingImage::factory()->create([
            'pet_listing_id' => $listing->id,
            'path' => self::EXTERNAL_IMAGE_URL,
        ]);

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('src="'.self::EXTERNAL_IMAGE_URL.'"', false);
    }

    public function test_admin_marketplace_views_render_listing_image_paths(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $listing = PetListing::factory()->create(['status' => PetListingStatus::Published]);
        ListingImage::factory()->create([
            'pet_listing_id' => $listing->id,
            'path' => self::EXTERNAL_IMAGE_URL,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.marketplace.listings.index'))
            ->assertOk()
            ->assertSee('src="'.self::EXTERNAL_IMAGE_URL.'"', false);

        $this->get(route('admin.marketplace.listings.show', $listing))
            ->assertOk()
            ->assertSee('src="'.self::EXTERNAL_IMAGE_URL.'"', false);
    }
}
