<?php

namespace Tests\Feature;

use App\Enums\SalesChannel;
use App\Facades\Settings;
use App\Models\Branch;
use App\Models\BranchProductPrice;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\DiscountRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class PricingAndPromotionsTest extends TestCase
{
    use CreatesBusinessMemberships;
    use RefreshDatabase;

    public function test_online_checkout_applies_one_promotion_then_coupon_and_snapshots_the_breakdown(): void
    {
        $customer = $this->customer();
        $product = $this->product(['price_minor' => 3000, 'sale_price_minor' => 2500]);
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'name' => 'Ten percent', 'discount_type' => 'percentage',
            'discount_value' => 1000, 'minimum_purchase_minor' => 0, 'priority' => 10,
            'sales_channel' => 'both', 'stack_with_coupon' => true, 'is_active' => true,
            'currency' => $product->currency,
        ]);
        $promotion->products()->attach($product->id);
        Coupon::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'code' => 'SAVE1', 'name' => 'One dollar off',
            'discount_type' => 'fixed', 'discount_value' => 100, 'minimum_purchase_minor' => 0,
            'usage_limit' => 1, 'per_customer_limit' => 1, 'sales_channel' => 'online',
            'is_active' => true, 'currency' => $product->currency,
        ])->products()->attach($product->id);

        $payload = $this->quoteAndPurchasePayload($customer, $product, 'SAVE1', 2);
        $this->actingAs($customer)->post(route('orders.request'), $payload)->assertRedirect(route('dashboard'));

        $order = Order::query()->withoutGlobalScopes()->sole();
        $item = $order->items()->sole();
        $this->assertSame(4400, $order->subtotal_minor); // 2 x company sale price, less 10% and one currency unit.
        $this->assertSame(500, $order->promotion_discount_minor);
        $this->assertSame(100, $order->coupon_discount_minor);
        $this->assertSame(3000, $item->base_price_minor);
        $this->assertSame(2500, $item->resolved_price_minor);
        $this->assertSame(2500, $item->price_minor);
        $this->assertSame(500, $item->promotion_discount_minor);
        $this->assertSame(100, $item->coupon_discount_minor);
        $this->assertSame(4400, $item->subtotal_minor);
        $this->assertDatabaseCount('discount_redemptions', 1);
        $owner = $this->owner();
        $this->actingAs($owner)->get(route('admin.pricing.index'))->assertOk()->assertSee('SAVE1')->assertSee('Gross merchandise');

        // Repeating the same idempotency key returns the same order and redemption.
        $this->actingAs($customer)->post(route('orders.request'), $payload)->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('discount_redemptions', 1);

        $quote = $this->actingAs($customer)->postJson(route('orders.quote'), [
            'product_id' => $product->id, 'quantity' => 1, 'coupon_code' => 'SAVE1',
        ]);
        $quote->assertUnprocessable();
    }

    public function test_branch_price_precedes_company_price_and_only_highest_priority_promotion_applies(): void
    {
        $owner = $this->owner();
        $branch = $this->branch();
        $product = $this->product(['price_minor' => 10000, 'sale_price_minor' => 9000]);
        $this->actingAs($owner);
        BranchProductPrice::query()->withoutGlobalScopes()->create([
            'product_id' => $product->id, 'company_id' => $product->company_id, 'branch_id' => $branch->id,
            'price_minor' => 8000, 'currency' => $product->currency,
        ]);
        $first = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'name' => 'Priority first', 'discount_type' => 'fixed',
            'discount_value' => 500, 'minimum_purchase_minor' => 0, 'priority' => 10,
            'sales_channel' => 'pos', 'is_active' => true, 'currency' => $product->currency,
        ]);
        $first->products()->attach($product->id);
        $second = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'name' => 'Priority second', 'discount_type' => 'percentage',
            'discount_value' => 5000, 'minimum_purchase_minor' => 0, 'priority' => 1,
            'sales_channel' => 'pos', 'is_active' => true, 'currency' => $product->currency,
        ]);
        $second->products()->attach($product->id);

        $quote = app(PricingService::class)->quote([$product->id => 2], SalesChannel::PointOfSale, $branch->id);

        $this->assertSame(8000, $quote['lines'][$product->id]['resolved_unit_minor']);
        $this->assertSame(1000, $quote['promotion_discount_minor']);
        $this->assertSame(15000, $quote['subtotal_minor']);
        $this->assertSame($first->id, $quote['promotion_id']);
        $this->assertNotSame($second->id, $quote['promotion_id']);

        $onlineQuote = app(PricingService::class)->quote([$product->id => 2], SalesChannel::Online, $branch->id);
        $this->assertSame(16000, $onlineQuote['subtotal_minor']);
        $this->assertNull($onlineQuote['promotion_id']);
    }

    public function test_coupon_cannot_stack_unless_the_selected_campaign_allows_it(): void
    {
        $product = $this->product(['price_minor' => 2000]);
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'name' => 'No stacking', 'discount_type' => 'fixed',
            'discount_value' => 100, 'minimum_purchase_minor' => 0, 'priority' => 1,
            'sales_channel' => 'online', 'stack_with_coupon' => false, 'is_active' => true,
            'currency' => $product->currency,
        ]);
        $promotion->products()->attach($product->id);
        Coupon::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'code' => 'ALSO10', 'name' => 'Also discount',
            'discount_type' => 'fixed', 'discount_value' => 100, 'minimum_purchase_minor' => 0,
            'sales_channel' => 'online', 'is_active' => true, 'currency' => $product->currency,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PricingService::class)->quote([$product->id => 1], SalesChannel::Online, null, 'also10');
    }

    public function test_expired_and_inactive_promotions_are_not_applied(): void
    {
        $product = $this->product(['price_minor' => 1500]);
        $expired = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'name' => 'Expired', 'discount_type' => 'fixed',
            'discount_value' => 500, 'minimum_purchase_minor' => 0, 'priority' => 2,
            'sales_channel' => 'online', 'is_active' => true, 'ends_at' => now()->subMinute(), 'currency' => $product->currency,
        ]);
        $expired->products()->attach($product->id);
        $inactive = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'name' => 'Inactive', 'discount_type' => 'fixed',
            'discount_value' => 300, 'minimum_purchase_minor' => 0, 'priority' => 1,
            'sales_channel' => 'online', 'is_active' => false, 'currency' => $product->currency,
        ]);
        $inactive->products()->attach($product->id);

        $quote = app(PricingService::class)->quote([$product->id => 1], SalesChannel::Online);

        $this->assertSame(1500, $quote['subtotal_minor']);
        $this->assertSame(0, $quote['promotion_discount_minor']);
        $this->assertNull($quote['promotion_id']);
    }

    public function test_shop_owner_can_create_and_edit_company_promotions_and_coupons(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $this->actingAs($owner)->post(route('admin.pricing.promotions.store'), [
            'name' => 'Launch offer', 'discount_type' => 'percentage', 'discount_value' => '10.00',
            'minimum_purchase' => '0.00', 'priority' => 5, 'sales_channel' => 'both',
            'stack_with_coupon' => '1', 'is_active' => '1', 'product_ids' => [$product->id],
        ])->assertRedirect();
        $promotion = Promotion::query()->withoutGlobalScopes()->sole();
        $this->assertSame(1000, $promotion->discount_value);

        $this->patch(route('admin.pricing.promotions.update', $promotion), [
            'name' => 'Updated offer', 'discount_type' => 'fixed', 'discount_value' => '2.50',
            'minimum_purchase' => '0.00', 'priority' => 7, 'sales_channel' => 'pos',
            'is_active' => '1', 'product_ids' => [$product->id],
        ])->assertRedirect();
        $this->assertSame(250, $promotion->fresh()->discount_value);
        $this->assertSame('pos', $promotion->fresh()->sales_channel);

        $this->post(route('admin.pricing.coupons.store'), [
            'code' => 'save-5', 'name' => 'Customer offer', 'discount_type' => 'fixed',
            'discount_value' => '5.00', 'minimum_purchase' => '0.00', 'sales_channel' => 'both',
            'per_customer_limit' => 1, 'is_active' => '1',
        ])->assertRedirect();
        $coupon = Coupon::query()->withoutGlobalScopes()->sole();
        $this->assertSame('SAVE-5', $coupon->code);
        $this->patch(route('admin.pricing.coupons.update', $coupon), [
            'code' => 'save-10', 'name' => 'Updated customer offer', 'discount_type' => 'percentage',
            'discount_value' => '12.50', 'minimum_purchase' => '0.00', 'sales_channel' => 'online',
            'per_customer_limit' => 2, 'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame('SAVE-10', $coupon->fresh()->code);
        $this->assertSame(1250, $coupon->fresh()->discount_value);
    }

    public function test_stale_price_signature_is_rejected_without_creating_or_reserving_an_order(): void
    {
        $customer = $this->customer();
        $product = $this->product(['price_minor' => 2500, 'stock_quantity' => 5]);
        $payload = $this->quoteAndPurchasePayload($customer, $product, null, 2);
        $product->forceFill(['price_minor' => 3000])->save();

        $this->actingAs($customer)->post(route('orders.request'), $payload)
            ->assertSessionHasErrors('pricing_signature');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('discount_redemptions', 0);
    }

    public function test_expired_and_tampered_online_quotes_are_rejected(): void
    {
        $customer = $this->customer();
        $product = $this->product();

        $expired = $this->quoteAndPurchasePayload($customer, $product, null, 1);
        $expired['pricing_issued_at'] = now()->timestamp - PricingService::QUOTE_TTL_SECONDS - 1;
        $this->actingAs($customer)->post(route('orders.request'), $expired)
            ->assertSessionHasErrors('pricing_signature');
        $this->assertDatabaseCount('orders', 0);

        $tampered = $this->quoteAndPurchasePayload($customer, $product, null, 1);
        $tampered['pricing_signature'] = str_repeat('0', 64);
        $tampered['accepted_pricing_signature'] = $tampered['pricing_signature'];
        $this->post(route('orders.request'), $tampered)->assertSessionHasErrors('pricing_signature');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_online_checkout_requires_acknowledgement_bound_to_the_current_quote(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $payload = $this->quoteAndPurchasePayload($customer, $product, null, 1);
        $unboundAcknowledgement = $payload;
        $unboundAcknowledgement['accepted_pricing_signature'] = str_repeat('0', 64);

        $this->actingAs($customer)->post(route('orders.request'), $unboundAcknowledgement)
            ->assertSessionHasErrors('pricing_signature');

        unset($payload['accepted_pricing_signature']);

        $this->post(route('orders.request'), $payload)
            ->assertSessionHasErrors('accepted_pricing_signature');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_online_checkout_rejects_quote_after_branch_price_changes(): void
    {
        $owner = $this->owner();
        $branch = $this->branch();
        $product = $this->product(['branch_id' => $branch->id, 'price_minor' => 2000]);
        BranchProductPrice::query()->withoutGlobalScopes()->create([
            'product_id' => $product->id, 'company_id' => $product->company_id, 'branch_id' => $branch->id,
            'price_minor' => 1800, 'currency' => $product->currency,
        ]);
        $customer = $this->customer();
        $payload = $this->quoteAndPurchasePayload($customer, $product, null, 1);
        $this->actingAs($owner);
        BranchProductPrice::query()->withoutGlobalScopes()->where('product_id', $product->id)
            ->where('branch_id', $branch->id)->update(['price_minor' => 1700]);

        $this->actingAs($customer)->post(route('orders.request'), $payload)
            ->assertSessionHasErrors('pricing_signature');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_online_checkout_rejects_quote_after_promotion_changes(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'name' => 'Mutable offer', 'discount_type' => 'fixed',
            'discount_value' => 100, 'minimum_purchase_minor' => 0, 'priority' => 1,
            'sales_channel' => 'online', 'is_active' => true, 'currency' => $product->currency,
        ]);
        $promotion->products()->attach($product->id);
        $payload = $this->quoteAndPurchasePayload($customer, $product, null, 1);
        $promotion->forceFill(['discount_value' => 200])->save();

        $this->actingAs($customer)->post(route('orders.request'), $payload)
            ->assertSessionHasErrors('pricing_signature');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_online_checkout_rejects_quote_after_coupon_changes(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $coupon = Coupon::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'code' => 'EDIT10', 'name' => 'Mutable coupon',
            'discount_type' => 'fixed', 'discount_value' => 100, 'minimum_purchase_minor' => 0,
            'sales_channel' => 'online', 'is_active' => true, 'currency' => $product->currency,
        ]);
        $payload = $this->quoteAndPurchasePayload($customer, $product, $coupon->code, 1);
        $coupon->forceFill(['discount_value' => 200])->save();

        $this->actingAs($customer)->post(route('orders.request'), $payload)
            ->assertSessionHasErrors('pricing_signature');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_per_customer_coupon_limit_is_enforced_without_a_global_limit(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $coupon = Coupon::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'code' => 'ONCEUSER', 'name' => 'One per customer',
            'discount_type' => 'fixed', 'discount_value' => 100, 'minimum_purchase_minor' => 0,
            'usage_limit' => null, 'per_customer_limit' => 1, 'sales_channel' => 'online',
            'is_active' => true, 'currency' => $product->currency,
        ]);
        $this->actingAs($customer)->post(route('orders.request'), $this->quoteAndPurchasePayload($customer, $product, $coupon->code, 1))
            ->assertRedirect(route('dashboard'));

        $quote = $this->actingAs($customer)->postJson(route('orders.quote'), [
            'product_id' => $product->id, 'quantity' => 1, 'coupon_code' => $coupon->code,
        ]);
        $quote->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        $this->assertDatabaseCount('discount_redemptions', 1);
    }

    public function test_coupon_redemption_rolls_back_with_a_failed_order_transaction(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock_quantity' => 5]);
        $coupon = Coupon::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'code' => 'ROLLBACK', 'name' => 'Rollback coupon',
            'discount_type' => 'fixed', 'discount_value' => 100, 'minimum_purchase_minor' => 0,
            'usage_limit' => 1, 'sales_channel' => 'online', 'is_active' => true, 'currency' => $product->currency,
        ]);
        $failOnce = true;
        DiscountRedemption::created(function () use (&$failOnce): void {
            if ($failOnce) {
                $failOnce = false;
                throw new \RuntimeException('Simulated post-redemption failure.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($customer)->post(route('orders.request'), $this->quoteAndPurchasePayload($customer, $product, $coupon->code, 1));
            $this->fail('The simulated post-redemption failure should be raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated post-redemption failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('discount_redemptions', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_branch_currency_mismatch_is_rejected_and_discount_caps_are_respected(): void
    {
        $branch = $this->branch();
        $product = $this->product(['branch_id' => $branch->id, 'price_minor' => 2500]);
        BranchProductPrice::query()->withoutGlobalScopes()->create([
            'product_id' => $product->id, 'company_id' => $product->company_id, 'branch_id' => $branch->id,
            'price_minor' => 2000, 'currency' => 'EUR',
        ]);

        try {
            app(PricingService::class)->quote([$product->id => 1], SalesChannel::Online, $branch->id);
            $this->fail('A branch-price currency mismatch must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        BranchProductPrice::query()->withoutGlobalScopes()->where('product_id', $product->id)->delete();
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $product->company_id, 'name' => 'Capped offer', 'discount_type' => 'percentage',
            'discount_value' => 8000, 'minimum_purchase_minor' => 0, 'maximum_discount_minor' => 400,
            'priority' => 1, 'sales_channel' => 'online', 'is_active' => true, 'currency' => $product->currency,
        ]);
        $promotion->products()->attach($product->id);
        $quote = app(PricingService::class)->quote([$product->id => 1], SalesChannel::Online);
        $this->assertSame(400, $quote['promotion_discount_minor']);
        $this->assertSame(2100, $quote['subtotal_minor']);
    }

    public function test_quote_rejects_amounts_that_cannot_fit_the_existing_order_columns(): void
    {
        $product = $this->product(['price_minor' => 4_294_967_295]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PricingService::class)->quote([$product->id => 2], SalesChannel::Online);
    }

    public function test_pos_uses_the_same_pricing_engine_and_keeps_employee_and_branch_attribution(): void
    {
        $owner = $this->owner();
        $branch = $this->branch();
        $company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $employee = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $employee->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'role' => 'seller', 'is_active' => true,
        ]);
        $product = $this->product(['price_minor' => 10000, 'sale_price_minor' => 9000, 'stock_quantity' => 4]);
        $this->actingAs($owner);
        BranchProductPrice::query()->withoutGlobalScopes()->create([
            'product_id' => $product->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'price_minor' => 8000, 'currency' => $product->currency,
        ]);
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'POS only discount',
            'discount_type' => 'fixed', 'discount_value' => 200, 'minimum_purchase_minor' => 0,
            'priority' => 1, 'sales_channel' => 'pos', 'is_active' => true, 'currency' => $product->currency,
        ]);
        $promotion->products()->attach($product->id);

        $this->actingAs($employee)->get(route('admin.sales-desk.index'))->assertOk();
        $this->post(route('admin.sales-desk.cart.add', $product->id), ['quantity' => 1])->assertRedirect();
        $this->get(route('admin.sales-desk.index'))->assertOk()->assertSee('promotion applied');
        $key = (string) session('employee_sales_desk.submission_key');
        $this->post(route('admin.sales-desk.checkout'), [
            'submission_key' => $key, 'customer_name' => 'Walk-in', 'customer_email' => 'pos@example.test',
            'customer_phone' => '555-0011', 'confirmed' => '1',
        ])->assertRedirect();

        $order = Order::query()->withoutGlobalScopes()->where('sales_channel', 'pos')->sole();
        $this->assertSame($employee->id, $order->employee_seller_id);
        $this->assertSame($company->id, $order->company_id);
        $this->assertSame($branch->id, $order->branch_id);
        $this->assertSame(7800, $order->subtotal_minor);
        $this->assertSame(200, $order->promotion_discount_minor);
        $this->assertSame(8000, $order->items()->sole()->resolved_price_minor);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('discount_redemptions', 1);
    }

    public function test_archived_product_is_not_publicly_purchasable_and_only_shop_owner_can_manage_promotions(): void
    {
        $owner = $this->owner();
        $branch = $this->branch();
        $product = $this->product();
        $this->actingAs($owner)->post(route('admin.products.archive', $product))->assertRedirect(route('admin.products.index'));
        $this->assertNotNull($product->fresh()->archived_at);
        $this->assertFalse($product->fresh()->is_published);

        $quote = $this->actingAs($this->customer())->postJson(route('orders.quote'), [
            'product_id' => $product->id, 'quantity' => 1,
        ]);
        $quote->assertUnprocessable();

        $seller = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $seller->id, 'company_id' => $branch->company_id,
            'branch_id' => $branch->id, 'role' => 'seller', 'is_active' => true,
        ]);
        $marketplaceSeller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
        $platformAdmin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        foreach ([$seller, $marketplaceSeller, $platformAdmin] as $unauthorized) {
            $this->actingAs($unauthorized)->get(route('admin.pricing.index'))->assertForbidden();
        }
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->grantCompanyWideShopAccess($owner);

        return $owner;
    }

    private function branch(): Branch
    {
        $company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();

        return Branch::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => 'Pricing Branch', 'code' => 'PRICE-'.strtoupper(Str::random(5)), 'is_active' => true,
        ]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function product(array $overrides = []): Product
    {
        $company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $key = strtoupper(Str::random(8));
        $category = ProductCategory::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => 'Pricing Category '.$key, 'slug' => 'pricing-category-'.$key,
            'is_active' => true,
        ]);

        return Product::query()->withoutGlobalScopes()->create(array_merge([
            'company_id' => $company->id, 'product_category_id' => $category->id, 'name' => 'Pricing Product '.$key,
            'slug' => 'pricing-product-'.$key, 'sku' => 'PRICING-'.$key, 'price_minor' => 2500,
            'stock_quantity' => 5, 'is_published' => true,
        ], $overrides));
    }

    private function quoteAndPurchasePayload(User $customer, Product $product, ?string $couponCode, int $quantity): array
    {
        $quote = app(PricingService::class)->quote(
            [$product->id => $quantity], SalesChannel::Online,
            $product->branch_id === null ? null : (int) $product->branch_id,
            $couponCode,
            $customer,
            $customer->email,
        );
        $threshold = (int) Settings::get('shop.free_shipping_threshold_minor', 0);
        $quote['delivery_fee_minor'] = ($threshold > 0 && $quote['subtotal_minor'] >= $threshold)
            ? 0
            : (int) Settings::get('shop.delivery_fee_minor', 0);

        return [
            'product_id' => $product->id, 'submission_key' => (string) Str::uuid(), 'quantity' => $quantity,
            'customer_phone' => '+1 555 123 4567', 'shipping_address' => '15 Test Road', 'shipping_city' => 'Seattle',
            'coupon_code' => $couponCode,
            'pricing_signature' => app(PricingService::class)->signature($quote),
            'pricing_issued_at' => (int) $quote['issued_at'],
            'accepted_pricing_signature' => app(PricingService::class)->signature($quote),
        ];
    }
}
