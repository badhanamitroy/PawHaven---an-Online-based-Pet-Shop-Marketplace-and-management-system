<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductReturnStatus;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductReturn;
use App\Models\ProductReturnEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $this->branch = Branch::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Inventory Test Branch',
            'code' => 'INV-TEST',
            'city' => 'Dhaka',
            'address' => 'Inventory test address',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::query()->create([
            'user_id' => $this->owner->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::query()->create([
            'user_id' => $this->manager->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'role' => 'manager',
            'is_active' => true,
        ]);
    }

    public function test_buyer_return_requires_ownership_and_quantity_and_restock_requires_review_and_receipt(): void
    {
        [$buyer, $product, $order, $item] = $this->deliveredOrder(2, 5);
        $key = (string) Str::uuid();

        $this->actingAs($buyer)->post(route('orders.items.returns.store', [$order, $item]), [
            'return_submission_key' => $key,
            'return_quantity' => 3,
            'reason' => 'Package quantity is incorrect.',
        ])->assertSessionHasErrors('return_quantity');
        $this->assertDatabaseCount('product_returns', 0);

        $otherBuyer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($otherBuyer)->post(route('orders.items.returns.store', [$order, $item]), [
            'return_submission_key' => (string) Str::uuid(),
            'return_quantity' => 2,
            'reason' => 'Not my order.',
        ])->assertForbidden();
        $this->assertDatabaseCount('product_returns', 0);

        $payload = [
            'return_submission_key' => $key,
            'return_quantity' => 2,
            'reason' => 'Package quantity is incorrect.',
        ];
        $this->actingAs($buyer)->post(route('orders.items.returns.store', [$order, $item]), $payload)->assertRedirect();
        $this->post(route('orders.items.returns.store', [$order, $item]), $payload)->assertRedirect();
        $return = ProductReturn::query()->sole();
        $this->assertSame(ProductReturnStatus::Requested, $return->status);
        $this->assertSame(1, ProductReturnEvent::query()->count());
        $this->assertSame(5, $product->fresh()->stock_quantity);

        $otherBranch = $this->createBranch('INV-OTHER');
        $otherManager = $this->createManager($otherBranch);
        $this->actingAs($otherManager)->post(route('admin.returns.approve', $return), [
            'review_note' => 'Reviewed.',
        ])->assertForbidden();

        $this->actingAs($this->manager)->post(route('admin.returns.approve', $return), [
            'review_note' => 'Eligible for physical receipt.',
        ])->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame(ProductReturnStatus::Approved, $return->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock_quantity, 'Approval alone must not change available stock.');

        $receiptKey = (string) Str::uuid();
        $receipt = [
            'receipt_submission_key' => $receiptKey,
            'received_quantity' => 2,
            'resellable_quantity' => 1,
            'damaged_quantity' => 1,
            'receipt_note' => 'One sealed unit can be resold; one unit was damaged.',
        ];
        $this->post(route('admin.returns.receive', $return), $receipt)
            ->assertRedirect(route('admin.orders.show', $order));
        $this->post(route('admin.returns.receive', $return), $receipt)
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(ProductReturnStatus::Received, $return->fresh()->status);
        $this->assertSame(6, $product->fresh()->stock_quantity, 'Only the resellable unit is restored.');
        $this->assertDatabaseHas('inventory_movements', [
            'product_return_id' => $return->id,
            'movement_type' => InventoryMovementType::ReturnReceived->value,
            'quantity_delta' => 0,
            'quantity_after' => 5,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_return_id' => $return->id,
            'movement_type' => InventoryMovementType::ReturnDamaged->value,
            'quantity_delta' => 0,
            'quantity_after' => 5,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_return_id' => $return->id,
            'movement_type' => InventoryMovementType::ReturnRestock->value,
            'quantity_delta' => 1,
            'quantity_after' => 6,
        ]);
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->assertSame(3, ProductReturnEvent::query()->count());
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertDatabaseCount('payment_refund_transactions', 0);
    }

    public function test_approved_return_cannot_restore_damaged_units_or_receive_too_many_units(): void
    {
        [$buyer, $product, $order, $item] = $this->deliveredOrder(1, 4);
        $this->actingAs($buyer)->post(route('orders.items.returns.store', [$order, $item]), [
            'return_submission_key' => (string) Str::uuid(),
            'return_quantity' => 1,
            'reason' => 'Item arrived damaged.',
        ])->assertRedirect();
        $return = ProductReturn::query()->sole();
        $this->actingAs($this->owner)->post(route('admin.returns.approve', $return), [
            'review_note' => 'Approved for inspection.',
        ])->assertRedirect();

        $this->actingAs($this->owner)->post(route('admin.returns.receive', $return), [
            'receipt_submission_key' => (string) Str::uuid(),
            'received_quantity' => 2,
            'resellable_quantity' => 0,
            'damaged_quantity' => 2,
            'receipt_note' => 'Quantity exceeds the approved request.',
        ])->assertSessionHasErrors('received_quantity');
        $this->assertSame(ProductReturnStatus::Approved, $return->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock_quantity);

        $this->post(route('admin.returns.receive', $return), [
            'receipt_submission_key' => (string) Str::uuid(),
            'received_quantity' => 1,
            'resellable_quantity' => 0,
            'damaged_quantity' => 1,
            'receipt_note' => 'The returned item is damaged and cannot be resold.',
        ])->assertRedirect();
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_return_id' => $return->id,
            'movement_type' => InventoryMovementType::ReturnDamaged->value,
            'quantity_delta' => 0,
        ]);
        $this->assertDatabaseMissing('inventory_movements', ['movement_type' => 'return_restock']);
    }

    public function test_inventory_report_is_scoped_to_manager_branch_and_owner_can_see_company_inventory(): void
    {
        $otherBranch = $this->createBranch('INV-SECOND');
        $category = $this->category();
        $branchProduct = $this->product($category, $this->branch, 'Assigned Branch Stock', 'INV-BRANCH-A', 7);
        $otherProduct = $this->product($category, $otherBranch, 'Other Branch Stock', 'INV-BRANCH-B', 9);

        $this->actingAs($this->owner);
        app(\App\Services\InventoryMovementService::class)->recordOpening($branchProduct, $this->owner);
        app(\App\Services\InventoryMovementService::class)->recordOpening($otherProduct, $this->owner);

        $this->actingAs($this->manager)->get(route('admin.inventory.movements'))
            ->assertOk()->assertSee('Assigned Branch Stock')->assertDontSee('Other Branch Stock');
        $this->get(route('admin.inventory.index'))->assertOk()->assertSee('Inventory Test Branch');
        $this->get(route('admin.inventory.movements', ['branch_id' => $otherBranch->id]))->assertForbidden();
        $this->get(route('admin.inventory.movements', ['product_id' => $otherProduct->id]))->assertForbidden();
        $this->get(route('admin.inventory.movements', ['product_id' => $branchProduct->id, 'movement_type' => 'opening']))
            ->assertOk()->assertSee('Assigned Branch Stock')->assertDontSee('Other Branch Stock');

        $this->actingAs($this->owner)->get(route('admin.inventory.movements'))
            ->assertOk()->assertSee('Assigned Branch Stock')->assertSee('Other Branch Stock');
        $this->get(route('admin.inventory.movements', [
            'product_id' => $branchProduct->id,
            'movement_type' => 'opening',
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk()->assertViewHas('movements', fn ($movements): bool =>
            $movements->total() === 1 && (int) $movements->first()->product_id === (int) $branchProduct->id
        );

        $this->actingAs($this->manager)->patch(route('admin.inventory.update', $branchProduct), [
            'stock_quantity' => 8,
            'low_stock_threshold' => 0,
            'stock_adjustment_reason' => 'Unauthorized adjustment attempt.',
            'adjustment_key' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertSame(7, $branchProduct->fresh()->stock_quantity);

        $this->actingAs(User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]))
            ->get(route('admin.inventory.movements'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]))
            ->get(route('admin.inventory.movements'))->assertForbidden();
    }

    private function deliveredOrder(int $itemQuantity, int $stock): array
    {
        $buyer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $category = $this->category();
        $product = $this->product($category, $this->branch, 'Returnable Product', 'RETURN-'.strtoupper(Str::random(8)), $stock);

        $this->actingAs($buyer);
        $order = Order::query()->create([
            'company_id' => $this->company->id,
            'order_number' => 'PH-RET-'.strtoupper(Str::random(8)),
            'user_id' => $buyer->id,
            'customer_name' => $buyer->name,
            'customer_email' => $buyer->email,
            'customer_phone' => '01700000000',
            'shipping_address' => 'Test delivery address',
            'shipping_city' => 'Dhaka',
            'status' => OrderStatus::Delivered,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 2000 * $itemQuantity,
            'delivery_fee_minor' => 0,
            'total_minor' => 2000 * $itemQuantity,
            'currency' => $product->currency,
        ]);
        $order->forceFill(['branch_id' => $this->branch->id])->save();
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price_minor' => 2000,
            'quantity' => $itemQuantity,
            'subtotal_minor' => 2000 * $itemQuantity,
        ]);

        return [$buyer, $product, $order, $item];
    }

    private function category(): ProductCategory
    {
        $this->actingAs($this->owner);

        return ProductCategory::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Inventory Category '.Str::random(8),
            'slug' => 'inventory-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
    }

    private function product(ProductCategory $category, Branch $branch, string $name, string $sku, int $stock): Product
    {
        $this->actingAs($this->owner);

        return Product::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'sku' => $sku,
            'price_minor' => 2000,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 0,
            'is_published' => true,
        ]);
    }

    private function createBranch(string $code): Branch
    {
        $this->actingAs($this->owner);

        return Branch::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Branch '.$code,
            'code' => $code,
            'city' => 'Dhaka',
            'address' => 'Test branch address',
            'is_active' => true,
        ]);
    }

    private function createManager(Branch $branch): User
    {
        $manager = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::query()->create([
            'user_id' => $manager->id,
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'role' => 'manager',
            'is_active' => true,
        ]);

        return $manager;
    }
}
