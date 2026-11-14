<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use CreatesBusinessMemberships;
    use RefreshDatabase;

    public function test_shop_owner_can_create_product_with_price_conversion(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($staff);

        $category = ProductCategory::create([
            'name' => 'Premium Food',
            'slug' => 'premium-food',
            'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->post(route('admin.products.store'), [
            'name' => 'Organic Freeze-Dried Beef Bites',
            'product_category_id' => $category->id,
            'sku' => 'treat-beef-001',
            'description' => 'Single ingredient raw pasture-raised beef bites.',
            'price' => '24.50',
            'sale_price' => '19.99',
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
            'is_published' => '1',
            'is_featured' => '1',
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'TREAT-BEEF-001',
            'price_minor' => 2450,
            'sale_price_minor' => 1999,
            'stock_quantity' => 50,
            'low_stock_threshold' => 10,
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'product.create',
            'user_id' => $staff->id,
        ]);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($staff);

        $category = ProductCategory::create([
            'name' => 'Toys',
            'slug' => 'toys',
        ]);

        Product::create([
            'product_category_id' => $category->id,
            'name' => 'Chew Bone',
            'slug' => 'chew-bone',
            'sku' => 'TOY-BONE-001',
            'price_minor' => 1000,
            'stock_quantity' => 15,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->actingAs($staff)->post(route('admin.products.store'), [
            'name' => 'Chew Bone Deluxe',
            'product_category_id' => $category->id,
            'sku' => 'TOY-BONE-001', // duplicate
            'price' => '12.00',
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
        ]);

        $response->assertSessionHasErrors(['sku']);
    }

    public function test_shop_owner_can_adjust_inventory_and_logs_audit(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($staff);

        $category = ProductCategory::create([
            'name' => 'Toys',
            'slug' => 'toys',
        ]);

        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Rope Toy',
            'slug' => 'rope-toy',
            'sku' => 'TOY-ROPE-001',
            'price_minor' => 800,
            'stock_quantity' => 4,
            'low_stock_threshold' => 5,
        ]);

        $this->assertTrue($product->isLowStock());

        $response = $this->actingAs($staff)->patch(route('admin.inventory.update', $product), [
            'stock_quantity' => 25,
            'low_stock_threshold' => 5,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));

        $product->refresh();
        $this->assertSame(25, $product->stock_quantity);
        $this->assertFalse($product->isLowStock());

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'inventory.adjust',
            'user_id' => $staff->id,
        ]);
    }

    public function test_cannot_delete_product_with_associated_order_items(): void
    {
        $owner = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($owner);

        $category = ProductCategory::create([
            'name' => 'Grooming',
            'slug' => 'grooming',
        ]);

        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Oatmeal Shampoo',
            'slug' => 'oatmeal-shampoo',
            'sku' => 'GROOM-SHAMP-001',
            'price_minor' => 1500,
            'stock_quantity' => 10,
            'low_stock_threshold' => 2,
        ]);

        $order = Order::create([
            'order_number' => 'PH-2026-TEST01',
            'user_id' => $owner->id,
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@test.com',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'Seattle',
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal_minor' => 1500,
            'delivery_fee_minor' => 500,
            'total_minor' => 2000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price_minor' => 1500,
            'quantity' => 1,
            'subtotal_minor' => 1500,
        ]);

        $response = $this->actingAs($owner)->delete(route('admin.products.destroy', $product));

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
