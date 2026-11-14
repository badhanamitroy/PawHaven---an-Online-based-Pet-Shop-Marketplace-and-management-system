<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class CustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBusinessMemberships;

    public function test_platform_admin_cannot_access_the_business_customer_directory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_shop_owner_customer_directory_only_lists_customers_with_company_orders(): void
    {
        $company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Customer Branch',
            'code' => 'CUSTOMER-01',
            'address' => '1 Example Road',
            'city' => 'Example City',
            'is_active' => true,
        ]);
        $owner = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->grantCompanyWideShopAccess($owner);

        $customer = User::factory()->create(['name' => 'Real Customer', 'role' => 'customer']);
        $legacyUser = User::factory()->create(['name' => 'Legacy User', 'role' => 'user']);
        $emptyRoleUser = User::factory()->create(['name' => 'Legacy Empty Role', 'role' => '']);
        $seller = User::factory()->create(['name' => 'Seller Account', 'role' => 'seller']);
        $staff = User::factory()->create(['name' => 'Staff Account', 'role' => 'staff']);

        $this->actingAs($owner);
        $this->createOrder($customer, $branch, 'CUSTOMER-ORDER-01');
        $this->createOrder($legacyUser, $branch, 'CUSTOMER-ORDER-02');
        $this->createOrder($emptyRoleUser, $branch, 'CUSTOMER-ORDER-03');

        $response = $this->get(route('admin.customers.index'));

        $response->assertOk()
            ->assertSee($customer->email)
            ->assertSee($legacyUser->email)
            ->assertSee($emptyRoleUser->email)
            ->assertDontSee($seller->email)
            ->assertDontSee($staff->email)
            ->assertSee('Total Customers:');

        $response->assertViewHas('totalCustomersCount', 3);
    }

    public function test_role_query_parameter_cannot_expand_the_shop_owner_customer_directory(): void
    {
        $company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Search Branch',
            'code' => 'SEARCH-01',
            'address' => '1 Example Road',
            'city' => 'Example City',
            'is_active' => true,
        ]);
        $owner = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->grantCompanyWideShopAccess($owner);
        $customer = User::factory()->create(['role' => 'customer']);
        $seller = User::factory()->create(['name' => 'Hidden Seller', 'role' => 'seller']);
        $this->actingAs($owner);
        $this->createOrder($customer, $branch, 'SEARCH-ORDER-01');

        $response = $this->get(route('admin.customers.index', ['role' => 'seller']));

        $response->assertOk()
            ->assertDontSee($seller->email)
            ->assertSee('No customers match the search criteria.');

        $response->assertViewHas('totalCustomersCount', 1);
    }

    private function createOrder(User $customer, Branch $branch, string $number): Order
    {
        return Order::create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'user_id' => $customer->id,
            'order_number' => $number,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'shipping_address' => '1 Example Road',
            'shipping_city' => 'Example City',
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal_minor' => 1000,
            'delivery_fee_minor' => 0,
            'total_minor' => 1000,
        ]);
    }
}
