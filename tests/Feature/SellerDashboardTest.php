<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Models\Breed;
use App\Models\Order;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $customer;

    private PetCategory $category;

    private Breed $breed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'name' => 'Jane Seller',
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $this->customer = User::factory()->create([
            'name' => 'John Customer',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->category = PetCategory::create([
            'name' => 'Dogs',
            'slug' => 'dogs',
        ]);

        $this->breed = Breed::create([
            'pet_category_id' => $this->category->id,
            'name' => 'Golden Retriever',
            'slug' => 'golden-retriever',
        ]);
    }

    public function test_seller_sees_personalized_greeting_and_persisted_metrics(): void
    {
        // 1 Published, 1 Draft, 1 Rejected
        PetListing::create([
            'seller_id' => $this->seller->id,
            'pet_category_id' => $this->category->id,
            'breed_id' => $this->breed->id,
            'title' => 'Happy Golden Pup',
            'description' => 'A sweet healthy puppy',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 85000,
            'location' => 'Austin, TX',
            'status' => PetListingStatus::Published,
        ]);

        PetListing::create([
            'seller_id' => $this->seller->id,
            'pet_category_id' => $this->category->id,
            'breed_id' => $this->breed->id,
            'title' => 'Draft Puppy Post',
            'description' => 'Draft details',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 50000,
            'location' => 'Dallas, TX',
            'status' => PetListingStatus::Draft,
        ]);

        // Incoming Order
        Order::create([
            'order_number' => 'PET-REQ-001',
            'user_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '+15551234567',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'Austin',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 85000,
            'delivery_fee_minor' => 0,
            'total_minor' => 85000,
            'notes' => 'Looking forward to meeting the puppy',
        ]);

        $response = $this->actingAs($this->seller)->get(route('seller.dashboard'));

        $response->assertOk();
        $response->assertSee('Hello, Jane Seller!');
        $response->assertSee("Here's what's happening with your shop today.", false);
        $response->assertSee('New Requests');
        $response->assertSee('Awaiting Action');
        $response->assertSee('Payment Verification');
        $response->assertSee('Happy Golden Pup');
        $response->assertSee('PET-REQ-001');
    }

    public function test_customer_and_guest_cannot_access_seller_routes(): void
    {
        // Guest is redirected
        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.login'));
        $this->get(route('seller.listings.index'))->assertRedirect(route('seller.login'));
        $this->get(route('seller.requests.index'))->assertRedirect(route('seller.login'));

        // Customer gets 403 Forbidden
        $this->actingAs($this->customer)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($this->customer)->get(route('seller.listings.index'))->assertForbidden();
        $this->actingAs($this->customer)->get(route('seller.requests.index'))->assertForbidden();
    }

    public function test_seller_can_create_listing_with_photos(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->create('pup.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->seller)->post(route('seller.listings.store'), [
            'title' => 'Adorable Labrador Puppy',
            'pet_category_id' => $this->category->id,
            'breed_id' => $this->breed->id,
            'listing_type' => 'sale',
            'price' => '750.00',
            'location' => 'Houston, TX',
            'description' => 'Gentle temperament and fully vaccinated puppy.',
            'action' => 'submit',
            'images' => [$image],
        ]);

        $response->assertRedirect(route('seller.listings.index'));
        $response->assertSessionHas('success');

        $listing = PetListing::where('title', 'Adorable Labrador Puppy')->first();
        $this->assertNotNull($listing);
        $this->assertEquals($this->seller->id, $listing->seller_id);
        $this->assertEquals(PetListingStatus::PendingReview, $listing->status);
        $this->assertEquals(75000, $listing->price_minor);
        $this->assertCount(1, $listing->images);
        Storage::disk('public')->assertExists($listing->images->first()->path);
    }

    public function test_seller_can_pause_and_resubmit_listing(): void
    {
        $listing = PetListing::create([
            'seller_id' => $this->seller->id,
            'pet_category_id' => $this->category->id,
            'breed_id' => $this->breed->id,
            'title' => 'Active Listing to Pause',
            'description' => 'Description here',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 40000,
            'location' => 'Dallas, TX',
            'status' => PetListingStatus::Published,
        ]);

        // Pause
        $response = $this->actingAs($this->seller)->patch(route('seller.listings.status', $listing), [
            'action' => 'pause',
        ]);

        $response->assertRedirect();
        $this->assertEquals(PetListingStatus::Paused, $listing->fresh()->status);
    }

    public function test_seller_cannot_modify_or_delete_another_sellers_listing_idor(): void
    {
        $otherSeller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $foreignListing = PetListing::create([
            'seller_id' => $otherSeller->id,
            'pet_category_id' => $this->category->id,
            'breed_id' => $this->breed->id,
            'title' => 'Other Seller Pup',
            'description' => 'Other seller details',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 60000,
            'location' => 'San Antonio, TX',
            'status' => PetListingStatus::Published,
        ]);

        // Seller A tries to edit Seller B's listing
        $this->actingAs($this->seller)
            ->get(route('seller.listings.edit', $foreignListing))
            ->assertForbidden();

        // Seller A tries to update Seller B's listing
        $this->actingAs($this->seller)
            ->put(route('seller.listings.update', $foreignListing), [
                'title' => 'Hacked Title',
                'listing_type' => 'sale',
                'price' => '100',
                'location' => 'Nowhere',
                'description' => 'Compromised',
            ])
            ->assertForbidden();

        // Seller A tries to delete Seller B's listing
        $this->actingAs($this->seller)
            ->delete(route('seller.listings.destroy', $foreignListing))
            ->assertForbidden();

        $this->assertDatabaseHas('pet_listings', ['id' => $foreignListing->id]);
    }

    public function test_seller_can_progress_order_lifecycle_without_delivery_being_treated_as_payment(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-100',
            'user_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '+15559876543',
            'shipping_address' => '456 Oak Avenue',
            'shipping_city' => 'Austin',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 50000,
            'delivery_fee_minor' => 0,
            'total_minor' => 50000,
        ]);

        // 1. Pending -> Processing
        $this->actingAs($this->seller)
            ->patch(route('seller.requests.status', $order), ['status' => 'processing'])
            ->assertRedirect();
        $this->assertEquals(OrderStatus::Processing, $order->fresh()->status);

        // 2. Processing -> Shipped
        $this->actingAs($this->seller)
            ->patch(route('seller.requests.status', $order), ['status' => 'shipped'])
            ->assertRedirect();
        $this->assertEquals(OrderStatus::Shipped, $order->fresh()->status);

        // 3. Shipped -> Delivered does not imply that cash was received.
        $this->actingAs($this->seller)
            ->patch(route('seller.requests.status', $order), ['status' => 'delivered'])
            ->assertRedirect();
        $this->assertEquals(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Pending, $order->fresh()->payment_status);

        // 4. Record payment reference
        $this->actingAs($this->seller)
            ->patch(route('seller.requests.payment', $order), [
                'payment_status' => 'paid',
                'payment_reference' => 'IN-PERSON-RECEIPT-99',
            ])
            ->assertRedirect();
        $this->assertEquals('IN-PERSON-RECEIPT-99', $order->fresh()->payment_reference);
        $this->assertSame('verified', PaymentTransaction::query()->where('order_id', $order->id)->sole()->status->value);
    }

    public function test_seller_cannot_view_or_progress_another_sellers_orders_idor(): void
    {
        $otherSeller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $foreignOrder = Order::create([
            'order_number' => 'ORD-FOREIGN-999',
            'user_id' => $this->customer->id,
            'seller_id' => $otherSeller->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '+15550001111',
            'shipping_address' => '789 Pine Road',
            'shipping_city' => 'Dallas',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 30000,
            'delivery_fee_minor' => 0,
            'total_minor' => 30000,
        ]);

        // Seller A cannot view Seller B's order
        $this->actingAs($this->seller)
            ->get(route('seller.requests.show', $foreignOrder))
            ->assertForbidden();

        // Seller A cannot update Seller B's order status
        $this->actingAs($this->seller)
            ->patch(route('seller.requests.status', $foreignOrder), ['status' => 'processing'])
            ->assertForbidden();

        // Seller A cannot verify payment on Seller B's order
        $this->actingAs($this->seller)
            ->patch(route('seller.requests.payment', $foreignOrder), ['payment_status' => 'paid'])
            ->assertForbidden();
    }
}
