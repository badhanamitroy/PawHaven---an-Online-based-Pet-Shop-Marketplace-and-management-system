@extends('layouts.admin')

@section('title', 'Employee Sales Desk')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Employee Sales Desk</h1>
            <p class="mt-1 text-xs text-[#69736B]">Create an in-store order for {{ $branch->name }}. Product availability is checked again when you submit.</p>
        </div>
        <div class="rounded-xl border border-[#245C45]/15 bg-white px-4 py-3 text-xs text-[#173F32]">
            <span class="font-semibold">Assigned branch</span><span class="mx-2 text-[#69736B]">·</span>{{ $branch->name }}
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <p class="font-semibold">Please review the sale.</p>
            <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <section class="space-y-4 xl:col-span-3" aria-labelledby="catalog-title">
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
                <h2 id="catalog-title" class="text-base font-bold text-[#173F32]">Product catalog</h2>
                <form method="GET" action="{{ route('admin.sales-desk.index') }}" class="mt-3 flex gap-2">
                    <label for="product-search" class="sr-only">Search products by name or SKU</label>
                    <input id="product-search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search name or SKU" class="min-w-0 flex-1 rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-[#245C45] focus:outline-none focus:ring-2 focus:ring-[#245C45]/20">
                    <button class="rounded-xl bg-[#245C45] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#173F32]">Search</button>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse($products as $product)
                    @php($unitPrice = $productPrices[$product->id]['subtotal_minor'] ?? ($product->sale_price_minor ?? $product->price_minor))
                    <article class="flex min-h-40 flex-col justify-between rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-[#173F32]">{{ $product->name }}</h3>
                                    <p class="mt-1 text-xs text-[#69736B]">{{ $product->sku }}{{ $product->category ? ' · '.$product->category->name : '' }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ $product->stock_quantity }} in stock</span>
                            </div>
                            <p class="mt-3 text-lg font-bold text-[#173F32]">{{ $product->currency }} {{ number_format($unitPrice / 100, 2) }}
                                @if(($productPrices[$product->id]['promotion_discount_minor'] ?? 0) > 0)
                                    <span class="ml-1 text-xs font-normal text-gray-500">promotion applied</span>
                                @endif
                                @if($product->sale_price_minor !== null && $product->sale_price_minor < $product->price_minor)
                                    <span class="ml-1 text-xs font-normal text-gray-500 line-through">{{ $product->currency }} {{ number_format($product->price_minor / 100, 2) }}</span>
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('admin.sales-desk.cart.add', $product->id) }}" class="mt-4 flex items-center gap-2">
                            @csrf
                            <label class="sr-only" for="quantity-{{ $product->id }}">Quantity for {{ $product->name }}</label>
                            <input id="quantity-{{ $product->id }}" type="number" name="quantity" value="1" min="1" max="10" class="w-20 rounded-lg border border-gray-200 px-2 py-2 text-sm">
                            <button class="flex-1 rounded-lg bg-[#173F32] px-3 py-2 text-sm font-semibold text-white hover:bg-[#245C45]">Add to cart</button>
                        </form>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-[#69736B] sm:col-span-2">No available products match this search for your branch.</div>
                @endforelse
            </div>
            <div>{{ $products->links() }}</div>
        </section>

        <section class="space-y-4 xl:col-span-2" aria-labelledby="cart-title">
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 id="cart-title" class="text-base font-bold text-[#173F32]">Current cart</h2>
                    <span class="rounded-full bg-[#245C45]/10 px-2.5 py-1 text-xs font-semibold text-[#245C45]">{{ count($cart) }} items</span>
                </div>

                @if(count($cart) > 0)
                    <div class="mt-4 divide-y divide-gray-100">
                        @foreach($cart as $productId => $quantity)
                            @php($cartProduct = $cartProducts->get((int) $productId))
                            <div class="py-3">
                                @if($cartProduct)
                                    @php($quotedLine = $quote['lines'][$productId] ?? null)
                                    @php($linePrice = $quotedLine['line_subtotal_minor'] ?? 0)
                                    <div class="flex justify-between gap-3">
                                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-[#202820]">{{ $cartProduct->name }}</p><p class="mt-0.5 text-xs text-[#69736B]">{{ $cartProduct->currency }} {{ number_format(($quotedLine['resolved_unit_minor'] ?? ($cartProduct->sale_price_minor ?? $cartProduct->price_minor)) / 100, 2) }} each before discounts</p>
                                            @if(($quotedLine['promotion_discount_minor'] ?? 0) > 0)<p class="text-[11px] text-emerald-700">Promotion: −{{ $cartProduct->currency }} {{ number_format($quotedLine['promotion_discount_minor'] / 100, 2) }}</p>@endif
                                            @if(($quotedLine['coupon_discount_minor'] ?? 0) > 0)<p class="text-[11px] text-emerald-700">Coupon: −{{ $cartProduct->currency }} {{ number_format($quotedLine['coupon_discount_minor'] / 100, 2) }}</p>@endif
                                        </div>
                                        <span class="whitespace-nowrap text-sm font-semibold text-[#173F32]">{{ $cartProduct->currency }} {{ number_format($linePrice / 100, 2) }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <form method="POST" action="{{ route('admin.sales-desk.cart.update', $productId) }}" class="flex items-center gap-2">
                                            @csrf @method('PATCH')
                                            <label class="sr-only" for="cart-quantity-{{ $productId }}">Quantity for {{ $cartProduct->name }}</label>
                                            <input id="cart-quantity-{{ $productId }}" type="number" name="quantity" value="{{ $quantity }}" min="1" max="10" class="w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-sm">
                                            <button class="text-xs font-semibold text-[#245C45] hover:underline">Update</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.sales-desk.cart.remove', $productId) }}">
                                            @csrf @method('DELETE')
                                            <button class="text-xs font-semibold text-rose-700 hover:underline">Remove</button>
                                        </form>
                                    </div>
                                @else
                                    <p class="text-sm font-semibold text-amber-800">Product #{{ $productId }} is no longer available for this branch. Remove it before checkout.</p>
                                    <form method="POST" action="{{ route('admin.sales-desk.cart.remove', $productId) }}" class="mt-2">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-700 hover:underline">Remove unavailable item</button></form>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                        <div class="flex justify-between text-[#4B554D]"><span>Subtotal</span><span>{{ $cartProducts->first()?->currency ?? \App\Facades\Settings::get('shop.currency', 'USD') }} {{ number_format($cartSubtotal / 100, 2) }}</span></div>
                        @if(($quote['promotion_discount_minor'] ?? 0) > 0)<div class="flex justify-between text-emerald-700"><span>Promotion discount</span><span>−{{ $cartProducts->first()?->currency ?? \App\Facades\Settings::get('shop.currency', 'USD') }} {{ number_format($quote['promotion_discount_minor'] / 100, 2) }}</span></div>@endif
                        @if(($quote['coupon_discount_minor'] ?? 0) > 0)<div class="flex justify-between text-emerald-700"><span>Coupon discount ({{ $quote['coupon_code'] }})</span><span>−{{ $cartProducts->first()?->currency ?? \App\Facades\Settings::get('shop.currency', 'USD') }} {{ number_format($quote['coupon_discount_minor'] / 100, 2) }}</span></div>@endif
                        <div class="flex justify-between text-[#4B554D]"><span>In-store pickup</span><span>{{ $cartProducts->first()?->currency ?? \App\Facades\Settings::get('shop.currency', 'USD') }} 0.00</span></div>
                        <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold text-[#173F32]"><span>Order total</span><span>{{ $cartProducts->first()?->currency ?? \App\Facades\Settings::get('shop.currency', 'USD') }} {{ number_format($cartSubtotal / 100, 2) }}</span></div>
                        <p class="text-xs text-[#69736B]">Prices are recalculated at checkout. No delivery charge is applied to an in-store pickup.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.sales-desk.coupon') }}" class="mt-4 rounded-xl border border-[#245C45]/15 bg-white p-3 space-y-2">
                        @csrf
                        <label for="coupon-code" class="block text-xs font-semibold text-[#173F32]">Optional coupon</label>
                        <input id="coupon-code" name="coupon_code" value="{{ $couponCode }}" maxlength="48" placeholder="Coupon code" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase">
                        <input name="coupon_customer_email" type="email" value="{{ $couponCustomerEmail }}" maxlength="255" placeholder="Customer email for per-customer limits (optional)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-[#245C45] px-3 py-2 text-xs font-semibold text-white">Apply coupon</button>
                        @if($couponCode !== '')<button name="coupon_code" value="" class="ml-2 rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700">Remove</button>@endif
                    </form>

                    <form method="GET" action="{{ route('admin.sales-desk.index') }}" class="mt-5 rounded-xl bg-gray-50 p-3">
                        <label for="customer-search" class="block text-xs font-semibold text-[#173F32]">Find a verified customer account (optional)</label>
                        <div class="mt-2 flex gap-2">
                            <input id="customer-search" name="customer_search" value="{{ $customerSearch }}" minlength="3" maxlength="100" placeholder="Name or email" class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <button class="rounded-lg border border-[#245C45] px-3 py-2 text-xs font-semibold text-[#245C45]">Find</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.sales-desk.checkout') }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="submission_key" value="{{ session('employee_sales_desk.submission_key') }}">
                        <input type="hidden" name="pricing_signature" value="{{ $pricingSignature }}">
                        @if($customers->isNotEmpty())
                            <div>
                                <label for="customer_id" class="mb-1 block text-xs font-semibold text-[#173F32]">Use found customer</label>
                                <select id="customer_id" name="customer_id" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm">
                                    <option value="">Enter customer details below</option>
                                    @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} — {{ $customer->email }}</option>@endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-[#69736B]">Only verified customer accounts matching your search are shown.</p>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div><label for="customer_name" class="mb-1 block text-xs font-semibold text-[#173F32]">Customer name</label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" maxlength="255" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></div>
                            <div><label for="customer_email" class="mb-1 block text-xs font-semibold text-[#173F32]">Customer email</label><input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}" maxlength="255" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></div>
                        </div>
                        <div><label for="customer_phone" class="mb-1 block text-xs font-semibold text-[#173F32]">Customer phone *</label><input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" required maxlength="30" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></div>
                        <div><label for="notes" class="mb-1 block text-xs font-semibold text-[#173F32]">Order notes (optional)</label><textarea id="notes" name="notes" rows="2" maxlength="500" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">{{ old('notes') }}</textarea></div>
                        <div class="rounded-xl border border-[#245C45]/15 bg-[#FAF8F2] p-3 space-y-2">
                            <p class="text-xs font-semibold text-[#173F32]">Optional payment submission</p>
                            <p class="text-[11px] leading-5 text-[#69736B]">Choose a method and amount only when you are submitting a payment claim. It remains unverified and is not counted as collected until a Shop Owner or Branch Manager verifies it.</p>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <select name="payment_method" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <option value="">No payment recorded — pay later</option>
                                    @foreach([\App\Enums\PaymentMethod::Cash, \App\Enums\PaymentMethod::Card, \App\Enums\PaymentMethod::Bkash, \App\Enums\PaymentMethod::Nagad, \App\Enums\PaymentMethod::Rocket] as $method)
                                        <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                                <input name="payment_amount" value="{{ old('payment_amount') }}" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" placeholder="Amount ({{ $cartProducts->first()?->currency ?? \App\Facades\Settings::get('shop.currency', 'USD') }}), up to {{ $cartProducts->first()?->currency ?? \App\Facades\Settings::get('shop.currency', 'USD') }} {{ number_format($cartSubtotal / 100, 2) }}" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            </div>
                            <input name="payment_reference" value="{{ old('payment_reference') }}" maxlength="120" placeholder="Transaction reference (required for card/mobile banking)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <input name="payment_note" value="{{ old('payment_note') }}" maxlength="500" placeholder="Payment submission note (optional)" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        </div>
                        <label class="flex items-start gap-2 text-xs leading-5 text-[#4B554D]"><input type="checkbox" name="confirmed" value="1" required class="mt-0.5 rounded border-gray-300 text-[#245C45] focus:ring-[#245C45]"><span>I reviewed the customer and cart details and want to create this order.</span></label>
                        <button @disabled($cartProducts->count() !== count($cart)) class="w-full rounded-xl bg-[#245C45] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#173F32] disabled:cursor-not-allowed disabled:bg-gray-300">Create order</button>
                    </form>
                @else
                    <div class="mt-4 rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-[#69736B]">Add an available product to begin a sale.</div>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
