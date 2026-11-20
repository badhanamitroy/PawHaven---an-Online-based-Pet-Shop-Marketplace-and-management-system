<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Facades\Settings;
use App\Models\Breed;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndIntegrationJourneysTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private User $customer;

    private PetCategory $dogsCategory;

    private Breed $labradorBreed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->seller = User::factory()->create([
            'name' => 'Verified Seller',
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $this->customer = User::factory()->create([
            'name' => 'Happy Customer',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->dogsCategory = PetCategory::create([
            'name' => 'Dogs',
            'slug' => 'dogs',
        ]);

        $this->labradorBreed = Breed::create([
            'pet_category_id' => $this->dogsCategory->id,
            'name' => 'Labrador Retriever',
            'slug' => 'labrador-retriever',
        ]);
    }

    /**
     * Journey 1: Website Management
     * Admin logs in -> edits Homepage content -> saves draft -> previews draft -> publishes -> public visitor sees published version.
     */
    public function test_journey_1_website_management_draft_preview_and_publish(): void
    {
        Settings::set('homepage.hero_headline', 'Original Live Headline');

        // 1. Admin saves draft headline
        $this->actingAs($this->admin)->post(route('admin.homepage.draft'), [
            'hero_headline' => 'Special Draft Headline For Pets',
            'hero_supporting' => 'Discover ethical companions safely.',
            'cta_primary_label' => 'Explore Pets',
            'cta_primary_url' => '#find-pets',
            'cta_secondary_label' => 'Shop Supplies',
            'cta_secondary_url' => '#pet-shop',
            'cta_adoption_label' => 'Explore Adoption',
            'cta_adoption_url' => '#adoption-rescue',
            'announcement_enabled' => '1',
            'announcement_text' => 'Winter Pet Adoption Drive is Live!',
            'section_categories_visible' => '1',
            'section_marketplace_visible' => '1',
            'section_shop_visible' => '1',
            'section_adoption_visible' => '1',
            'section_breeds_visible' => '1',
        ])->assertRedirect(route('admin.homepage.index'));

        // 2. Public visitor DOES NOT see draft
        $publicResponse = $this->get(route('public.home'));
        $publicResponse->assertOk();
        $publicResponse->assertSee('Original Live Headline');
        $publicResponse->assertDontSee('Special Draft Headline For Pets');
        $publicResponse->assertDontSee('Winter Pet Adoption Drive is Live!');

        // 3. Admin views draft preview with preview banner
        $previewResponse = $this->actingAs($this->admin)->get(route('admin.homepage.preview'));
        $previewResponse->assertOk();
        $previewResponse->assertSee('DRAFT PREVIEW MODE', false);
        $previewResponse->assertSee('Special Draft Headline For Pets');

        // 4. Admin publishes live
        $this->actingAs($this->admin)->post(route('admin.homepage.publish'))
            ->assertRedirect(route('admin.homepage.index'));

        // 5. Public visitor now sees the published version
        $updatedPublic = $this->get(route('public.home'));
        $updatedPublic->assertOk();
        $updatedPublic->assertSee('Special Draft Headline For Pets');
        $updatedPublic->assertSee('Winter Pet Adoption Drive is Live!');
    }

    /**
     * Journey 2: Customer Purchase Request
     * Guest browses public listings -> views listing -> logs in -> submits purchase request -> views on Customer Dashboard.
     */
    public function test_journey_2_customer_browses_and_submits_purchase_request(): void
    {
        $listing = PetListing::create([
            'seller_id' => $this->seller->id,
            'pet_category_id' => $this->dogsCategory->id,
            'breed_id' => $this->labradorBreed->id,
            'title' => 'Golden Labrador Puppy',
            'description' => 'Healthy, microchipped, calm family dog.',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 120000,
            'location' => 'Austin, TX',
            'status' => PetListingStatus::Published,
        ]);

        // 1. Guest views listing details
        $guestView = $this->get(route('listings.show', $listing));
        $guestView->assertOk();
        $guestView->assertSee('Golden Labrador Puppy');
        $guestView->assertSee('Log In to Submit Purchase Request');

        // 2. Customer submits purchase request
        $purchaseResponse = $this->actingAs($this->customer)->post(route('listings.purchase', $listing), [
            'customer_phone' => '+1 (555) 345-6789',
            'shipping_city' => 'Austin, TX',
            'shipping_address' => '742 Evergreen Terrace',
            'notes' => 'We have a fenced yard and two kids excited to meet the puppy.',
        ]);

        $purchaseResponse->assertRedirect(route('dashboard'));
        $purchaseResponse->assertSessionHas('success');

        // 3. Verify order persistence
        $order = Order::where('user_id', $this->customer->id)->where('seller_id', $this->seller->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(OrderStatus::Pending, $order->status);
        $this->assertEquals(PaymentStatus::Pending, $order->payment_status);
        $this->assertEquals(120000, $order->total_minor);

        // 4. Customer views request on dashboard with safe payment instructions
        $dashResponse = $this->actingAs($this->customer)->get(route('dashboard'));
        $dashResponse->assertOk();
        $dashResponse->assertSee($order->order_number);
        $dashResponse->assertSee('Safe Payment Instructions (Cash on Handover / COD)');
        $dashResponse->assertSee('Cancel Request');
    }

    /**
     * Journey 3: Seller Processing & Payment Verification
     * Seller logs in -> views incoming request -> accepts -> marks shipped -> confirms handover & verified payment.
     */
    public function test_journey_3_seller_manages_request_lifecycle_and_payment(): void
    {
        $listing = PetListing::create([
            'seller_id' => $this->seller->id,
            'pet_category_id' => $this->dogsCategory->id,
            'breed_id' => $this->labradorBreed->id,
            'title' => 'Family Pup',
            'description' => 'Great temperament',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 80000,
            'location' => 'Dallas, TX',
            'status' => PetListingStatus::Published,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-JRN-300',
            'user_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'pet_listing_id' => $listing->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '+15554443333',
            'shipping_address' => '500 Pine St',
            'shipping_city' => 'Dallas',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 80000,
            'delivery_fee_minor' => 0,
            'total_minor' => 80000,
        ]);

        // 1. Seller views request in seller dashboard
        $sellerDash = $this->actingAs($this->seller)->get(route('seller.dashboard'));
        $sellerDash->assertOk();
        $sellerDash->assertSee('ORD-JRN-300');

        // 2. Seller accepts request (Pending -> Processing)
        $this->actingAs($this->seller)->patch(route('seller.requests.status', $order), [
            'status' => 'processing',
        ])->assertRedirect();
        $this->assertEquals(OrderStatus::Processing, $order->fresh()->status);

        // 3. Customer sees Processing status
        $this->actingAs($this->customer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Accepted &amp; Processing', false);

        // 4. Seller marks out for delivery / handover (Processing -> Shipped)
        $this->actingAs($this->seller)->patch(route('seller.requests.status', $order), [
            'status' => 'shipped',
        ])->assertRedirect();
        $this->assertEquals(OrderStatus::Shipped, $order->fresh()->status);

        // 5. Seller confirms final handover (Shipped -> Delivered); delivery is not payment evidence.
        $this->actingAs($this->seller)->patch(route('seller.requests.status', $order), [
            'status' => 'delivered',
        ])->assertRedirect();
        $this->assertEquals(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Pending, $order->fresh()->payment_status);

        // 6. Seller records payment receipt reference
        $this->actingAs($this->seller)->patch(route('seller.requests.payment', $order), [
            'payment_status' => 'paid',
            'payment_reference' => 'COD-CASH-CONFIRMED-800',
        ])->assertRedirect();
        $this->assertEquals('COD-CASH-CONFIRMED-800', $order->fresh()->payment_reference);
        $this->assertSame('verified', PaymentTransaction::query()->where('order_id', $order->id)->sole()->status->value);
    }

    /**
     * Journey 4: Adoption / Rescue Posting & Moderation
     * Customer creates rehoming post -> submitted to moderation queue -> admin approves -> post becomes publicly visible.
     */
    public function test_journey_4_adoption_rehoming_lifecycle_and_admin_moderation(): void
    {
        // 1. Customer creates an adoption listing (pending review)
        $adoptionListing = PetListing::create([
            'seller_id' => $this->customer->id,
            'pet_category_id' => $this->dogsCategory->id,
            'breed_id' => $this->labradorBreed->id,
            'title' => 'Gentle Rescue Looking For Garden Home',
            'description' => 'Calm adult rescue dog, vaccinated and microchipped.',
            'listing_type' => PetListingType::Adoption,
            'price_minor' => 0,
            'location' => 'Austin, TX',
            'status' => PetListingStatus::PendingReview,
        ]);

        // 2. Unapproved post is hidden from public browse
        $this->get(route('listings.index'))
            ->assertOk()
            ->assertDontSee('Gentle Rescue Looking For Garden Home');

        // 3. Admin visits moderation queue
        $modResponse = $this->actingAs($this->admin)->get(route('admin.marketplace.moderation.index'));
        $modResponse->assertOk();
        $modResponse->assertSee('Gentle Rescue Looking For Garden Home');

        // 4. Admin approves post
        $this->actingAs($this->admin)->post(route('admin.marketplace.moderation.approve', $adoptionListing), [
            'note' => 'Meets ethical rescue guidelines.',
        ])->assertRedirect();

        $this->assertEquals(PetListingStatus::Published, $adoptionListing->fresh()->status);

        // 5. Post is now live on public adoption feed
        $this->get(route('listings.index', ['type' => 'adoption']))
            ->assertOk()
            ->assertSee('Gentle Rescue Looking For Garden Home')
            ->assertSee('No Sale Fee');
    }

    /**
     * Journey 5: Role-based Access Control and Strict Server-Side Denial (IDOR Prevention)
     */
    public function test_journey_5_role_isolation_and_idor_prevention(): void
    {
        $otherSeller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $privateOrder = Order::create([
            'order_number' => 'ORD-SECRET-999',
            'user_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'customer_phone' => '+15559990000',
            'shipping_address' => 'Secret Vault',
            'shipping_city' => 'Austin',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 10000,
            'delivery_fee_minor' => 0,
            'total_minor' => 10000,
        ]);

        // 1. Customer cannot access Admin Dashboard
        $this->actingAs($this->customer)->get(route('admin.dashboard'))->assertForbidden();

        // 2. Customer cannot access Seller Dashboard
        $this->actingAs($this->customer)->get(route('seller.dashboard'))->assertForbidden();

        // 3. Seller cannot access Admin Dashboard
        $this->actingAs($this->seller)->get(route('admin.dashboard'))->assertForbidden();

        // 4. Seller B cannot access Seller A's order (IDOR)
        $this->actingAs($otherSeller)->get(route('seller.requests.show', $privateOrder))->assertForbidden();

        // 5. Seller B cannot update Seller A's order status (IDOR)
        $this->actingAs($otherSeller)->patch(route('seller.requests.status', $privateOrder), [
            'status' => 'cancelled',
        ])->assertForbidden();

        $publicListing = PetListing::create([
            'seller_id' => $this->seller->id,
            'pet_category_id' => $this->dogsCategory->id,
            'breed_id' => $this->labradorBreed->id,
            'title' => 'Protected Listing',
            'description' => 'Cannot purchase without login',
            'listing_type' => PetListingType::Sale,
            'price_minor' => 10000,
            'location' => 'Austin',
            'status' => PetListingStatus::Published,
        ]);

        // 6. Unauthenticated visitor cannot submit purchase request
        $this->post(route('logout'));
        $this->post(route('listings.purchase', $publicListing), [])->assertRedirect(route('login'));
    }
}
