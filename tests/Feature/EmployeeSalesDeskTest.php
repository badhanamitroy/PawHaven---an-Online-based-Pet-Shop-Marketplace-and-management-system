<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\EmployeeSalesDeskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Support\SignsOnlineOrderQuote;

class EmployeeSalesDeskTest extends TestCase
{
    use RefreshDatabase;
    use SignsOnlineOrderQuote;

    private Company $company;

    private User $shopOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
    }

    public function test_only_active_employee_sellers_can_open_the_sales_desk(): void
    {
        [$branch, $owner] = $this->createBranchAndOwner('DESK-ACCESS');
        $seller = $this->createEmployee($branch, 'seller');

        $this->actingAs($seller)->get(route('admin.sales-desk.index'))->assertOk();

        $inactive = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $inactive->id,
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'role' => 'seller',
            'is_active' => false,
        ]);
        $this->actingAs($inactive)->get(route('admin.sales-desk.index'))->assertForbidden();

        $manager = $this->createEmployee($branch, 'manager');
        $shopOwner = $owner;
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $marketplaceSeller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
        $unassignedStaff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);

        foreach ([$manager, $shopOwner, $admin, $marketplaceSeller, $unassignedStaff] as $unauthorized) {
            $this->actingAs($unauthorized)->get(route('admin.sales-desk.index'))->assertForbidden();
        }
    }

    public function test_sales_desk_creates_pending_pos_order_with_server_derived_employee_branch_and_stock(): void
    {
        [$branch] = $this->createBranchAndOwner('DESK-SALE');
        $otherBranch = $this->createBranch('DESK-OTHER');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-products');
        $product = $this->createProduct($branch, $category, 'Desk Product', 'DESK-PRODUCT', 10, 1200, 900);
        $otherProduct = $this->createProduct($otherBranch, $category, 'Other Branch Product', 'OTHER-DESK-PRODUCT', 10, 500);

        $this->actingAs($seller)->get(route('admin.sales-desk.index'))
            ->assertOk()->assertSee('Desk Product')->assertDontSee('Other Branch Product');
        $this->post(route('admin.sales-desk.cart.add', $product->id), ['quantity' => 2])->assertRedirect();
        $key = (string) session('employee_sales_desk.submission_key');

        $response = $this->post(route('admin.sales-desk.checkout'), [
            'submission_key' => $key,
            'customer_name' => 'Walk-in Customer',
            'customer_email' => 'walkin@example.test',
            'customer_phone' => '+1 555 010 0100',
            'confirmed' => '1',
            'employee_seller_id' => 999999,
            'company_id' => 999999,
            'branch_id' => $otherBranch->id,
            'sales_channel' => 'online',
            'seller_id' => 999999,
        ]);

        $response->assertSessionHasNoErrors();

        $order = Order::query()->withoutGlobalScopes()->where('sales_channel', 'pos')->sole();
        $response->assertRedirect(route('admin.sales-desk.receipt', $order));
        $this->assertSame($seller->id, $order->employee_seller_id);
        $this->assertSame($this->company->id, $order->company_id);
        $this->assertSame($branch->id, $order->branch_id);
        $this->assertSame(SalesChannel::PointOfSale, $order->sales_channel);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame('pos_pending', $order->payment_method);
        $this->assertSame(1800, $order->subtotal_minor);
        $this->assertSame(0, $order->delivery_fee_minor);
        $this->assertSame(1800, $order->total_minor);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, $order->items()->count());
        $this->assertNull($order->seller_id);
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'movement_type' => 'pos_reservation',
            'quantity_delta' => -2,
            'quantity_after' => 8,
            'actor_user_id' => $seller->id,
            'activity_branch_id' => $branch->id,
        ]);

        $this->post(route('admin.sales-desk.checkout'), [
            'submission_key' => $key,
            'customer_name' => 'Walk-in Customer',
            'customer_email' => 'walkin@example.test',
            'customer_phone' => '+1 555 010 0100',
            'confirmed' => '1',
        ])->assertSessionHasErrors('submission_key');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $product->fresh()->stock_quantity);

        $this->get(route('admin.sales-desk.receipt', $order))->assertOk()->assertSee($order->order_number)->assertSee('No payment transaction was submitted');
    }

    public function test_sales_desk_payment_claim_is_recorded_but_not_counted_until_manager_verifies(): void
    {
        [$branch] = $this->createBranchAndOwner('DESK-PAYMENT');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-payment-products');
        $product = $this->createProduct($branch, $category, 'Payment Product', 'PAYMENT-PRODUCT', 5, 5000);

        $this->actingAs($seller)->get(route('admin.sales-desk.index'))->assertOk();
        $this->post(route('admin.sales-desk.cart.add', $product->id), ['quantity' => 1])->assertRedirect();
        $key = (string) session('employee_sales_desk.submission_key');
        $this->post(route('admin.sales-desk.checkout'), [
            'submission_key' => $key,
            'customer_name' => 'Payment Customer',
            'customer_email' => 'payment-customer@example.test',
            'customer_phone' => '5550105',
            'payment_method' => 'bkash',
            'payment_amount' => '25.00',
            'payment_reference' => 'BKASH-UNIQUE-500',
            'confirmed' => '1',
        ])->assertRedirect();

        $order = Order::query()->withoutGlobalScopes()->where('sales_channel', 'pos')->sole();
        $payment = PaymentTransaction::query()->where('order_id', $order->id)->sole();
        $this->assertSame($seller->id, $payment->recorded_by_user_id);
        $this->assertSame('bkash', $payment->payment_method->value);
        $this->assertSame('pos', $payment->source->value);
        $this->assertSame('submitted', $payment->status->value);
        $this->assertSame(2500, $payment->amount_minor);
        $this->assertNull($payment->verified_by_user_id);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transaction_events', [
            'payment_transaction_id' => $payment->id,
            'actor_user_id' => $seller->id,
            'to_status' => 'submitted',
        ]);
    }

    public function test_invalid_pos_payment_rolls_back_the_order_and_stock_reservation(): void
    {
        [$branch] = $this->createBranchAndOwner('DESK-PAYMENT-ROLLBACK');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-payment-rollback-products');
        $product = $this->createProduct($branch, $category, 'Rollback Product', 'ROLLBACK-PRODUCT', 3, 1000);

        $this->actingAs($seller)->get(route('admin.sales-desk.index'))->assertOk();
        $this->post(route('admin.sales-desk.cart.add', $product->id), ['quantity' => 2])->assertRedirect();
        $key = (string) session('employee_sales_desk.submission_key');
        $this->post(route('admin.sales-desk.checkout'), [
            'submission_key' => $key,
            'customer_name' => 'Rollback Customer',
            'customer_email' => 'rollback@example.test',
            'customer_phone' => '5550106',
            'payment_method' => 'cash',
            'payment_amount' => '20.01',
            'confirmed' => '1',
        ])->assertSessionHasErrors('payment_amount');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_checkout_rejects_cross_branch_products_even_if_the_session_cart_is_tampered_with(): void
    {
        [$branch] = $this->createBranchAndOwner('DESK-SCOPE');
        $otherBranch = $this->createBranch('DESK-SCOPE-OTHER');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-scope-products');
        $product = $this->createProduct($otherBranch, $category, 'Private Branch Product', 'PRIVATE-BRANCH-PRODUCT', 4, 1000);
        $key = (string) Str::uuid();

        $this->actingAs($seller)->withSession([
            'employee_sales_desk.cart' => [$product->id => 1],
            'employee_sales_desk.submission_key' => $key,
        ])->post(route('admin.sales-desk.checkout'), [
            'submission_key' => $key,
            'customer_name' => 'Walk-in Customer',
            'customer_email' => 'branch-test@example.test',
            'customer_phone' => '5550100',
            'confirmed' => '1',
        ])->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_checkout_rejects_a_product_that_becomes_unpublished_after_being_added(): void
    {
        [$branch] = $this->createBranchAndOwner('DESK-UNPUBLISHED');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-unpublished-products');
        $product = $this->createProduct($branch, $category, 'Unpublished Product', 'UNPUBLISHED-PRODUCT', 3, 900);
        $key = (string) Str::uuid();
        $product->forceFill(['is_published' => false])->save();

        $this->actingAs($seller)->withSession([
            'employee_sales_desk.cart' => [$product->id => 1],
            'employee_sales_desk.submission_key' => $key,
        ])->post(route('admin.sales-desk.checkout'), [
            'submission_key' => $key,
            'customer_name' => 'Walk-in Customer',
            'customer_email' => 'unpublished@example.test',
            'customer_phone' => '5550100',
            'confirmed' => '1',
        ])->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_selected_verified_customer_account_is_used_instead_of_submitted_identity_fields(): void
    {
        [$branch] = $this->createBranchAndOwner('DESK-CUSTOMER');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-customer-products');
        $product = $this->createProduct($branch, $category, 'Customer Product', 'CUSTOMER-PRODUCT', 3, 1100);
        $customer = User::factory()->create([
            'name' => 'Verified Customer',
            'email' => 'verified-customer@example.test',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($seller)->get(route('admin.sales-desk.index'))->assertOk();
        $this->post(route('admin.sales-desk.cart.add', $product->id), ['quantity' => 1])->assertRedirect();
        $this->post(route('admin.sales-desk.checkout'), [
            'submission_key' => session('employee_sales_desk.submission_key'),
            'customer_id' => $customer->id,
            'customer_name' => 'Spoofed Name',
            'customer_email' => 'spoofed@example.test',
            'customer_phone' => '5550190',
            'confirmed' => '1',
        ])->assertRedirect();

        $order = Order::query()->withoutGlobalScopes()->sole();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('Verified Customer', $order->customer_name);
        $this->assertSame('verified-customer@example.test', $order->customer_email);
        $this->assertSame('5550190', $order->customer_phone);
    }

    public function test_invalid_quantity_and_stock_changes_are_revalidated_at_checkout(): void
    {
        [$branch] = $this->createBranchAndOwner('DESK-STOCK');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-stock-products');
        $product = $this->createProduct($branch, $category, 'Limited Stock Product', 'LIMITED-STOCK-PRODUCT', 5, 700);

        $this->actingAs($seller)->get(route('admin.sales-desk.index'))->assertOk();
        $this->post(route('admin.sales-desk.cart.add', $product->id), ['quantity' => -1])->assertSessionHasErrors('quantity');
        $this->post(route('admin.sales-desk.cart.add', $product->id), ['quantity' => 2])->assertRedirect();
        $product->forceFill(['stock_quantity' => 1])->save();
        $key = (string) session('employee_sales_desk.submission_key');

        $this->post(route('admin.sales-desk.checkout'), [
            'submission_key' => $key,
            'customer_name' => 'Stock Test',
            'customer_email' => 'stock@example.test',
            'customer_phone' => '5550101',
            'confirmed' => '1',
        ])->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock_quantity);
    }

    public function test_repeated_submission_key_returns_the_original_pos_order_without_reserving_stock_twice(): void
    {
        [$branch] = $this->createBranchAndOwner('DESK-IDEMPOTENCY');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-idempotency-products');
        $product = $this->createProduct($branch, $category, 'Idempotent Product', 'IDEMPOTENT-PRODUCT', 5, 800);
        $key = (string) Str::uuid();
        $cart = [$product->id => 2];
        $customer = [
            'customer_name' => 'Same Customer',
            'customer_email' => 'same@example.test',
            'customer_phone' => '5550102',
        ];

        $this->actingAs($seller);
        $salesDesk = app(EmployeeSalesDeskService::class);
        $first = $salesDesk->createPosOrder($seller, $cart, $customer, $key);
        $second = $salesDesk->createPosOrder($seller, $cart, $customer, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_online_customer_checkout_still_creates_an_online_unattributed_order(): void
    {
        [$branch, $owner] = $this->createBranchAndOwner('DESK-ONLINE');
        $seller = $this->createEmployee($branch, 'seller');
        $category = $this->createCategory('desk-online-products');
        $product = $this->createProduct($branch, $category, 'Online Checkout Product', 'ONLINE-CHECKOUT-PRODUCT', 3, 1500);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);

        $this->actingAs($owner);
        $this->actingAs($customer)->post(route('orders.request'), [
            'product_id' => $product->id,
            'submission_key' => (string) Str::uuid(),
            'quantity' => 1,
            ...$this->onlinePricingFields($product, 1, null, $customer),
            'customer_phone' => '5550103',
            'shipping_address' => '10 Online Road',
            'shipping_city' => 'Seattle',
            'employee_seller_id' => $seller->id,
            'sales_channel' => 'pos',
        ])->assertRedirect(route('dashboard'));

        $order = Order::query()->sole();
        $this->assertNull($order->employee_seller_id);
        $this->assertSame(SalesChannel::Online, $order->sales_channel);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    private function createBranchAndOwner(string $code): array
    {
        $branch = $this->createBranch($code);
        $owner = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $owner->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->shopOwner = $owner;

        return [$branch, $owner];
    }

    private function createBranch(string $code): Branch
    {
        return Branch::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Branch '.$code,
            'code' => $code,
            'city' => 'Seattle',
            'address' => '100 PawHaven Way',
            'is_active' => true,
        ]);
    }

    private function createEmployee(Branch $branch, string $role): User
    {
        $employee = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $employee->id,
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $employee;
    }

    private function createCategory(string $slug): ProductCategory
    {
        return ProductCategory::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Desk Category '.str_replace('-', ' ', $slug),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createProduct(
        Branch $branch,
        ProductCategory $category,
        string $name,
        string $sku,
        int $stock,
        int $price,
        ?int $salePrice = null,
    ): Product {
        $this->actingAs($this->shopOwner);

        return Product::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => $sku,
            'price_minor' => $price,
            'sale_price_minor' => $salePrice,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 0,
            'is_published' => true,
        ]);
    }
}
