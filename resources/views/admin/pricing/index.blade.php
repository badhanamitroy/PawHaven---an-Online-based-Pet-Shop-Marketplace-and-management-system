@extends('layouts.admin')

@section('title', 'Pricing & Promotions')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Pricing &amp; Promotions</h1>
        <p class="mt-1 text-sm text-[#69736B]">Company prices, optional branch overrides, scheduled campaigns and coupon codes. All amounts use {{ $currency }}.</p>
    </header>

    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"><p class="font-semibold">Please review the pricing details.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-[#173F32]">Branch price overrides</h2>
        <p class="mt-1 text-xs text-[#69736B]">Company product prices remain on the product record. An active override replaces the company selling price only at the selected branch.</p>
        <form method="POST" action="{{ route('admin.pricing.branch-prices.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">@csrf
            <label class="text-xs font-semibold text-gray-700">Product<select name="product_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">Choose product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-gray-700">Branch<select name="branch_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-gray-700">Selling price ({{ $currency }})<input name="price" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" value="{{ old('price') }}" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="0.00"></label>
            <button class="self-end rounded-lg bg-[#245C45] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#173F32]">Save override</button>
        </form>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[520px] text-left text-xs"><thead><tr class="border-b text-gray-500"><th class="py-2">Product</th><th>Branch</th><th>Price</th><th></th></tr></thead><tbody>
                @forelse($branchPrices as $price)<tr class="border-b border-gray-100"><td class="py-2 font-medium">{{ $price->product?->name ?? 'Product removed' }} <span class="text-gray-400">{{ $price->product?->sku }}</span></td><td>{{ $price->branch?->name ?? 'Branch removed' }}</td><td>{{ $price->currency }} {{ number_format($price->price_minor / 100, 2) }}</td><td class="text-right"><form method="POST" action="{{ route('admin.pricing.branch-prices.destroy', $price) }}">@csrf @method('DELETE')<button class="text-rose-700 hover:underline">Remove override</button></form></td></tr>
                @empty<tr><td colspan="4" class="py-4 text-center text-gray-500">No branch price overrides configured.</td></tr>@endforelse
            </tbody></table>
            <div class="mt-3">{{ $branchPrices->links() }}</div>
        </div>
    </section>

    <section class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-[#173F32]">Create promotion</h2>
        <p class="mt-1 text-xs text-[#69736B]">Only one promotion is selected per cart: highest priority first, then oldest ID. Coupons can combine only when this campaign permits it.</p>
        <form method="POST" action="{{ route('admin.pricing.promotions.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf
            <label class="text-xs font-semibold text-gray-700">Name<input name="name" value="{{ old('name') }}" maxlength="160" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Branch scope<select name="branch_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">Entire company</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-gray-700">Channel<select name="sales_channel" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="both">Online and POS</option><option value="online">Online only</option><option value="pos">POS only</option></select></label>
            <label class="text-xs font-semibold text-gray-700">Discount type<select name="discount_type" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="percentage">Percentage</option><option value="fixed">Fixed {{ $currency }} amount</option></select></label>
            <label class="text-xs font-semibold text-gray-700">Discount value<input name="discount_value" value="{{ old('discount_value') }}" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="10.00 (10% or {{ $currency }} 10.00)"></label>
            <label class="text-xs font-semibold text-gray-700">Minimum eligible purchase ({{ $currency }})<input name="minimum_purchase" value="{{ old('minimum_purchase', '0.00') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="0.00"></label>
            <label class="text-xs font-semibold text-gray-700">Maximum discount (optional, {{ $currency }})<input name="maximum_discount" value="{{ old('maximum_discount') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="No cap"></label>
            <label class="text-xs font-semibold text-gray-700">Priority<input type="number" name="priority" min="0" max="65535" value="{{ old('priority', 0) }}" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Usage limit (optional)<input type="number" name="usage_limit" min="1" value="{{ old('usage_limit') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Unlimited"></label>
            <label class="text-xs font-semibold text-gray-700">Starts at<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Ends at<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Products (empty means any)<select multiple name="product_ids[]" class="mt-1 h-24 w-full rounded-lg border-gray-300 text-sm">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-gray-700">Categories (empty means any)<select multiple name="category_ids[]" class="mt-1 h-24 w-full rounded-lg border-gray-300 text-sm">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-gray-700">Description<textarea name="description" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ old('description') }}</textarea></label>
            <div class="flex flex-col gap-2 text-xs md:col-span-3 sm:flex-row sm:items-center">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="stack_with_coupon" value="1" class="rounded border-gray-300">Allow one coupon after this promotion</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="rounded border-gray-300">Activate immediately</label>
                <button class="rounded-lg bg-[#245C45] px-4 py-2.5 font-semibold text-white hover:bg-[#173F32] sm:ml-auto">Create promotion</button>
            </div>
        </form>
        <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[700px] text-left text-xs"><thead><tr class="border-b text-gray-500"><th class="py-2">Campaign</th><th>Scope / channel</th><th>Discount</th><th>Usage</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($promotions as $promotion)<tr class="border-b border-gray-100"><td class="py-2 font-semibold">{{ $promotion->name }}<div class="font-normal text-gray-500">Priority {{ $promotion->priority }} · {{ $promotion->starts_at?->format('Y-m-d H:i') ?? 'No start' }} to {{ $promotion->ends_at?->format('Y-m-d H:i') ?? 'No end' }}</div>@include('admin.pricing._promotion-edit', ['promotion' => $promotion])</td><td>{{ $promotion->branch_id ? ($branches->firstWhere('id', $promotion->branch_id)?->name ?? 'Branch') : 'Company' }} · {{ strtoupper($promotion->sales_channel) }}</td><td>{{ $promotion->discount_type === 'percentage' ? number_format($promotion->discount_value / 100, 2).'%' : $promotion->currency.' '.number_format($promotion->discount_value / 100, 2) }}</td><td>{{ $promotionUsage[$promotion->id] ?? 0 }} / {{ $promotion->usage_limit ?? '∞' }}</td><td>{{ $promotion->is_active ? 'Active' : 'Inactive' }}</td><td class="text-right"><form method="POST" action="{{ route('admin.pricing.promotions.toggle', $promotion) }}">@csrf @method('PATCH')<button class="text-[#245C45] hover:underline">{{ $promotion->is_active ? 'Deactivate' : 'Activate' }}</button></form></td></tr>
            @empty<tr><td colspan="6" class="py-4 text-center text-gray-500">No promotions created.</td></tr>@endforelse
        </tbody></table><div class="mt-3">{{ $promotions->links() }}</div></div>
    </section>

    <section class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-[#173F32]">Create coupon</h2>
        <p class="mt-1 text-xs text-[#69736B]">Coupon codes are normalized to uppercase. Usage is recorded only in the same transaction that creates a successful order.</p>
        <form method="POST" action="{{ route('admin.pricing.coupons.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf
            <label class="text-xs font-semibold text-gray-700">Code<input name="code" value="{{ old('code') }}" maxlength="48" required class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase" placeholder="WELCOME10"></label>
            <label class="text-xs font-semibold text-gray-700">Name<input name="name" value="{{ old('name') }}" maxlength="160" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Branch scope<select name="branch_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">Entire company</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-gray-700">Channel<select name="sales_channel" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="both">Online and POS</option><option value="online">Online only</option><option value="pos">POS only</option></select></label>
            <label class="text-xs font-semibold text-gray-700">Discount type<select name="discount_type" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="percentage">Percentage</option><option value="fixed">Fixed {{ $currency }} amount</option></select></label>
            <label class="text-xs font-semibold text-gray-700">Discount value<input name="discount_value" value="{{ old('discount_value') }}" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="10.00"></label>
            <label class="text-xs font-semibold text-gray-700">Minimum purchase ({{ $currency }})<input name="minimum_purchase" value="{{ old('minimum_purchase', '0.00') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Maximum discount (optional, {{ $currency }})<input name="maximum_discount" value="{{ old('maximum_discount') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Global usage limit<input type="number" name="usage_limit" min="1" value="{{ old('usage_limit') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Unlimited"></label>
            <label class="text-xs font-semibold text-gray-700">Per-customer usage limit<input type="number" name="per_customer_limit" min="1" value="{{ old('per_customer_limit') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Unlimited"></label>
            <label class="text-xs font-semibold text-gray-700">Starts at<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Ends at<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-700">Products (empty means any)<select multiple name="product_ids[]" class="mt-1 h-24 w-full rounded-lg border-gray-300 text-sm">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-gray-700">Categories (empty means any)<select multiple name="category_ids[]" class="mt-1 h-24 w-full rounded-lg border-gray-300 text-sm">@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
            <label class="inline-flex items-center gap-2 self-end pb-2 text-xs"><input type="checkbox" name="is_active" value="1" class="rounded border-gray-300">Activate immediately</label>
            <button class="self-end rounded-lg bg-[#245C45] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#173F32]">Create coupon</button>
        </form>
        <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[700px] text-left text-xs"><thead><tr class="border-b text-gray-500"><th class="py-2">Code</th><th>Scope / channel</th><th>Discount</th><th>Usage</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($coupons as $coupon)<tr class="border-b border-gray-100"><td class="py-2 font-semibold">{{ $coupon->code }}<div class="font-normal text-gray-500">{{ $coupon->name }}</div>@include('admin.pricing._coupon-edit', ['coupon' => $coupon])</td><td>{{ $coupon->branch_id ? ($branches->firstWhere('id', $coupon->branch_id)?->name ?? 'Branch') : 'Company' }} · {{ strtoupper($coupon->sales_channel) }}</td><td>{{ $coupon->discount_type === 'percentage' ? number_format($coupon->discount_value / 100, 2).'%' : $coupon->currency.' '.number_format($coupon->discount_value / 100, 2) }}</td><td>{{ $couponUsage[$coupon->id] ?? 0 }} / {{ $coupon->usage_limit ?? '∞' }}</td><td>{{ $coupon->is_active ? 'Active' : 'Inactive' }}</td><td class="text-right"><form method="POST" action="{{ route('admin.pricing.coupons.toggle', $coupon) }}">@csrf @method('PATCH')<button class="text-[#245C45] hover:underline">{{ $coupon->is_active ? 'Deactivate' : 'Activate' }}</button></form></td></tr>
            @empty<tr><td colspan="6" class="py-4 text-center text-gray-500">No coupons created.</td></tr>@endforelse
        </tbody></table><div class="mt-3">{{ $coupons->links() }}</div></div>
    </section>

    <section class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-[#173F32]">Recent discount usage</h2>
        <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[850px] text-left text-xs"><thead><tr class="border-b text-gray-500"><th class="py-2">Order</th><th>Channel</th><th>Coupon</th><th>Gross merchandise</th><th>Subtotal after discount</th><th>Promotion discount</th><th>Coupon discount</th><th>Date</th></tr></thead><tbody>
            @forelse($redemptions as $redemption)<tr class="border-b border-gray-100"><td class="py-2">{{ $redemption->order?->order_number ?? 'Order removed' }}</td><td>{{ strtoupper($redemption->order?->sales_channel?->value ?? '') }}</td><td>{{ $redemption->coupon_code_snapshot ?? '—' }}</td><td>{{ $redemption->currency }} {{ number_format((($redemption->order?->subtotal_minor ?? 0) + $redemption->promotion_discount_minor + $redemption->coupon_discount_minor) / 100, 2) }}</td><td>{{ $redemption->currency }} {{ number_format(($redemption->order?->subtotal_minor ?? 0) / 100, 2) }}</td><td>{{ $redemption->currency }} {{ number_format($redemption->promotion_discount_minor / 100, 2) }}</td><td>{{ $redemption->currency }} {{ number_format($redemption->coupon_discount_minor / 100, 2) }}</td><td>{{ $redemption->created_at?->format('Y-m-d H:i') }}</td></tr>
            @empty<tr><td colspan="6" class="py-4 text-center text-gray-500">No completed coupon or promotion redemptions yet.</td></tr>@endforelse
        </tbody></table></div>
        <p class="mt-3 text-[11px] text-[#69736B]">Discount figures are order values, not collected revenue. Use verified payment and refund reports for cash movement.</p>
    </section>
</div>
@endsection
