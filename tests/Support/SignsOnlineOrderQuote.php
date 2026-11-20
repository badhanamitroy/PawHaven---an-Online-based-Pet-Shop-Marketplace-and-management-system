<?php

namespace Tests\Support;

use App\Enums\SalesChannel;
use App\Facades\Settings;
use App\Models\Product;
use App\Models\User;
use App\Services\PricingService;

trait SignsOnlineOrderQuote
{
    /** @return array{pricing_signature: string, pricing_issued_at: int, accepted_pricing_signature: string} */
    protected function onlinePricingFields(Product $product, int $quantity, ?string $couponCode = null, ?User $customer = null): array
    {
        $quote = app(PricingService::class)->quote(
            [(int) $product->id => $quantity],
            SalesChannel::Online,
            $product->branch_id === null ? null : (int) $product->branch_id,
            $couponCode,
            $customer,
            $customer?->email,
        );
        $threshold = (int) Settings::get('shop.free_shipping_threshold_minor', 0);
        $quote['delivery_fee_minor'] = ($threshold > 0 && $quote['subtotal_minor'] >= $threshold)
            ? 0
            : (int) Settings::get('shop.delivery_fee_minor', 0);

        $signature = app(PricingService::class)->signature($quote);

        return [
            'pricing_signature' => $signature,
            'pricing_issued_at' => (int) $quote['issued_at'],
            'accepted_pricing_signature' => $signature,
        ];
    }

    protected function onlinePricingSignature(Product $product, int $quantity, ?string $couponCode = null, ?User $customer = null): string
    {
        return $this->onlinePricingFields($product, $quantity, $couponCode, $customer)['pricing_signature'];
    }
}
