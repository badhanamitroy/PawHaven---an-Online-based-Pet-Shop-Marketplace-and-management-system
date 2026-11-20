<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Facades\Settings;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Services\BusinessAccessService;
use App\Services\FulfilmentService;
use App\Services\InventoryMovementService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderRequestController extends Controller
{
    public function quote(Request $request, PricingService $pricing): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'coupon_code' => ['nullable', 'string', 'max:48'],
        ]);
        $product = Product::query()->withoutGlobalScopes()->findOrFail($validated['product_id']);
        if (! $product->isPurchasable() || $product->stock_quantity < (int) $validated['quantity']) {
            throw ValidationException::withMessages(['product_id' => 'This product is no longer available in the requested quantity.']);
        }
        $quote = $pricing->quote(
            [(int) $product->id => (int) $validated['quantity']],
            SalesChannel::Online,
            $product->branch_id === null ? null : (int) $product->branch_id,
            $validated['coupon_code'] ?? null,
            $request->user(),
            $request->user()?->email,
        );
        $quote = $this->addOnlineDeliveryFee($quote);

        return response()->json([
            'currency' => $quote['currency'],
            'subtotal_minor' => $quote['subtotal_minor'],
            'delivery_fee_minor' => $quote['delivery_fee_minor'],
            'total_minor' => $quote['subtotal_minor'] + $quote['delivery_fee_minor'],
            'promotion_discount_minor' => $quote['promotion_discount_minor'],
            'coupon_discount_minor' => $quote['coupon_discount_minor'],
            'signature' => $pricing->signature($quote),
            'issued_at' => $quote['issued_at'],
        ]);
    }

    /**
     * Store a new customer purchase / buy request.
     */
    public function store(Request $request, InventoryMovementService $inventory, FulfilmentService $fulfilment, PricingService $pricing): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'submission_key' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'coupon_code' => ['nullable', 'string', 'max:48'],
            'pricing_signature' => ['required', 'string', 'size:64'],
            'pricing_issued_at' => ['required', 'integer', 'min:1'],
            'accepted_pricing_signature' => ['required', 'string', 'size:64'],
        ]);

        $quantity = (int) $validated['quantity'];

        try {
            [$order, $product] = DB::transaction(function () use ($request, $validated, $quantity, $inventory, $fulfilment, $pricing): array {
            $existing = Order::query()->withoutGlobalScopes()->with('items')
                ->where('online_submission_key', $validated['submission_key'])->first();
            if ($existing) {
                return $this->resolveExistingSubmission($existing, $request, $validated, $quantity);
            }

            /** @var Product $product */
            $product = Product::query()
                ->whereKey($validated['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $product->isPurchasable()) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is no longer available for purchase.',
                ]);
            }

            $company = Company::query()->withoutGlobalScopes()
                ->whereKey($product->company_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if (! $company) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is no longer available for purchase.',
                ]);
            }
            app(BusinessAccessService::class)->installationCompany((int) $company->id);

            $branch = null;
            if ($product->branch_id !== null) {
                $branch = Branch::query()->withoutGlobalScopes()
                    ->whereKey($product->branch_id)
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                if (! $branch) {
                    throw ValidationException::withMessages([
                        'product_id' => 'This product is no longer available from its assigned branch.',
                    ]);
                }
            }

            if ($product->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'There is not enough stock to fulfill this quantity.',
                ]);
            }

            $quote = $pricing->quote(
                [(int) $product->id => $quantity], SalesChannel::Online,
                $product->branch_id === null ? null : (int) $product->branch_id,
                $validated['coupon_code'] ?? null,
                $request->user(),
                $request->user()->email,
                true,
                (int) $validated['pricing_issued_at'],
            );
            $quote = $this->addOnlineDeliveryFee($quote);
            $currentSignature = $pricing->signature($quote);
            if (! hash_equals($currentSignature, $validated['pricing_signature'])) {
                throw ValidationException::withMessages(['pricing_signature' => 'The price changed. Review the updated price before submitting again.']);
            }
            if (! hash_equals($currentSignature, $validated['accepted_pricing_signature'])) {
                throw ValidationException::withMessages(['pricing_signature' => 'Confirm the current price before submitting the order.']);
            }
            $quote['price_acknowledged_at'] = now()->toIso8601String();
            $line = reset($quote['lines']);
            $subtotal = (int) $quote['subtotal_minor'];

            $deliveryFee = (int) $quote['delivery_fee_minor'];
            $total = $subtotal + $deliveryFee;
            $user = $request->user();

            $order = Order::create([
                'company_id' => $company->id,
                'order_number' => 'PH-'.strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $validated['customer_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'status' => OrderStatus::Pending,
                'payment_method' => 'cod',
                'payment_status' => PaymentStatus::Pending,
                'subtotal_minor' => $subtotal,
                'delivery_fee_minor' => $deliveryFee,
                'total_minor' => $total,
                'currency' => $product->currency,
                'notes' => $validated['notes'] ?? null,
                'inventory_reserved_at' => now(),
                'online_submission_key' => $validated['submission_key'],
                'pricing_snapshot' => $quote,
                'promotion_discount_minor' => $quote['promotion_discount_minor'],
                'coupon_discount_minor' => $quote['coupon_discount_minor'],
            ]);

            if ($branch) {
                $order->forceFill(['branch_id' => $branch->id])->save();
            }

            $item = $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'price_minor' => $line['unit_price_minor'],
                'quantity' => $quantity,
                'subtotal_minor' => $line['line_subtotal_minor'],
                'base_price_minor' => $line['base_unit_minor'],
                'resolved_price_minor' => $line['resolved_unit_minor'],
                'promotion_discount_minor' => $line['promotion_discount_minor'],
                'coupon_discount_minor' => $line['coupon_discount_minor'],
                'promotion_id' => $line['promotion_id'],
                'coupon_id' => $line['coupon_id'],
            ]);
            $inventory->reserveForOrder($order, $item, $product, $user);
            $fulfilment->recordPlaced($order, $user);
            $pricing->recordRedemption($quote, $order, $user, $user->email);

            return [$order, $product];
            }, 3);
        } catch (QueryException $exception) {
            $existing = Order::query()->withoutGlobalScopes()->with('items')
                ->where('online_submission_key', $validated['submission_key'])->first();
            if (! $existing) {
                throw $exception;
            }
            [$order, $product] = $this->resolveExistingSubmission($existing, $request, $validated, $quantity);
        }

        return redirect()->route('dashboard')->with(
            'success',
            "Your buy request for '{$product->name}' (Order #{$order->order_number}) was submitted successfully! The shopkeeper will review it and confirm payment."
        );
    }

    private function addOnlineDeliveryFee(array $quote): array
    {
        $freeShippingThreshold = (int) Settings::get('shop.free_shipping_threshold_minor', 0);
        $configuredFee = (int) Settings::get('shop.delivery_fee_minor', 0);
        $quote['delivery_fee_minor'] = ($freeShippingThreshold > 0 && $quote['subtotal_minor'] >= $freeShippingThreshold)
            ? 0
            : $configuredFee;

        if ($quote['delivery_fee_minor'] < 0
            || $quote['subtotal_minor'] + $quote['delivery_fee_minor'] > 4_294_967_295) {
            throw ValidationException::withMessages([
                'cart' => 'The order total exceeds the range supported by the current order schema.',
            ]);
        }

        return $quote;
    }

    /** @param array<string, mixed> $validated
     *  @return array{Order, Product}
     */
    private function resolveExistingSubmission(Order $order, Request $request, array $validated, int $quantity): array
    {
        $item = $order->items->first();
        if ((int) $order->user_id !== (int) $request->user()->getKey()
            || ! $item
            || $order->items->count() !== 1
            || (int) $item->product_id !== (int) $validated['product_id']
            || (int) $item->quantity !== $quantity
            || $order->customer_phone !== $validated['customer_phone']
            || $order->shipping_address !== $validated['shipping_address']
            || $order->shipping_city !== $validated['shipping_city']
            || $order->notes !== ($validated['notes'] ?? null)) {
            throw ValidationException::withMessages(['submission_key' => 'This checkout key was already used for a different order. Start a new purchase request.']);
        }

        $product = Product::query()->withoutGlobalScopes()->find($item->product_id);
        if (! $product) {
            throw ValidationException::withMessages(['submission_key' => 'The previous order product is no longer available. Contact the shop.']);
        }

        return [$order, $product];
    }
}
