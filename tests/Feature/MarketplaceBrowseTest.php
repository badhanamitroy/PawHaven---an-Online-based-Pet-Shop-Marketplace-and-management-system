<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Facades\Settings;
use App\Models\Breed;
use App\Models\ListingImage;
use App\Models\Order;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::resetCache();
    }

    public function test_guest_can_browse_listings_and_filter_by_type(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $category = PetCategory::create(['name' => 'Canine', 'slug' => 'canine']);

        $saleListing = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'Purebred Golden Retriever Puppy',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 45000,
            'status' => PetListingStatus::Published,
        ]);

        $adoptionListing = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'Rescued Senior Beagle',
            'listing_type' => PetListingType::Adoption,
            'price_minor' => null,
            'status' => PetListingStatus::Published,
        ]);

        $draftListing = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'Unapproved Cat Draft',
            'status' => PetListingStatus::Draft,
        ]);

        // 1. All published
        $response = $this->get(route('listings.index'));
        $response->assertOk()
            ->assertSee('Purebred Golden Retriever Puppy')
            ->assertSee('Rescued Senior Beagle')
            ->assertDontSee('Unapproved Cat Draft');

        // 2. Filter sale only
        $saleResponse = $this->get(route('listings.index', ['type' => 'sale']));
        $saleResponse->assertOk()
            ->assertSee('Purebred Golden Retriever Puppy')
            ->assertDontSee('Rescued Senior Beagle');

        // 3. Filter adoption only
        $adoptionResponse = $this->get(route('listings.index', ['type' => 'adoption']));
        $adoptionResponse->assertOk()
            ->assertSee('Rescued Senior Beagle')
            ->assertDontSee('Purebred Golden Retriever Puppy');
    }

    public function test_guest_can_view_published_pet_details(): void
    {
        $seller = User::factory()->create(['name' => 'Alice Shelter', 'role' => 'seller']);
        $category = PetCategory::create(['name' => 'Dogs', 'slug' => 'dogs']);
        $breed = Breed::create(['pet_category_id' => $category->id, 'name' => 'Labrador', 'slug' => 'labrador']);

        $listing = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'breed_id' => $breed->id,
            'title' => 'Happy Labrador Pup',
            'description' => 'Gentle and friendly family dog.',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 50000,
            'status' => PetListingStatus::Published,
            'location' => 'Portland, OR',
        ]);

        ListingImage::create([
            'pet_listing_id' => $listing->id,
            'path' => 'listings/test-dog.jpg',
            'position' => 0,
        ]);

        $response = $this->get(route('listings.show', $listing));

        $response->assertOk()
            ->assertSee('Happy Labrador Pup')
            ->assertSee('USD 500.00')
            ->assertSee('Labrador')
            ->assertSee('Alice Shelter')
            ->assertSee('Log In to Submit Purchase Request');
    }

    public function test_guest_cannot_view_unpublished_listing(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $category = PetCategory::create(['name' => 'Cats', 'slug' => 'cats']);

        $draft = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'Under Review Kitten',
            'status' => PetListingStatus::PendingReview,
        ]);

        $response = $this->get(route('listings.show', $draft));
        $response->assertNotFound();
    }

    public function test_seller_can_preview_their_own_pending_listing(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $category = PetCategory::create(['name' => 'Birds', 'slug' => 'birds']);

        $pending = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'Pending Review Parakeet',
            'status' => PetListingStatus::PendingReview,
        ]);

        $response = $this->actingAs($seller)->get(route('listings.show', $pending));
        $response->assertOk()
            ->assertSee('Pending Review Parakeet')
            ->assertSee('AUTHOR PREVIEW');
    }

    public function test_customer_can_submit_purchase_request_for_commercial_pet_and_view_on_dashboard(): void
    {
        $seller = User::factory()->create(['name' => 'Breeder Bob', 'role' => 'seller']);
        $customer = User::factory()->create(['name' => 'Jane Buyer', 'role' => 'customer']);
        $category = PetCategory::create(['name' => 'Dogs', 'slug' => 'dogs']);

        $listing = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'Champion Line Poodle',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 60000,
            'status' => PetListingStatus::Published,
        ]);

        $response = $this->actingAs($customer)->post(route('listings.purchase', $listing), [
            'customer_phone' => '+1 (555) 345-6789',
            'shipping_city' => 'Seattle',
            'shipping_address' => '456 Pine St, Suite 2',
            'notes' => 'Looking forward to meeting the puppy on Saturday.',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'customer_name' => 'Jane Buyer',
            'customer_phone' => '+1 (555) 345-6789',
            'total_minor' => 60000,
            'status' => OrderStatus::Pending->value,
        ]);

        $order = Order::where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame($listing->seller_id, $order->seller_id);
        $this->assertNull($order->employee_seller_id);
        $this->assertSame(\App\Enums\SalesChannel::Online, $order->sales_channel);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => 'Champion Line Poodle',
            'price_minor' => 60000,
        ]);

        // Customer dashboard renders the purchase request
        $dashResponse = $this->actingAs($customer)->get(route('dashboard'));
        $dashResponse->assertOk()
            ->assertSee('Champion Line Poodle')
            ->assertSee($order->order_number);
    }

    public function test_marketplace_purchase_rejects_listing_price_outside_order_column_range(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $category = PetCategory::create(['name' => 'Dogs', 'slug' => 'dogs']);
        $listing = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'High value listing',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 4_294_967_296,
            'status' => PetListingStatus::Published,
        ]);

        $this->actingAs($customer)->post(route('listings.purchase', $listing), [
            'customer_phone' => '+1 (555) 111-0000',
            'shipping_city' => 'Seattle',
            'shipping_address' => '123 Test Street',
        ])->assertSessionHasErrors('listing');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_customer_can_submit_adoption_inquiry_for_rescue_pet(): void
    {
        $shelter = User::factory()->create(['name' => 'City Rescue', 'role' => 'seller']);
        $customer = User::factory()->create(['name' => 'Mark Adopter', 'role' => 'customer']);
        $category = PetCategory::create(['name' => 'Cats', 'slug' => 'cats']);

        $adoption = PetListing::factory()->create([
            'seller_id' => $shelter->id,
            'pet_category_id' => $category->id,
            'title' => 'Barn Cat Trio',
            'listing_type' => PetListingType::Rescue,
            'price_minor' => null,
            'status' => PetListingStatus::Published,
        ]);

        $response = $this->actingAs($customer)->post(route('listings.inquire', $adoption), [
            'customer_phone' => '+1 (555) 987-6543',
            'shipping_city' => 'Tacoma',
            'shipping_address' => '120 Country Road',
            'notes' => 'We own a secure heated barn with 5 acres and plenty of rodent control experience.',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'customer_name' => 'Mark Adopter',
            'total_minor' => 0,
            'payment_method' => 'inquiry',
        ]);
    }

    public function test_seller_cannot_purchase_own_listing(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $category = PetCategory::create(['name' => 'Dogs', 'slug' => 'dogs']);

        $listing = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'My Own Pet',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 30000,
            'status' => PetListingStatus::Published,
        ]);

        $response = $this->actingAs($seller)->post(route('listings.purchase', $listing), [
            'customer_phone' => '+1 (555) 111-2222',
            'shipping_city' => 'Seattle',
            'shipping_address' => 'My Address',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_homepage_keeps_commercial_and_adoption_listings_strictly_separated(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $category = PetCategory::create(['name' => 'Dogs', 'slug' => 'dogs']);

        $sale = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'Commercial Golden Pup',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 80000,
            'status' => PetListingStatus::Published,
        ]);

        $adoption = PetListing::factory()->create([
            'seller_id' => $seller->id,
            'pet_category_id' => $category->id,
            'title' => 'Sweet Rescue Mutt',
            'listing_type' => PetListingType::Adoption,
            'price_minor' => null,
            'status' => PetListingStatus::Published,
        ]);

        $response = $this->get(route('home'));
        $response->assertOk()
            ->assertSee('Commercial Golden Pup')
            ->assertSee('USD 800.00')
            ->assertSee('Sweet Rescue Mutt')
            ->assertSee('Ready for Adoption & Foster Care');
    }

    public function test_admin_draft_does_not_leak_to_public_homepage(): void
    {
        Settings::set('homepage.hero_headline', 'Canonical Public Headline');
        Settings::set('homepage.draft.hero_headline', 'Unpublished Admin Secret');
        Settings::set('homepage.draft_exists', true);

        // Public visitor sees canonical only
        $publicResponse = $this->get(route('home'));
        $publicResponse->assertOk()
            ->assertSee('Canonical Public Headline')
            ->assertDontSee('Unpublished Admin Secret')
            ->assertDontSee('DRAFT PREVIEW MODE');

        // Admin preview route renders draft
        $admin = User::factory()->create(['role' => 'admin']);
        $previewResponse = $this->actingAs($admin)->get(route('admin.homepage.preview'));
        $previewResponse->assertOk()
            ->assertSee('Unpublished Admin Secret')
            ->assertSee('DRAFT PREVIEW MODE');
    }
}
