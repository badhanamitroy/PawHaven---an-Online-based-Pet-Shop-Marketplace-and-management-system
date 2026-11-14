<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Facades\Settings;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderRequestController extends Controller
{
    /**
     * Store a new customer purchase / buy request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $quantity = (int) $validated['quantity'];

        [$order, $product] = DB::transaction(function () use ($request, $validated, $quantity): array {
            /** @var Product $product */
            $product = Product::query()
                ->whereKey($validated['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $product->is_published) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is no longer available for purchase.',
                ]);
            }

            if ($product->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'There is not enough stock to fulfill this quantity.',
                ]);
            }

            $reserved = Product::query()
                ->whereKey($product->id)
                ->where('is_published', true)
                ->where('stock_quantity', '>=', $quantity)
                ->decrement('stock_quantity', $quantity);

            if ($reserved !== 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'There is not enough stock to fulfill this quantity.',
                ]);
            }

            $product->stock_quantity -= $quantity;
            $unitPrice = $product->sale_price_minor ?? $product->price_minor;
            $subtotal = $unitPrice * $quantity;

            $freeShippingThreshold = (int) Settings::get('shop.free_shipping_threshold_minor', 0);
            $configuredFee = (int) Settings::get('shop.delivery_fee_minor', 0);
            $deliveryFee = ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold) ? 0 : $configuredFee;
            $total = $subtotal + $deliveryFee;
            $user = $request->user();

            $order = Order::create([
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
                'notes' => $validated['notes'] ?? null,
                'inventory_reserved_at' => now(),
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'price_minor' => $unitPrice,
                'quantity' => $quantity,
                'subtotal_minor' => $subtotal,
            ]);

            return [$order, $product];
        });

        return redirect()->route('dashboard')->with(
            'success',
            "Your buy request for '{$product->name}' (Order #{$order->order_number}) was submitted successfully! The shopkeeper will review it and confirm payment."
        );
    }
}
