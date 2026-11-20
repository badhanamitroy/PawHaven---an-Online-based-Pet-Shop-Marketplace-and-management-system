<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Support\SignsOnlineOrderQuote;

class SalesAttributionTest extends TestCase
{
    use RefreshDatabase;
    use SignsOnlineOrderQuote;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
    }

    public function test_online_product_order_uses_product_company_and_branch_without_client_employee_attribution(): void
    {
        $branch = $this->createBranch('ONLINE-01');
        $owner = $this->createOwner();
        $this->actingAs($owner);
        $category = ProductCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Online Sales Category',
            'slug' => 'online-sales-category',
        ]);
        $product = Product::create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => 'Branch-specific Product',
            'slug' => 'branch-specific-product',
            'sku' => 'BRANCH-SALE-001',
            'price_minor' => 2500,
            'stock_quantity' => 3,
            'is_published' => true,
        ]);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $marketplaceSeller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($customer)->post(route('orders.request'), [
            'product_id' => $product->id,
            'submission_key' => (string) Str::uuid(),
            'quantity' => 1,
            ...$this->onlinePricingFields($product, 1, null, $customer),
            'customer_phone' => '+1 555 010 0100',
            'shipping_address' => '100 Test Road',
            'shipping_city' => 'Seattle',
            'branch_id' => 999999,
            'company_id' => 999999,
            'employee_seller_id' => $marketplaceSeller->id,
        ])->assertRedirect(route('dashboard'));

        $order = Order::query()->sole();
        $this->assertSame($this->company->id, $order->company_id);
        $this->assertSame($branch->id, $order->branch_id);
        $this->assertNull($order->employee_seller_id);
        $this->assertNull($order->seller_id);
        $this->assertSame(SalesChannel::Online, $order->sales_channel);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_employee_seller_order_is_attributed_to_the_authenticated_membership_branch(): void
    {
        $branch = $this->createBranch('EMPLOYEE-01');
        $owner = User::factory()->create(['role' => 'staff']);
        BusinessMembership::create([
            'user_id' => $owner->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $employeeSeller = User::factory()->create(['role' => 'staff']);
        BusinessMembership::create([
            'user_id' => $employeeSeller->id,
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'role' => 'seller',
            'is_active' => true,
        ]);
        $spoofedEmployee = User::factory()->create(['role' => 'staff']);

        $this->actingAs($employeeSeller);
        $order = Order::create([...$this->orderData(), 'employee_seller_id' => $spoofedEmployee->id]);

        $this->assertSame($employeeSeller->id, $order->employee_seller_id);
        $this->assertSame($this->company->id, $order->company_id);
        $this->assertSame($branch->id, $order->branch_id);
        $this->assertSame(SalesChannel::Online, $order->sales_channel);
        $this->assertNull($order->seller_id);
    }

    public function test_employee_seller_cannot_create_an_order_for_another_branch(): void
    {
        $assignedBranch = $this->createBranch('EMPLOYEE-02');
        $otherBranch = $this->createBranch('EMPLOYEE-03');
        $this->createOwner();
        $employeeSeller = User::factory()->create(['role' => 'staff']);
        BusinessMembership::create([
            'user_id' => $employeeSeller->id,
            'company_id' => $this->company->id,
            'branch_id' => $assignedBranch->id,
            'role' => 'seller',
            'is_active' => true,
        ]);

        $this->actingAs($employeeSeller);
        try {
            Order::create([...$this->orderData(), 'branch_id' => $otherBranch->id]);
            $this->fail('An Employee Seller must not create an order attributed to a different branch.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_marketplace_seller_cannot_create_an_employee_attributed_business_order(): void
    {
        $this->createOwner();
        $marketplaceSeller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($marketplaceSeller);
        try {
            Order::create([...$this->orderData(), 'employee_seller_id' => $marketplaceSeller->id]);
            $this->fail('A Marketplace Seller must not act as an Employee Seller.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('orders', 0);
        }
    }

    private function createBranch(string $code): Branch
    {
        return Branch::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Branch '.$code,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function createOwner(): User
    {
        $owner = User::factory()->create(['role' => 'staff']);
        BusinessMembership::create([
            'user_id' => $owner->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);

        return $owner;
    }

    /** @return array<string, mixed> */
    private function orderData(): array
    {
        return [
            'order_number' => 'ATTR-'.strtoupper(bin2hex(random_bytes(4))),
            'customer_name' => 'Test Customer',
            'customer_email' => 'sales-customer@example.test',
            'shipping_address' => '100 Test Road',
            'shipping_city' => 'Seattle',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 2500,
            'delivery_fee_minor' => 0,
            'total_minor' => 2500,
        ];
    }
}
