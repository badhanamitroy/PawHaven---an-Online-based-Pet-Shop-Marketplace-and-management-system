<details class="mt-2 text-left">
    <summary class="cursor-pointer text-xs font-semibold text-[#245C45]">Edit promotion</summary>
    <form method="POST" action="{{ route('admin.pricing.promotions.update', $promotion) }}" class="mt-3 grid gap-3 rounded-xl bg-gray-50 p-3 md:grid-cols-3">@csrf @method('PATCH')
        <label class="text-xs">Name<input name="name" value="{{ $promotion->name }}" maxlength="160" required class="mt-1 w-full rounded border-gray-300 text-sm"></label>
        <label class="text-xs">Branch<select name="branch_id" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="">Entire company</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($promotion->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
        <label class="text-xs">Channel<select name="sales_channel" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="both" @selected($promotion->sales_channel === 'both')>Online and POS</option><option value="online" @selected($promotion->sales_channel === 'online')>Online only</option><option value="pos" @selected($promotion->sales_channel === 'pos')>POS only</option></select></label>
        <label class="text-xs">Discount type<select name="discount_type" class="mt-1 w-full rounded border-gray-300 text-sm"><option value="percentage" @selected($promotion->discount_type === 'percentage')>Percentage</option><option value="fixed" @selected($promotion->discount_type === 'fixed')>Fixed {{ $currency }}</option></select></label>
        <label class="text-xs">Discount value<input name="discount_value" value="{{ $promotion->discount_type === 'percentage' ? number_format($promotion->discount_value / 100, 2, '.', '') : number_format($promotion->discount_value / 100, 2, '.', '') }}" maxlength="11" required class="mt-1 w-full rounded border-gray-300 text-sm"></label>
        <label class="text-xs">Minimum purchase<input name="minimum_purchase" value="{{ number_format($promotion->minimum_purchase_minor / 100, 2, '.', '') }}" class="mt-1 w-full rounded border-gray-300 text-sm"></label>
        <label class="text-xs">Maximum discount<input name="maximum_discount" value="{{ $promotion->maximum_discount_minor === null ? '' : number_format($promotion->maximum_discount_minor / 100, 2, '.', '') }}" class="mt-1 w-full rounded border-gray-300 text-sm"></label>
        <label class="text-xs">Priority<input type="number" name="priority" min="0" max="65535" value="{{ $promotion->priority }}" required class="mt-1 w-full rounded border-gray-300 text-sm"></label>
        <label class="text-xs">Usage limit<input type="number" name="usage_limit" min="1" value="{{ $promotion->usage_limit }}" class="mt-1 w-full rounded border-gray-300 text-sm"></label>
        <label class="text-xs">Starts at<input type="datetime-local" name="starts_at" value="{{ $promotion->starts_at?->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded border-gray-300 text-sm"></label>
        <label class="text-xs">Ends at<input type="datetime-local" name="ends_at" value="{{ $promotion->ends_at?->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded border-gray-300 text-sm"></label>
        <label class="text-xs">Products (empty = any)<select multiple name="product_ids[]" class="mt-1 h-20 w-full rounded border-gray-300 text-sm">@foreach($products as $product)<option value="{{ $product->id }}" @selected($promotion->products->contains('id', $product->id))>{{ $product->name }}</option>@endforeach</select></label>
        <label class="text-xs">Categories (empty = any)<select multiple name="category_ids[]" class="mt-1 h-20 w-full rounded border-gray-300 text-sm">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($promotion->categories->contains('id', $category->id))>{{ $category->name }}</option>@endforeach</select></label>
        <label class="text-xs">Description<textarea name="description" rows="2" maxlength="2000" class="mt-1 w-full rounded border-gray-300 text-sm">{{ $promotion->description }}</textarea></label>
        <div class="flex flex-wrap items-center gap-4 text-xs md:col-span-3">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="stack_with_coupon" value="1" @checked($promotion->stack_with_coupon)>Allow coupon stacking</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($promotion->is_active)>Active</label>
            <button class="rounded bg-[#245C45] px-3 py-2 font-semibold text-white">Save promotion changes</button>
        </div>
    </form>
</details>
