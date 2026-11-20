<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Tests\Support\SignsOnlineOrderQuote;

class ProductPurchaseTest extends TestCase
{
    use RefreshDatabase;
    use SignsOnlineOrderQuote;

    public function test_unpublished_product_cannot_be_purchased(): void
    {
        $customer = $this->customer();
        $product = $this->product(['is_published' => false]);

        $this->actingAs($customer)->post(route('orders.request'), $this->purchasePayload($product))
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_out_of_stock_product_cannot_be_purchased(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock_quantity' => 0]);

        $this->actingAs($customer)->post(route('orders.request'), $this->purchasePayload($product))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_insufficient_stock_rejects_the_requested_quantity(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock_quantity' => 2]);

        $this->actingAs($customer)->post(route('orders.request'), $this->purchasePayload($product, 3))
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_valid_purchase_uses_authoritative_price_and_reserves_stock(): void
    {
        $customer = $this->customer();
        $product = $this->product([
            'price_minor' => 2500,
            'sale_price_minor' => 2000,
            'stock_quantity' => 5,
        ]);

        $this->actingAs($customer)->post(route('orders.request'), [
            ...$this->purchasePayload($product, 2),
            'price_minor' => 1,
        ])->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $order = Order::query()->with('items')->sole();

        $this->assertSame(2000, $order->items->sole()->price_minor);
        $this->assertSame(4000, $order->subtotal_minor);
        $this->assertSame(4500, $order->total_minor);
        $this->assertNotNull($order->inventory_reserved_at);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'order_item_id' => $order->items->sole()->id,
            'movement_type' => 'online_reservation',
            'quantity_delta' => -2,
            'quantity_after' => 3,
        ]);
    }

    public function test_order_and_inventory_roll_back_if_order_item_creation_fails(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock_quantity' => 5]);

        OrderItem::creating(function (): void {
            throw new RuntimeException('Simulated order item persistence failure.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($customer)->post(route('orders.request'), $this->purchasePayload($product));
            $this->fail('The simulated order item failure should be raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated order item persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_order_and_stock_roll_back_if_inventory_movement_persistence_fails(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock_quantity' => 5]);
        $failOnce = true;
        InventoryMovement::creating(function () use (&$failOnce): void {
            if ($failOnce) {
                $failOnce = false;
                throw new RuntimeException('Simulated inventory ledger failure.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($customer)->post(route('orders.request'), $this->purchasePayload($product));
            $this->fail('The simulated inventory movement failure should be raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated inventory ledger failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_cancelling_a_reserved_order_restores_stock_once(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock_quantity' => 5]);

        $this->actingAs($customer)->post(route('orders.request'), $this->purchasePayload($product, 2));
        $order = $customer->orders()->sole();

        $this->assertSame(3, $product->fresh()->stock_quantity);

        $this->actingAs($customer)->post(route('orders.cancel', $order))
            ->assertSessionHas('success');

        $this->actingAs($customer)->post(route('orders.cancel', $order))->assertForbidden();

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertNull($order->fresh()->inventory_reserved_at);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'movement_type' => 'cancellation_release',
            'quantity_delta' => 2,
            'quantity_after' => 5,
        ]);
        $this->assertDatabaseCount('inventory_movements', 3);
    }

    public function test_repeated_online_submission_reserves_stock_once_and_rejects_key_reuse(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock_quantity' => 5]);
        $payload = $this->purchasePayload($product, 2);

        $this->actingAs($customer)->post(route('orders.request'), $payload)->assertRedirect(route('dashboard'));
        $this->post(route('orders.request'), $payload)->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 2);

        $this->post(route('orders.request'), [...$payload, 'quantity' => 1])->assertSessionHasErrors('submission_key');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_delivered_order_cannot_release_reserved_stock_through_customer_cancellation(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock_quantity' => 5]);
        $this->actingAs($customer)->post(route('orders.request'), $this->purchasePayload($product, 2));
        $order = $customer->orders()->sole();
        $order->forceFill(['status' => \App\Enums\OrderStatus::Delivered])->save();

        $this->actingAs($customer)->post(route('orders.cancel', $order))->assertForbidden();

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseMissing('inventory_movements', ['movement_type' => 'cancellation_release']);
    }

    public function test_seller_cannot_submit_a_customer_product_order_directly(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);
        $product = $this->product();

        $this->actingAs($seller)->post(route('orders.request'), $this->purchasePayload($product))
            ->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(array $overrides = []): Product
    {
        $category = ProductCategory::create([
            'name' => 'Test Food',
            'slug' => 'test-food',
        ]);

        return Product::create(array_merge([
            'product_category_id' => $category->id,
            'name' => 'Test Food',
            'slug' => 'test-food-'.uniqid(),
            'sku' => 'TEST-'.strtoupper(uniqid()),
            'price_minor' => 2500,
            'stock_quantity' => 5,
            'is_published' => true,
        ], $overrides));
    }

    private function customer(): User
    {
        return User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(Product $product, int $quantity = 1): array
    {
        try {
            $pricingFields = $this->onlinePricingFields($product, $quantity);
        } catch (\Illuminate\Validation\ValidationException) {
            $pricingFields = [
                'pricing_signature' => str_repeat('0', 64),
                'pricing_issued_at' => now()->timestamp,
                'accepted_pricing_signature' => str_repeat('0', 64),
            ];
        }

        return [
            'product_id' => $product->id,
            'submission_key' => (string) Str::uuid(),
            'quantity' => $quantity,
            ...$pricingFields,
            'customer_phone' => '+1 555 123 4567',
            'shipping_address' => '123 Test Street',
            'shipping_city' => 'Seattle',
        ];
    }
}
