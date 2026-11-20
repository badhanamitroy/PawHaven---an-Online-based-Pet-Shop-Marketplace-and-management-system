<?php

namespace App\Services;

use App\Enums\SalesChannel;
use App\Models\BranchProductPrice;
use App\Models\Coupon;
use App\Models\Company;
use App\Models\DiscountRedemption;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single server-side price resolver for the online order-request and POS flows.
 * Monetary values are integer minor units; percent values are basis points.
 */
class PricingService
{
    public const QUOTE_TTL_SECONDS = 900;

    /** @param array<int|string, int|string> $cart */
    public function quote(
        array $cart,
        SalesChannel|string $channel,
        ?int $branchId = null,
        ?string $couponCode = null,
        ?User $customer = null,
        ?string $customerKey = null,
        bool $lockForCheckout = false,
        ?int $issuedAt = null,
    ): array {
        $issuedAt ??= now()->timestamp;
        $this->assertQuoteFresh($issuedAt);

        $channelValue = $channel instanceof SalesChannel ? $channel->value : $channel;
        if (! in_array($channelValue, [SalesChannel::Online->value, SalesChannel::PointOfSale->value], true)) {
            throw ValidationException::withMessages(['cart' => 'This sales channel cannot be priced.']);
        }
        if ($cart === [] || count($cart) > 30) {
            throw ValidationException::withMessages(['cart' => 'Add at least one available product before checkout.']);
        }

        $normalized = [];
        foreach ($cart as $productId => $quantity) {
            if (! ctype_digit((string) $productId) || (int) $productId < 1
                || filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 1 || (int) $quantity > 10) {
                throw ValidationException::withMessages(['cart' => 'Product quantities must be whole numbers from 1 to 10.']);
            }
            $normalized[(int) $productId] = (int) $quantity;
        }
        ksort($normalized);

        $products = Product::query()->withoutGlobalScopes()
            ->whereIn('id', array_keys($normalized))
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true)->whereColumn('product_categories.company_id', 'products.company_id'))
            ->orderBy('id')
            ->when($lockForCheckout, fn (Builder $query) => $query->lockForUpdate())
            ->get()->keyBy(fn (Product $product): int => (int) $product->getKey());
        if ($products->count() !== count($normalized)) {
            throw ValidationException::withMessages(['cart' => 'One or more products are no longer available.']);
        }

        $companyIds = $products->pluck('company_id')->unique();
        if ($companyIds->count() !== 1 || $products->contains(fn (Product $product): bool => ! $product->isPurchasable())) {
            throw ValidationException::withMessages(['cart' => 'Products must be published, unarchived, and from one active catalogue.']);
        }
        $companyId = (int) $companyIds->first();
        $company = Company::query()->withoutGlobalScopes()->whereKey($companyId)->where('is_active', true)->first();
        if (! $company) {
            throw ValidationException::withMessages(['cart' => 'This company catalogue is not currently available.']);
        }
        app(BusinessAccessService::class)->installationCompany($companyId);
        if ($branchId !== null && ! \App\Models\Branch::query()->withoutGlobalScopes()
            ->whereKey($branchId)->where('company_id', $companyId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['cart' => 'The selected branch is not currently available.']);
        }
        $currency = null;
        foreach ($products as $product) {
            if ($product->branch_id !== null && ($branchId === null || (int) $product->branch_id !== $branchId)) {
                throw ValidationException::withMessages(['cart' => 'A product is assigned to a different branch.']);
            }
            if ($currency !== null && $currency !== $product->currency) {
                throw ValidationException::withMessages(['cart' => 'Products with different currencies cannot be combined in one order.']);
            }
            $currency = $product->currency;
        }
        $currency ??= app(CurrencyPolicy::class)->current();
        app(CurrencyPolicy::class)->assertCanCreateIn($currency);

        $branchPrices = $branchId === null ? collect() : BranchProductPrice::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('branch_id', $branchId)
            ->whereIn('product_id', array_keys($normalized))
            ->when($lockForCheckout, fn (Builder $query) => $query->lockForUpdate())
            ->get()->keyBy('product_id');

        $lines = [];
        foreach ($normalized as $productId => $quantity) {
            /** @var Product $product */
            $product = $products->get($productId);
            $branchPrice = $branchPrices->get($productId);
            if ($branchPrice && strtoupper($branchPrice->currency) !== strtoupper($currency)) {
                throw ValidationException::withMessages(['cart' => 'Branch price currency does not match the product currency.']);
            }
            $resolved = $branchPrice?->price_minor ?? $product->sale_price_minor ?? $product->price_minor;
            $lines[$productId] = [
                'product' => $product,
                'quantity' => $quantity,
                'base_unit_minor' => (int) $product->price_minor,
                'resolved_unit_minor' => (int) $resolved,
                'promotion_discount_minor' => 0,
                'coupon_discount_minor' => 0,
                'promotion_id' => null,
                'coupon_id' => null,
            ];
        }

        $now = now();
        $promotions = Promotion::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('is_active', true)->where('currency', $currency)
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where(fn (Builder $query) => $query->where('sales_channel', 'both')->orWhere('sales_channel', $channelValue))
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderByDesc('priority')->orderBy('id')
            ->when($lockForCheckout, fn (Builder $query) => $query->lockForUpdate())
            ->get();

        $selectedPromotion = null;
        foreach ($promotions as $promotion) {
            $used = $this->countRedemptions(
                DiscountRedemption::query()->where('promotion_id', $promotion->id),
                $lockForCheckout,
            );
            if ($promotion->usage_limit !== null && $used >= $promotion->usage_limit) {
                continue;
            }
            $eligibleIds = $this->eligibleLineIds($promotion, $lines);
            $eligibleTotal = $this->lineTotal($lines, $eligibleIds, 'resolved_unit_minor');
            if ($eligibleIds === [] || $eligibleTotal < $promotion->minimum_purchase_minor) {
                continue;
            }
            $discount = $this->discountAmount($promotion->discount_type, $promotion->discount_value, $eligibleTotal, $promotion->maximum_discount_minor);
            if ($discount <= 0) {
                continue;
            }
            $this->allocateDiscount($lines, $eligibleIds, $discount, 'resolved_unit_minor', 'promotion_discount_minor');
            foreach ($eligibleIds as $id) {
                $lines[$id]['promotion_id'] = (int) $promotion->id;
            }
            $selectedPromotion = $promotion;
            break;
        }

        $coupon = null;
        $normalizedCode = strtoupper(trim((string) $couponCode));
        if ($normalizedCode !== '') {
            $couponQuery = Coupon::query()->withoutGlobalScopes()
                ->where('company_id', $companyId)->where('code', $normalizedCode)
                ->where('is_active', true)->where('currency', $currency)
                ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ->where(fn (Builder $query) => $query->where('sales_channel', 'both')->orWhere('sales_channel', $channelValue))
                ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now));
            $coupon = $lockForCheckout ? $couponQuery->lockForUpdate()->first() : $couponQuery->first();
            if (! $coupon) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon is invalid, inactive, expired, or not available for this order.']);
            }
            if ($selectedPromotion && ! $selectedPromotion->stack_with_coupon) {
                throw ValidationException::withMessages(['coupon_code' => 'The active promotion cannot be combined with a coupon.']);
            }
            $redeemed = DiscountRedemption::query()->where('coupon_id', $coupon->id);
            if ($coupon->usage_limit !== null
                && $this->countRedemptions(clone $redeemed, $lockForCheckout) >= $coupon->usage_limit) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon has reached its usage limit.']);
            }
            if ($coupon->per_customer_limit !== null) {
                $customerKey = $customerKey === null ? null : hash('sha256', mb_strtolower(trim($customerKey)));
                if (! $customer && $customerKey === null) {
                    throw ValidationException::withMessages(['coupon_code' => 'Enter the customer identity before applying this coupon.']);
                }
                $customerRedemptions = (clone $redeemed)->where(function ($query) use ($customer, $customerKey): void {
                    if ($customer) {
                        $query->where('customer_user_id', $customer->id);
                        if ($customerKey !== null) {
                            $query->orWhere('customer_key', $customerKey);
                        }
                    } else {
                        $query->where('customer_key', $customerKey);
                    }
                });
                $customerUses = $this->countRedemptions($customerRedemptions, $lockForCheckout);
                if ($customerUses >= $coupon->per_customer_limit) {
                    throw ValidationException::withMessages(['coupon_code' => 'This customer has reached the coupon usage limit.']);
                }
            }
            $eligibleIds = $this->eligibleLineIds($coupon, $lines);
            $afterPromotionTotal = $this->lineTotal($lines, $eligibleIds, 'resolved_unit_minor', true);
            if ($eligibleIds === [] || $afterPromotionTotal < $coupon->minimum_purchase_minor) {
                throw ValidationException::withMessages(['coupon_code' => 'This order does not meet the coupon requirements.']);
            }
            $couponDiscount = $this->discountAmount($coupon->discount_type, $coupon->discount_value, $afterPromotionTotal, $coupon->maximum_discount_minor);
            $this->allocateDiscount($lines, $eligibleIds, $couponDiscount, 'resolved_unit_minor', 'coupon_discount_minor', true);
            foreach ($eligibleIds as $id) {
                $lines[$id]['coupon_id'] = (int) $coupon->id;
            }
        }

        $baseSubtotal = 0;
        $promotionDiscount = 0;
        $couponDiscount = 0;
        $subtotal = 0;
        foreach ($lines as &$line) {
            $product = $line['product'];
            $quantity = $line['quantity'];
            $baseSubtotal += $line['base_unit_minor'] * $quantity;
            $promotionDiscount += $line['promotion_discount_minor'];
            $couponDiscount += $line['coupon_discount_minor'];
            // Keep the legacy OrderItem unit price as the resolved pre-discount price;
            // order/line discount snapshots reconcile it to the final subtotal exactly.
            $line['unit_price_minor'] = $line['resolved_unit_minor'];
            $line['line_subtotal_minor'] = $line['resolved_unit_minor'] * $quantity
                - $line['promotion_discount_minor'] - $line['coupon_discount_minor'];
            $subtotal += $line['line_subtotal_minor'];
            $line['product_id'] = (int) $product->id;
            unset($line['product']);
        }
        unset($line);

        // The existing order, order-item and discount snapshot columns are unsigned
        // 32-bit minor-unit integers. Reject quotes that cannot be persisted instead
        // of allowing a later database overflow (even when a discount lowers the net).
        $maximumOrderAmount = 4_294_967_295;
        if ($baseSubtotal > $maximumOrderAmount || $subtotal > $maximumOrderAmount
            || $promotionDiscount > $maximumOrderAmount || $couponDiscount > $maximumOrderAmount) {
            throw ValidationException::withMessages([
                'cart' => 'The order amount exceeds the range supported by the current order schema.',
            ]);
        }

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'channel' => $channelValue,
            'currency' => $currency,
            'issued_at' => $issuedAt,
            'lines' => $lines,
            'base_subtotal_minor' => $baseSubtotal,
            'promotion_discount_minor' => $promotionDiscount,
            'coupon_discount_minor' => $couponDiscount,
            'subtotal_minor' => $subtotal,
            'promotion_id' => $selectedPromotion?->id,
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'promotion_name' => $selectedPromotion?->name,
        ];
    }

    public function signature(array $quote): string
    {
        $payload = [
            'issued_at' => $quote['issued_at'],
            'company' => $quote['company_id'], 'branch' => $quote['branch_id'], 'channel' => $quote['channel'],
            'currency' => $quote['currency'], 'coupon' => $quote['coupon_code'],
            'promotion' => $quote['promotion_id'], 'total' => $quote['subtotal_minor'],
            'delivery_fee' => $quote['delivery_fee_minor'] ?? 0,
            'payable_total' => $quote['subtotal_minor'] + ($quote['delivery_fee_minor'] ?? 0),
            'lines' => array_map(fn (array $line): array => [
                $line['product_id'], $line['quantity'], $line['base_unit_minor'], $line['resolved_unit_minor'],
                $line['promotion_discount_minor'], $line['coupon_discount_minor'], $line['line_subtotal_minor'],
                $line['promotion_id'], $line['coupon_id'],
            ], array_values($quote['lines'])),
        ];

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function assertQuoteFresh(int $issuedAt): void
    {
        $now = now()->timestamp;
        if ($issuedAt > $now || $now - $issuedAt > self::QUOTE_TTL_SECONDS) {
            throw ValidationException::withMessages([
                'pricing_signature' => 'This price quote has expired. Refresh the price and review it before checkout.',
            ]);
        }
    }

    private function countRedemptions(Builder $query, bool $useCurrentRead): int
    {
        if (! $useCurrentRead) {
            return $query->count();
        }

        // The intent is to count committed redemptions after checkout acquires the
        // campaign/coupon lock. Verify this locking-read behavior on the production
        // database and isolation level; SQLite does not emulate row-lock concurrency.
        return $query->orderBy('id')->lockForUpdate()->get(['id'])->count();
    }

    public function recordRedemption(array $quote, \App\Models\Order $order, ?User $customer, ?string $customerKey = null): void
    {
        if (! $quote['promotion_id'] && ! $quote['coupon_id']) {
            return;
        }

        DiscountRedemption::create([
            'order_id' => $order->id,
            'promotion_id' => $quote['promotion_id'],
            'coupon_id' => $quote['coupon_id'],
            'customer_user_id' => $customer?->id,
            'customer_key' => $customerKey === null ? null : hash('sha256', mb_strtolower(trim($customerKey))),
            'coupon_code_snapshot' => $quote['coupon_code'],
            'promotion_discount_minor' => $quote['promotion_discount_minor'],
            'coupon_discount_minor' => $quote['coupon_discount_minor'],
            'currency' => $quote['currency'],
        ]);
    }

    private function eligibleLineIds(object $discountable, array $lines): array
    {
        $prefix = $discountable instanceof Promotion ? 'promotion' : 'coupon';
        $foreignKey = $prefix.'_id';
        $productIds = DB::table($prefix.'_products')->where($foreignKey, $discountable->id)
            ->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
        $categoryIds = DB::table($prefix.'_categories')->where($foreignKey, $discountable->id)
            ->pluck('product_category_id')->map(fn ($id): int => (int) $id)->all();
        if ($productIds === [] && $categoryIds === []) {
            return array_keys($lines);
        }
        $eligible = [];
        foreach ($lines as $id => $line) {
            /** @var Product $product */
            $product = $line['product'];
            if (in_array((int) $id, $productIds, true) || in_array((int) $product->product_category_id, $categoryIds, true)) {
                $eligible[] = $id;
            }
        }

        return $eligible;
    }

    private function lineTotal(array $lines, array $ids, string $priceField, bool $afterPromotion = false): int
    {
        $sum = 0;
        foreach ($ids as $id) {
            $line = $lines[$id];
            $total = $line[$priceField] * $line['quantity'] - $line['promotion_discount_minor'];
            $sum += $afterPromotion ? $total : $line[$priceField] * $line['quantity'];
        }

        return $sum;
    }

    private function discountAmount(string $type, int $value, int $eligibleTotal, ?int $cap): int
    {
        if ($eligibleTotal <= 0) {
            return 0;
        }
        $amount = $type === 'percentage'
            ? intdiv(($eligibleTotal * $value) + 5000, 10000)
            : min($value, $eligibleTotal);
        if ($cap !== null) {
            $amount = min($amount, $cap);
        }

        return min(max(0, $amount), $eligibleTotal);
    }

    private function allocateDiscount(array &$lines, array $ids, int $discount, string $unitField, string $targetField, bool $afterPromotion = false): void
    {
        if ($discount <= 0 || $ids === []) {
            return;
        }
        $weights = [];
        $weightTotal = 0;
        foreach ($ids as $id) {
            $line = $lines[$id];
            $weight = $line[$unitField] * $line['quantity'] - ($targetField === 'coupon_discount_minor' ? $line['promotion_discount_minor'] : 0);
            $weights[$id] = max(0, $weight);
            $weightTotal += $weights[$id];
        }
        if ($weightTotal <= 0) {
            return;
        }
        $remaining = $discount;
        foreach ($ids as $index => $id) {
            $share = $index === array_key_last($ids)
                ? $remaining
                : intdiv($discount * $weights[$id], $weightTotal);
            $share = min($share, $weights[$id]);
            $lines[$id][$targetField] += $share;
            $remaining -= $share;
        }
        if ($remaining > 0) {
            foreach ($ids as $id) {
                $capacity = $weights[$id] - $lines[$id][$targetField];
                $extra = min($remaining, max(0, $capacity));
                $lines[$id][$targetField] += $extra;
                $remaining -= $extra;
                if ($remaining === 0) {
                    break;
                }
            }
        }
    }
}
