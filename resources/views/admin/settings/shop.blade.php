@extends('layouts.admin')

@section('title', 'Shop & Delivery Settings')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">PawHaven Pet Shop &bull; Commerce Settings</h1>
        <p class="text-xs text-[#69736B] mt-1">Configure retail single-shop currencies, delivery logistics, Cash on Delivery support, and customer care details.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.shop.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Currency & Pricing --}}
        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-[#173F32] border-b border-gray-100 pb-3">Currency &amp; Checkout Display</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="currency" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Currency Code <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="currency"
                        id="currency"
                        value="{{ old('currency', $settings['shop.currency'] ?? 'USD') }}"
                        required
                        class="w-full uppercase font-mono rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    <p class="mt-1 text-[11px] text-[#69736B]">Supported: USD, BDT, CAD, EUR (two decimal places). Currency is locked after monetary records exist; changing historical amounts requires a reviewed data migration.</p>
                    @error('currency')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="currency_symbol" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Currency Symbol <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="currency_symbol"
                        id="currency_symbol"
                        value="{{ old('currency_symbol', $settings['shop.currency_symbol'] ?? '$') }}"
                        required
                        class="w-full font-mono rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    @error('currency_symbol')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Delivery & Fulfillment --}}
        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-[#173F32] border-b border-gray-100 pb-3">Delivery &amp; Fulfillment Rates</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="delivery_fee" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Flat Standard Delivery Fee ({{ $settings['shop.currency'] ?? 'USD' }}) <span class="text-red-500">*</span>
                    </label>
                    @php
                        $feeMinor = (int) ($settings['shop.delivery_fee_minor'] ?? 500);
                        $defaultFee = number_format($feeMinor / 100, 2, '.', '');
                    @endphp
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="delivery_fee"
                        id="delivery_fee"
                        value="{{ old('delivery_fee', $defaultFee) }}"
                        required
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    <p class="mt-1 text-[11px] text-[#69736B]">Applied to orders below the free shipping threshold.</p>
                </div>

                <div>
                    <label for="free_shipping_threshold" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Free Shipping Threshold ({{ $settings['shop.currency'] ?? 'USD' }}) <span class="text-red-500">*</span>
                    </label>
                    @php
                        $threshMinor = (int) ($settings['shop.free_shipping_threshold_minor'] ?? 4900);
                        $defaultThresh = number_format($threshMinor / 100, 2, '.', '');
                    @endphp
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="free_shipping_threshold"
                        id="free_shipping_threshold"
                        value="{{ old('free_shipping_threshold', $defaultThresh) }}"
                        required
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                    <p class="mt-1 text-[11px] text-[#69736B]">Cart totals at or above this amount receive free delivery.</p>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    @php
                        $codEnabled = ($settings['shop.cod_enabled'] ?? 'true') === 'true';
                    @endphp
                    <input
                        type="checkbox"
                        name="cod_enabled"
                        value="1"
                        {{ old('cod_enabled', $codEnabled ? '1' : '0') == '1' ? 'checked' : '' }}
                        class="rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"
                    />
                    <div>
                        <span class="text-xs font-bold text-[#173F32]">Enable Cash on Delivery (COD)</span>
                        <p class="text-[11px] text-[#69736B]">Allows customers to pay upon receiving their pet supplies order.</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Store Location & Support --}}
        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-[#173F32] border-b border-gray-100 pb-3">Headquarters &amp; Customer Support</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="customer_support_phone" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Customer Support Phone
                    </label>
                    <input
                        type="text"
                        name="customer_support_phone"
                        id="customer_support_phone"
                        value="{{ old('customer_support_phone', $settings['shop.customer_support_phone'] ?? '') }}"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                </div>

                <div>
                    <label for="tax_percentage" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Sales Tax Rate (%)
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        name="tax_percentage"
                        id="tax_percentage"
                        value="{{ old('tax_percentage', $settings['shop.tax_percentage'] ?? '0') }}"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    />
                </div>

                <div class="sm:col-span-2">
                    <label for="store_address" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                        Retail Store / Dispatch Headquarters Address
                    </label>
                    <textarea
                        name="store_address"
                        id="store_address"
                        rows="2"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                    >{{ old('store_address', $settings['shop.store_address'] ?? '123 Meadow Lane, Green Hills, CA 90210') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end">
            <button
                type="submit"
                class="rounded-xl bg-[#245C45] px-6 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#173F32] transition-colors"
            >
                Save Commerce Settings
            </button>
        </div>
    </form>
</div>
@endsection
