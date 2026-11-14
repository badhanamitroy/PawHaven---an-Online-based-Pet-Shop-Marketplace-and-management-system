<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\LoginOtpMail;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use CreatesBusinessMemberships;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_admin(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login'));

        $brandResponse = $this->get(route('admin.brand.index'));
        $brandResponse->assertRedirect(route('admin.login'));
    }

    public function test_customer_receives_forbidden_when_accessing_admin(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer)->get(route('admin.dashboard'));
        $response->assertForbidden();

        $brandResponse = $this->actingAs($customer)->get(route('admin.brand.index'));
        $brandResponse->assertForbidden();
    }

    public function test_shop_owner_can_access_business_operations_but_not_platform_governance(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($staff);

        // Can access operational routes
        $dashboardResponse = $this->actingAs($staff)->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();

        $productsResponse = $this->actingAs($staff)->get(route('admin.products.index'));
        $productsResponse->assertOk();

        $ordersResponse = $this->actingAs($staff)->get(route('admin.orders.index'));
        $ordersResponse->assertOk();

        // Cannot access admin-only governance routes
        $brandResponse = $this->actingAs($staff)->get(route('admin.brand.index'));
        $brandResponse->assertForbidden();

        $usersResponse = $this->actingAs($staff)->get(route('admin.users.index'));
        $usersResponse->assertForbidden();

        $featuresResponse = $this->actingAs($staff)->get(route('admin.settings.features'));
        $featuresResponse->assertForbidden();
    }

    public function test_seller_is_denied_admin_operations_and_governance(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        // Seller has access to their own seller dashboard
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();

        // Seller is denied administrative operational and governance routes
        $this->actingAs($seller)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.inventory.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.brand.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.settings.features'))->assertForbidden();
    }

    public function test_platform_admin_access_is_distinct_from_shop_business_access(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.brand.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.homepage.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.inventory.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.marketplace.listings.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.marketplace.moderation.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.marketplace.categories.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.community.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.breeds.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit-logs.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.shop'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.settings.features'))->assertOk();
    }

    public function test_generic_login_rejects_admin_and_requires_the_dedicated_otp_flow(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'SecurePassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_generic_login_rejects_staff_and_requires_the_dedicated_otp_flow(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $staff->email,
            'password' => 'SecurePassword123!',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_seller_login_redirects_to_seller_dashboard(): void
    {
        Mail::fake();
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $seller->email,
            'password' => 'SecurePassword123!',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
        $this->post(route('login.otp.verify'), ['code' => $this->sentOtpFor($seller)])
            ->assertRedirect(route('seller.dashboard'));
    }

    public function test_customer_login_redirects_to_customer_dashboard(): void
    {
        Mail::fake();
        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'SecurePassword123!',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
        $this->post(route('login.otp.verify'), ['code' => $this->sentOtpFor($customer)])
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_accessing_dashboard_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_staff_accessing_dashboard_is_redirected_to_admin_dashboard(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($staff)->get(route('dashboard'));
        $response->assertRedirect(route('admin.dashboard'));
    }

    private function sentOtpFor(User $user): string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user, &$code): bool {
            $code = $mail->otp;

            return $mail->hasTo($user->email);
        });

        $this->assertIsString($code);

        return $code;
    }

    public function test_seller_accessing_dashboard_is_redirected_to_seller_dashboard(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($seller)->get(route('dashboard'));
        $response->assertRedirect(route('seller.dashboard'));
    }

    public function test_customer_accessing_dashboard_sees_pawhaven_customer_dashboard(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('PawHaven Customer');
        $response->assertSee('My Pet Listings');
        $response->assertSee('Shop Orders');
        $response->assertDontSee('https://github.com/laravel/livewire-starter-kit');
        $response->assertDontSee('https://laravel.com/docs/starter-kits#livewire');
    }

    public function test_unrecognized_role_cannot_fall_through_to_customer_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
        DB::table('users')->where('id', $user->id)->update(['role' => 'unrecognized']);
        $user->refresh();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_customer_can_submit_buy_request(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $category = ProductCategory::create([
            'name' => 'Food',
            'slug' => 'food-test',
        ]);

        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Test Pet Food',
            'slug' => 'test-pet-food',
            'sku' => 'TEST-FOOD-001',
            'price_minor' => 2500,
            'stock_quantity' => 10,
            'is_published' => true,
        ]);

        $response = $this->actingAs($customer)->post(route('orders.request'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'customer_phone' => '+1 555 123 4567',
            'shipping_address' => '123 Main St, Apt 2',
            'shipping_city' => 'Seattle',
            'notes' => 'Ring the bell',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => '+1 555 123 4567',
            'shipping_address' => '123 Main St, Apt 2',
            'shipping_city' => 'Seattle',
            'total_minor' => 5000,
            'status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_guest_cannot_submit_buy_request(): void
    {
        $response = $this->post(route('orders.request'), [
            'product_id' => 1,
            'quantity' => 1,
            'customer_phone' => '+1 555 123 4567',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'Seattle',
        ]);

        $response->assertRedirect(route('login'));
    }
}
