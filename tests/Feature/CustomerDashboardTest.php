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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $seller;

    private PetCategory $category;

    private Breed $breed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create([
            'name' => 'John Customer',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->seller = User::factory()->create([
            'name' => 'Jane Seller',
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $this->category = PetCategory::create([
            'name' => 'Cats',
            'slug' => 'cats',
        ]);

        $this->breed = Breed::create([
            'pet_category_id' => $this->category->id,
            'name' => 'Siamese',
            'slug' => 'siamese',
        ]);
    }

    public function test_customer_sees_personalized_greeting_and_timeline(): void
    {
        $order = Order::create([
            'order_number' => 'CUST-ORD-001',
            'user_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '+15551234567',
            'shipping_address' => '100 Main St',
            'shipping_city' => 'Austin',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 40000,
            'delivery_fee_minor' => 0,
            'total_minor' => 40000,
        ]);

        $response = $this->actingAs($this->customer)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Hello, John Customer!');
        $response->assertSee("Welcome to PawHaven. Let's find your next best friend.", false);
        $response->assertSee('My Purchase Requests &amp; Orders', false);
        $response->assertSee('Safe Payment Instructions (Cash on Handover / COD)');
        $response->assertSee('CUST-ORD-001');
        $response->assertSee('Pending Review');
        $response->assertSee('Cancel Request');
    }

    public function test_customer_can_cancel_pending_order(): void
    {
        $order = Order::create([
            'order_number' => 'CUST-CANCEL-001',
            'user_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '+15551234567',
            'shipping_address' => '100 Main St',
            'shipping_city' => 'Austin',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 40000,
            'delivery_fee_minor' => 0,
            'total_minor' => 40000,
        ]);

        $response = $this->actingAs($this->customer)->post(route('orders.cancel', $order));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_customer_cannot_cancel_processing_order(): void
    {
        $order = Order::create([
            'order_number' => 'CUST-PROC-001',
            'user_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '+15551234567',
            'shipping_address' => '100 Main St',
            'shipping_city' => 'Austin',
            'status' => OrderStatus::Processing,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 40000,
            'delivery_fee_minor' => 0,
            'total_minor' => 40000,
        ]);

        $response = $this->actingAs($this->customer)->post(route('orders.cancel', $order));

        // Policy allows cancel only when status is Pending
        $response->assertForbidden();
        $this->assertEquals(OrderStatus::Processing, $order->fresh()->status);
    }

    public function test_customer_cannot_cancel_another_customers_order_idor(): void
    {
        $otherCustomer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $order = Order::create([
            'order_number' => 'FOREIGN-ORD-999',
            'user_id' => $otherCustomer->id,
            'seller_id' => $this->seller->id,
            'customer_name' => $otherCustomer->name,
            'customer_email' => $otherCustomer->email,
            'customer_phone' => '+15559998888',
            'shipping_address' => '200 Elm St',
            'shipping_city' => 'Dallas',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 30000,
            'delivery_fee_minor' => 0,
            'total_minor' => 30000,
        ]);

        $this->actingAs($this->customer)
            ->post(route('orders.cancel', $order))
            ->assertForbidden();

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_customer_can_toggle_favorite_and_see_it_on_dashboard(): void
    {
        $listing = PetListing::create([
            'seller_id' => $this->seller->id,
            'pet_category_id' => $this->category->id,
            'breed_id' => $this->breed->id,
            'title' => 'Adorable Siamese Kitten',
            'description' => 'Very playful and sweet kitten',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 35000,
            'location' => 'Austin, TX',
            'status' => PetListingStatus::Published,
        ]);

        // 1. Save pet
        $this->actingAs($this->customer)
            ->post(route('listings.favorite', $listing))
            ->assertRedirect();

        $this->assertTrue($this->customer->savedPets()->where('pet_listing_id', $listing->id)->exists());

        // 2. View on Customer Dashboard
        $response = $this->actingAs($this->customer)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Saved Pets (Favorites)');
        $response->assertSee('Adorable Siamese Kitten');

        // 3. Remove pet
        $this->actingAs($this->customer)
            ->post(route('listings.favorite', $listing))
            ->assertRedirect();

        $this->assertFalse($this->customer->fresh()->savedPets()->where('pet_listing_id', $listing->id)->exists());
    }

    public function test_customer_rehoming_posts_display_on_dashboard(): void
    {
        PetListing::create([
            'seller_id' => $this->customer->id,
            'pet_category_id' => $this->category->id,
            'breed_id' => $this->breed->id,
            'title' => 'Gentle Cat Seeking Quiet Home',
            'description' => 'Rehoming due to relocation',
            'listing_type' => PetListingType::Rehoming,
            'price_minor' => 0,
            'location' => 'Austin, TX',
            'status' => PetListingStatus::Published,
        ]);

        $response = $this->actingAs($this->customer)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('My Rehoming &amp; Adoption Posts', false);
        $response->assertSee('Gentle Cat Seeking Quiet Home');
    }
}
