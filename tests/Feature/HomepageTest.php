<?php

namespace Tests\Feature;

use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Models\Breed;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads_successfully_with_pawhaven_brand_and_tagline(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('PawHaven')
            ->assertSee('Find Pets. Give Hope. Shop with Love.')
            ->assertSee('Your next best friend is waiting.')
            ->assertSee('Discover pets, shop food and accessories, and help animals find loving homes—all in one place.')
            ->assertSee('Explore Pets')
            ->assertSee('Shop Pet Supplies')
            ->assertSee('Explore Adoption')
            ->assertSee('Community')
            ->assertSee('Shopping Cart')
            ->assertSee('Browse by Pet Category')
            ->assertSee('Meet your future companion')
            ->assertSee('Every pet deserves a safe place to call home.')
            ->assertSee('Get to know your perfect match')
            ->assertSee('Everything your companion needs')
            ->assertSee('How PawHaven Works')
            ->assertSee('Built on Trust, Care & Community');
    }

    public function test_homepage_renders_green_logo_in_header_and_white_logo_in_footer(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('pawhaven-logo-green.png')
            ->assertSee('pawhaven-logo-white.png');
    }

    public function test_homepage_shows_empty_state_when_no_published_listings_exist(): void
    {
        // Even if an unpublished listing exists
        PetListing::factory()->create(['status' => PetListingStatus::Draft]);
        PetListing::factory()->create(['status' => PetListingStatus::PendingReview]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Explore Marketplace')
            ->assertDontSee('Draft');
    }

    public function test_homepage_displays_only_published_listings_with_details_and_contact(): void
    {
        $category = PetCategory::factory()->create(['name' => 'Dogs']);
        $breed = Breed::factory()->create(['pet_category_id' => $category->id, 'name' => 'Golden Retriever']);

        $published = PetListing::factory()->create([
            'pet_category_id' => $category->id,
            'breed_id' => $breed->id,
            'title' => 'Friendly Golden Companion',
            'status' => PetListingStatus::Published,
            'listing_type' => PetListingType::Sale,
            'price_minor' => 25000,
            'location' => 'Seattle, WA',
        ]);

        $draft = PetListing::factory()->create([
            'title' => 'Hidden Secret Dog',
            'status' => PetListingStatus::Draft,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Friendly Golden Companion')
            ->assertSee('$250.00')
            ->assertSee('Available')
            ->assertSee('Seattle, WA')
            ->assertSee('View Listing & Contact Seller')
            ->assertDontSee('Hidden Secret Dog')
            ->assertDontSee('New pet listings are being prepared');
    }

    public function test_homepage_displays_pet_shop_and_single_store_governance(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Official PawHaven Store')
            ->assertSee('Everything your companion needs')
            ->assertSee('Food & Treats')
            ->assertSee('Toys & Enrichment')
            ->assertSee('PawHaven Heritage All-Natural Salmon & Rice Dry Food (12kg)')
            ->assertSee('In Stock')
            ->assertSee('$64.99')
            ->assertSee('Centrally Managed PawHaven Store')
            ->assertSee('sole provider and retailer for pet products')
            ->assertSee('Upcoming E-Commerce Milestone');
    }

    public function test_homepage_displays_adoption_community_and_breed_preview(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Shelter & Rescue Network')
            ->assertSee('Gentle Rehoming Service')
            ->assertSee('Lost & Found Community')
            ->assertSee('Foster & Volunteer Network')
            ->assertSee('Curated Breed Spotlights • Encyclopedia Preview')
            ->assertSee('Golden Retriever')
            ->assertSee('British Shorthair');
    }

    public function test_homepage_displays_authentication_state_for_guests_and_users(): void
    {
        $guestResponse = $this->get(route('home'));
        $guestResponse->assertOk()
            ->assertSee('Log In')
            ->assertSee('Sign Up')
            ->assertDontSee('Dashboard');

        $user = User::factory()->create();
        $rootResponse = $this->actingAs($user)->get(route('home'));
        $rootResponse->assertRedirect(route('dashboard'));

        $userResponse = $this->actingAs($user)->get(route('public.home'));
        $userResponse->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Log Out')
            ->assertDontSee('Sign Up');
    }
}
