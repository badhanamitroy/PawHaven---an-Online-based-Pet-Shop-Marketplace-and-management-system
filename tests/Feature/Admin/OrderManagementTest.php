<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use CreatesBusinessMemberships;
    use RefreshDatabase;

    public function test_shop_owner_can_follow_permitted_order_status_lifecycle(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($staff);

        $order = Order::create([
            'order_number' => 'PH-2026-ORD100',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'shipping_address' => '456 Elm St',
            'shipping_city' => 'Portland',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 3000,
            'delivery_fee_minor' => 500,
            'total_minor' => 3500,
        ]);

        // 1. Invalid jump: Pending directly to Delivered
        $invalidResponse = $this->actingAs($staff)->patch(route('admin.orders.update-status', $order), [
            'status' => 'delivered',
        ]);
        $invalidResponse->assertSessionHas('error');
        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);

        // 2. Valid transition: Pending to Processing
        $validResponse = $this->actingAs($staff)->patch(route('admin.orders.update-status', $order), [
            'status' => 'processing',
        ]);
        $validResponse->assertRedirect(route('admin.orders.show', $order));
        $validResponse->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(OrderStatus::Processing, $order->status);

        // 3. Valid transition: Processing to Shipped
        $shippedResponse = $this->actingAs($staff)->patch(route('admin.orders.update-status', $order), [
            'status' => 'shipped',
        ]);
        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);

        // 4. Valid transition: Shipped to Delivered (and COD marked as Paid)
        $deliveredResponse = $this->actingAs($staff)->patch(route('admin.orders.update-status', $order), [
            'status' => 'delivered',
        ]);
        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);

        // 5. Audit log recorded
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'order.status_update',
            'user_id' => $staff->id,
        ]);
    }

    public function test_cancellation_requires_a_reason(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($staff);

        $order = Order::create([
            'order_number' => 'PH-2026-ORD101',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
            'shipping_address' => '789 Oak Ave',
            'shipping_city' => 'Spokane',
            'status' => OrderStatus::Pending,
            'payment_method' => 'card',
            'payment_status' => PaymentStatus::Paid,
            'subtotal_minor' => 4500,
            'delivery_fee_minor' => 0,
            'total_minor' => 4500,
        ]);

        $missingReasonResponse = $this->actingAs($staff)->patch(route('admin.orders.update-status', $order), [
            'status' => 'cancelled',
            'cancelled_reason' => '',
        ]);
        $missingReasonResponse->assertSessionHasErrors(['cancelled_reason']);

        $validCancelResponse = $this->actingAs($staff)->patch(route('admin.orders.update-status', $order), [
            'status' => 'cancelled',
            'cancelled_reason' => 'Customer requested order cancellation before packaging.',
        ]);
        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('Customer requested order cancellation before packaging.', $order->cancelled_reason);
    }

    public function test_shop_owner_cancellation_restores_inventory_reserved_by_a_new_order(): void
    {
        $owner = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($owner);
        $category = ProductCategory::create([
            'name' => 'Food',
            'slug' => 'admin-cancel-food',
        ]);
        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Reserved Food',
            'slug' => 'reserved-food',
            'sku' => 'RESERVED-FOOD',
            'price_minor' => 1000,
            'stock_quantity' => 3,
        ]);
        $order = Order::create([
            'order_number' => 'PH-2026-ORD102',
            'user_id' => $owner->id,
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'shipping_address' => '123 Test Street',
            'shipping_city' => 'Seattle',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 2000,
            'delivery_fee_minor' => 0,
            'total_minor' => 2000,
            'inventory_reserved_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price_minor' => 1000,
            'quantity' => 2,
            'subtotal_minor' => 2000,
        ]);

        $this->actingAs($owner)->patch(route('admin.orders.update-status', $order), [
            'status' => 'cancelled',
            'cancelled_reason' => 'Customer cancellation.',
        ])->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->inventory_reserved_at);
    }

    public function test_shop_owner_cancelling_a_legacy_order_does_not_inflate_inventory(): void
    {
        $owner = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($owner);
        $category = ProductCategory::create([
            'name' => 'Food',
            'slug' => 'legacy-cancel-food',
        ]);
        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Legacy Food',
            'slug' => 'legacy-food',
            'sku' => 'LEGACY-FOOD',
            'price_minor' => 1000,
            'stock_quantity' => 3,
        ]);
        $order = Order::create([
            'order_number' => 'PH-2026-ORD103',
            'customer_name' => 'Historical Customer',
            'customer_email' => 'historical@example.com',
            'shipping_address' => '123 Test Street',
            'shipping_city' => 'Seattle',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 1000,
            'delivery_fee_minor' => 0,
            'total_minor' => 1000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price_minor' => 1000,
            'quantity' => 1,
            'subtotal_minor' => 1000,
        ]);

        $this->actingAs($owner)->patch(route('admin.orders.update-status', $order), [
            'status' => 'cancelled',
            'cancelled_reason' => 'Historical order cancelled.',
        ]);

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->inventory_reserved_at);
    }
}
