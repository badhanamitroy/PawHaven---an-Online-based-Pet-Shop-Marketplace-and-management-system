<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\BranchProductPrice;
use App\Models\Coupon;
use App\Models\DiscountRedemption;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\User;
use App\Services\BusinessAccessService;
use App\Services\CurrencyPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PricingManagementController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $this->ownerCompany($request->user());
        $promotions = Promotion::query()->withoutGlobalScopes()->where('company_id', $companyId)
            ->with(['products:id,name', 'categories:id,name'])->latest()->paginate(10, ['*'], 'promotions_page');
        $coupons = Coupon::query()->withoutGlobalScopes()->where('company_id', $companyId)
            ->with(['products:id,name', 'categories:id,name'])->latest()->paginate(10, ['*'], 'coupons_page');
        $redemptions = DiscountRedemption::query()->whereHas('order', fn ($query) => $query->where('company_id', $companyId))
            ->with('order:id,order_number,sales_channel,subtotal_minor,total_minor,currency')
            ->latest()->limit(50)->get();
        $branches = Branch::query()->withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $products = Product::query()->withoutGlobalScopes()->where('company_id', $companyId)->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'sku']);
        $categories = ProductCategory::query()->withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $branchPrices = BranchProductPrice::query()->withoutGlobalScopes()->where('company_id', $companyId)
            ->with(['product:id,name,sku', 'branch:id,name'])->latest()->paginate(10, ['*'], 'prices_page');
        $promotionUsage = DiscountRedemption::query()->whereNotNull('promotion_id')->groupBy('promotion_id')
            ->selectRaw('promotion_id, COUNT(*) as redeemed')->pluck('redeemed', 'promotion_id');
        $couponUsage = DiscountRedemption::query()->whereNotNull('coupon_id')->groupBy('coupon_id')
            ->selectRaw('coupon_id, COUNT(*) as redeemed')->pluck('redeemed', 'coupon_id');
        $currency = app(CurrencyPolicy::class)->current();

        return view('admin.pricing.index', compact('promotions', 'coupons', 'redemptions', 'branches', 'products', 'categories', 'branchPrices', 'currency', 'promotionUsage', 'couponUsage'));
    }

    public function storePromotion(Request $request): RedirectResponse
    {
        $companyId = $this->ownerCompany($request->user());
        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'minimum_purchase' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'maximum_discount' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'priority' => ['required', 'integer', 'min:0', 'max:65535'],
            'sales_channel' => ['required', Rule::in(['both', 'online', 'pos'])],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'stack_with_coupon' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
        ])->validate();
        $this->assertDateRange($validated);
        $branchId = $this->validatedBranch($validated['branch_id'] ?? null, $companyId);
        $productIds = $this->validateCatalogueIds($validated['product_ids'] ?? [], Product::class, $companyId);
        $categoryIds = $this->validateCatalogueIds($validated['category_ids'] ?? [], ProductCategory::class, $companyId);
        $discountValue = $validated['discount_type'] === 'percentage'
            ? $this->decimalMinor($validated['discount_value'], true)
            : $this->decimalMinor($validated['discount_value']);
        if ($validated['discount_type'] === 'percentage' && ($discountValue < 1 || $discountValue > 10000)) {
            throw ValidationException::withMessages(['discount_value' => 'Percentage discounts must be greater than 0 and at most 100.00%.']);
        }
        $currency = app(CurrencyPolicy::class)->current();

        $promotion = DB::transaction(function () use ($validated, $companyId, $branchId, $productIds, $categoryIds, $discountValue, $currency): Promotion {
            $promotion = Promotion::query()->withoutGlobalScopes()->create([
                'company_id' => $companyId, 'branch_id' => $branchId, 'name' => $validated['name'],
                'description' => $validated['description'] ?? null, 'discount_type' => $validated['discount_type'],
                'discount_value' => $discountValue,
                'minimum_purchase_minor' => $this->decimalMinor($validated['minimum_purchase'] ?? '0'),
                'maximum_discount_minor' => isset($validated['maximum_discount']) && $validated['maximum_discount'] !== ''
                    ? $this->decimalMinor($validated['maximum_discount']) : null,
                'priority' => (int) $validated['priority'], 'sales_channel' => $validated['sales_channel'],
                'stack_with_coupon' => (bool) ($validated['stack_with_coupon'] ?? false),
                'usage_limit' => $validated['usage_limit'] ?? null, 'is_active' => (bool) ($validated['is_active'] ?? false),
                'starts_at' => $validated['starts_at'] ?? null, 'ends_at' => $validated['ends_at'] ?? null, 'currency' => $currency,
            ]);
            $promotion->products()->sync($productIds);
            $promotion->categories()->sync($categoryIds);

            return $promotion;
        });
        AdminAuditLog::record('pricing.promotion.created', 'Created a product promotion.', [
            'company_id' => $companyId, 'branch_id' => $branchId, 'promotion_id' => $promotion->id,
            'discount_type' => $promotion->discount_type, 'discount_value' => $promotion->discount_value,
            'currency' => $promotion->currency, 'sales_channel' => $promotion->sales_channel,
            'priority' => $promotion->priority, 'usage_limit' => $promotion->usage_limit,
            'starts_at' => $promotion->starts_at?->toIso8601String(), 'ends_at' => $promotion->ends_at?->toIso8601String(),
            'product_ids' => $productIds, 'category_ids' => $categoryIds,
        ]);

        return back()->with('success', 'Promotion created.');
    }

    public function updatePromotion(Request $request, int $promotion): RedirectResponse
    {
        $companyId = $this->ownerCompany($request->user());
        $campaign = Promotion::query()->withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($promotion);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'minimum_purchase' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'maximum_discount' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'priority' => ['required', 'integer', 'min:0', 'max:65535'], 'sales_channel' => ['required', Rule::in(['both', 'online', 'pos'])],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'],
            'stack_with_coupon' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'], 'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'], 'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
        ]);
        $this->assertDateRange($validated);
        $branchId = $this->validatedBranch($validated['branch_id'] ?? null, $companyId);
        $productIds = $this->validateCatalogueIds($validated['product_ids'] ?? [], Product::class, $companyId);
        $categoryIds = $this->validateCatalogueIds($validated['category_ids'] ?? [], ProductCategory::class, $companyId);
        $discountValue = $validated['discount_type'] === 'percentage'
            ? $this->decimalMinor($validated['discount_value'], true) : $this->decimalMinor($validated['discount_value']);
        if ($validated['discount_type'] === 'percentage' && ($discountValue < 1 || $discountValue > 10000)) {
            throw ValidationException::withMessages(['discount_value' => 'Percentage discounts must be greater than 0 and at most 100.00%.']);
        }
        $oldValues = $campaign->only(['name', 'branch_id', 'discount_type', 'discount_value', 'sales_channel', 'priority', 'is_active']);
        DB::transaction(function () use ($campaign, $validated, $branchId, $productIds, $categoryIds, $discountValue): void {
            $locked = Promotion::query()->withoutGlobalScopes()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'name' => $validated['name'], 'description' => $validated['description'] ?? null, 'branch_id' => $branchId,
                'discount_type' => $validated['discount_type'], 'discount_value' => $discountValue,
                'minimum_purchase_minor' => $this->decimalMinor($validated['minimum_purchase'] ?? '0'),
                'maximum_discount_minor' => isset($validated['maximum_discount']) && $validated['maximum_discount'] !== ''
                    ? $this->decimalMinor($validated['maximum_discount']) : null,
                'priority' => (int) $validated['priority'], 'sales_channel' => $validated['sales_channel'],
                'stack_with_coupon' => (bool) ($validated['stack_with_coupon'] ?? false),
                'usage_limit' => $validated['usage_limit'] ?? null, 'is_active' => (bool) ($validated['is_active'] ?? false),
                'starts_at' => $validated['starts_at'] ?? null, 'ends_at' => $validated['ends_at'] ?? null,
            ]);
            $locked->products()->sync($productIds);
            $locked->categories()->sync($categoryIds);
        });
        AdminAuditLog::record('pricing.promotion.updated', 'Updated a product promotion.', [
            'company_id' => $companyId, 'branch_id' => $branchId, 'promotion_id' => $campaign->id,
            'old' => $oldValues, 'new' => ['name' => $validated['name'], 'discount_type' => $validated['discount_type'],
                'discount_value' => $discountValue, 'sales_channel' => $validated['sales_channel'], 'product_ids' => $productIds, 'category_ids' => $categoryIds],
        ]);

        return back()->with('success', 'Promotion updated.');
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $companyId = $this->ownerCompany($request->user());
        $code = strtoupper(trim((string) $request->input('code')));
        $request->merge(['code' => $code]);
        $validated = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:48', 'regex:/^[A-Z0-9_-]+$/', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:160'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'minimum_purchase' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'maximum_discount' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'sales_channel' => ['required', Rule::in(['both', 'online', 'pos'])],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'], 'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'], 'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
        ])->validate();
        $this->assertDateRange($validated);
        $branchId = $this->validatedBranch($validated['branch_id'] ?? null, $companyId);
        $productIds = $this->validateCatalogueIds($validated['product_ids'] ?? [], Product::class, $companyId);
        $categoryIds = $this->validateCatalogueIds($validated['category_ids'] ?? [], ProductCategory::class, $companyId);
        $discountValue = $validated['discount_type'] === 'percentage'
            ? $this->decimalMinor($validated['discount_value'], true)
            : $this->decimalMinor($validated['discount_value']);
        if ($validated['discount_type'] === 'percentage' && ($discountValue < 1 || $discountValue > 10000)) {
            throw ValidationException::withMessages(['discount_value' => 'Percentage discounts must be greater than 0 and at most 100.00%.']);
        }
        $currency = app(CurrencyPolicy::class)->current();

        $coupon = DB::transaction(function () use ($validated, $companyId, $branchId, $productIds, $categoryIds, $discountValue, $currency): Coupon {
            $coupon = Coupon::query()->withoutGlobalScopes()->create([
                'company_id' => $companyId, 'branch_id' => $branchId, 'code' => $validated['code'], 'name' => $validated['name'],
                'discount_type' => $validated['discount_type'], 'discount_value' => $discountValue,
                'minimum_purchase_minor' => $this->decimalMinor($validated['minimum_purchase'] ?? '0'),
                'maximum_discount_minor' => isset($validated['maximum_discount']) && $validated['maximum_discount'] !== ''
                    ? $this->decimalMinor($validated['maximum_discount']) : null,
                'usage_limit' => $validated['usage_limit'] ?? null, 'per_customer_limit' => $validated['per_customer_limit'] ?? null,
                'sales_channel' => $validated['sales_channel'], 'is_active' => (bool) ($validated['is_active'] ?? false),
                'starts_at' => $validated['starts_at'] ?? null, 'ends_at' => $validated['ends_at'] ?? null, 'currency' => $currency,
            ]);
            $coupon->products()->sync($productIds);
            $coupon->categories()->sync($categoryIds);

            return $coupon;
        });
        AdminAuditLog::record('pricing.coupon.created', 'Created a checkout coupon.', [
            'company_id' => $companyId, 'branch_id' => $branchId, 'coupon_id' => $coupon->id, 'code' => $coupon->code,
            'discount_type' => $coupon->discount_type, 'discount_value' => $coupon->discount_value,
            'currency' => $coupon->currency, 'sales_channel' => $coupon->sales_channel,
            'usage_limit' => $coupon->usage_limit, 'per_customer_limit' => $coupon->per_customer_limit,
            'starts_at' => $coupon->starts_at?->toIso8601String(), 'ends_at' => $coupon->ends_at?->toIso8601String(),
            'product_ids' => $productIds, 'category_ids' => $categoryIds,
        ]);

        return back()->with('success', 'Coupon created.');
    }

    public function updateCoupon(Request $request, int $coupon): RedirectResponse
    {
        $companyId = $this->ownerCompany($request->user());
        $record = Coupon::query()->withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($coupon);
        $normalizedCode = strtoupper(trim((string) $request->input('code')));
        $request->merge(['code' => $normalizedCode]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:48', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($record->id)],
            'name' => ['required', 'string', 'max:160'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'minimum_purchase' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'maximum_discount' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'], 'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'sales_channel' => ['required', Rule::in(['both', 'online', 'pos'])], 'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'], 'is_active' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'], 'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'], 'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
        ]);
        $this->assertDateRange($validated);
        $branchId = $this->validatedBranch($validated['branch_id'] ?? null, $companyId);
        $productIds = $this->validateCatalogueIds($validated['product_ids'] ?? [], Product::class, $companyId);
        $categoryIds = $this->validateCatalogueIds($validated['category_ids'] ?? [], ProductCategory::class, $companyId);
        $discountValue = $validated['discount_type'] === 'percentage'
            ? $this->decimalMinor($validated['discount_value'], true) : $this->decimalMinor($validated['discount_value']);
        if ($validated['discount_type'] === 'percentage' && ($discountValue < 1 || $discountValue > 10000)) {
            throw ValidationException::withMessages(['discount_value' => 'Percentage discounts must be greater than 0 and at most 100.00%.']);
        }
        $oldValues = $record->only(['code', 'name', 'branch_id', 'discount_type', 'discount_value', 'sales_channel', 'is_active']);
        DB::transaction(function () use ($record, $validated, $branchId, $productIds, $categoryIds, $discountValue): void {
            $locked = Coupon::query()->withoutGlobalScopes()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'code' => $validated['code'], 'name' => $validated['name'], 'branch_id' => $branchId,
                'discount_type' => $validated['discount_type'], 'discount_value' => $discountValue,
                'minimum_purchase_minor' => $this->decimalMinor($validated['minimum_purchase'] ?? '0'),
                'maximum_discount_minor' => isset($validated['maximum_discount']) && $validated['maximum_discount'] !== ''
                    ? $this->decimalMinor($validated['maximum_discount']) : null,
                'usage_limit' => $validated['usage_limit'] ?? null, 'per_customer_limit' => $validated['per_customer_limit'] ?? null,
                'sales_channel' => $validated['sales_channel'], 'is_active' => (bool) ($validated['is_active'] ?? false),
                'starts_at' => $validated['starts_at'] ?? null, 'ends_at' => $validated['ends_at'] ?? null,
            ]);
            $locked->products()->sync($productIds);
            $locked->categories()->sync($categoryIds);
        });
        AdminAuditLog::record('pricing.coupon.updated', 'Updated a checkout coupon.', [
            'company_id' => $companyId, 'branch_id' => $branchId, 'coupon_id' => $record->id,
            'old' => $oldValues, 'new' => ['code' => $validated['code'], 'discount_type' => $validated['discount_type'],
                'discount_value' => $discountValue, 'sales_channel' => $validated['sales_channel'], 'product_ids' => $productIds, 'category_ids' => $categoryIds],
        ]);

        return back()->with('success', 'Coupon updated.');
    }

    public function setPromotionActive(Request $request, int $promotion): RedirectResponse
    {
        $companyId = $this->ownerCompany($request->user());
        $campaign = Promotion::query()->withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($promotion);
        $campaign->forceFill(['is_active' => ! $campaign->is_active])->save();
        AdminAuditLog::record('pricing.promotion.toggled', 'Changed promotion availability.', [
            'company_id' => $companyId, 'branch_id' => $campaign->branch_id, 'promotion_id' => $campaign->id, 'is_active' => $campaign->is_active,
        ]);

        return back()->with('success', 'Promotion availability updated.');
    }

    public function setCouponActive(Request $request, int $coupon): RedirectResponse
    {
        $companyId = $this->ownerCompany($request->user());
        $code = Coupon::query()->withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($coupon);
        $code->forceFill(['is_active' => ! $code->is_active])->save();
        AdminAuditLog::record('pricing.coupon.toggled', 'Changed coupon availability.', [
            'company_id' => $companyId, 'branch_id' => $code->branch_id, 'coupon_id' => $code->id, 'is_active' => $code->is_active,
        ]);

        return back()->with('success', 'Coupon availability updated.');
    }

    public function storeBranchPrice(Request $request): RedirectResponse
    {
        $companyId = $this->ownerCompany($request->user());
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'price' => ['required', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
        ]);
        $price = $this->decimalMinor($validated['price']);
        [$row, $oldPrice, $currency] = DB::transaction(function () use ($validated, $companyId, $price): array {
            $product = Product::query()->withoutGlobalScopes()->where('company_id', $companyId)
                ->whereNull('archived_at')->whereKey($validated['product_id'])->lockForUpdate()->firstOrFail();
            $branch = Branch::query()->withoutGlobalScopes()->where('company_id', $companyId)
                ->where('is_active', true)->whereKey($validated['branch_id'])->lockForUpdate()->firstOrFail();
            $row = BranchProductPrice::query()->withoutGlobalScopes()->where('product_id', $product->id)
                ->where('branch_id', $branch->id)->lockForUpdate()->first();
            $oldPrice = $row?->price_minor;
            $currency = strtoupper($product->currency);
            if ($row) {
                $row->update(['price_minor' => $price, 'currency' => $currency]);
            } else {
                $row = BranchProductPrice::query()->withoutGlobalScopes()->create([
                    'product_id' => $product->id, 'company_id' => $companyId, 'branch_id' => $branch->id,
                    'price_minor' => $price, 'currency' => $currency,
                ]);
            }

            return [$row, $oldPrice, $currency];
        });
        $branch = Branch::query()->withoutGlobalScopes()->findOrFail($row->branch_id);
        $product = Product::query()->withoutGlobalScopes()->findOrFail($row->product_id);
        AdminAuditLog::record('pricing.branch_price.changed', 'Changed branch-specific product pricing.', [
            'company_id' => $companyId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'branch_price_id' => $row->id,
            'old_price_minor' => $oldPrice, 'new_price_minor' => $price, 'currency' => $currency,
        ]);

        return back()->with('success', 'Branch price saved.');
    }

    public function deleteBranchPrice(Request $request, int $branchProductPrice): RedirectResponse
    {
        $companyId = $this->ownerCompany($request->user());
        $price = BranchProductPrice::query()->withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($branchProductPrice);
        $price->delete();
        AdminAuditLog::record('pricing.branch_price.removed', 'Removed a branch-specific product price.', [
            'company_id' => $companyId, 'branch_id' => $price->branch_id, 'product_id' => $price->product_id,
            'old_price_minor' => $price->price_minor, 'currency' => $price->currency,
        ]);

        return back()->with('success', 'Branch price override removed.');
    }

    private function ownerCompany(?User $user): int
    {
        abort_unless($user?->isStaff(), 403);
        $memberships = app(BusinessAccessService::class)->activeMemberships($user);
        $owner = $memberships->first(fn ($membership): bool => $membership->role === BusinessAccessService::OWNER && $membership->branch_id === null);
        abort_unless($owner, 403, 'Only the Shop Owner can manage company prices and campaigns.');
        app(BusinessAccessService::class)->installationCompany((int) $owner->company_id);

        return (int) $owner->company_id;
    }

    private function validatedBranch(mixed $branchId, int $companyId): ?int
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }
        $branch = Branch::query()->withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->find($branchId);
        if (! $branch) {
            throw ValidationException::withMessages(['branch_id' => 'Choose an active branch in your company.']);
        }

        return (int) $branch->id;
    }

    private function validateCatalogueIds(array $ids, string $model, int $companyId): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $valid = $model::query()->withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages(['product_ids' => 'All selected products and categories must belong to your company.']);
        }

        return $valid;
    }

    private function assertDateRange(array $validated): void
    {
        if (! empty($validated['starts_at']) && ! empty($validated['ends_at'])
            && Carbon::parse($validated['ends_at'])->lessThanOrEqualTo(Carbon::parse($validated['starts_at']))) {
            throw ValidationException::withMessages(['ends_at' => 'The end time must be later than the start time.']);
        }
    }

    private function decimalMinor(string $value, bool $percentage = false): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/', $value, $matches)) {
            throw ValidationException::withMessages(['discount_value' => 'Enter an amount with no more than two decimal places.']);
        }
        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');
        $minor = ($whole * 100) + $fraction;
        if ($minor > 4_294_967_295) {
            throw ValidationException::withMessages(['discount_value' => 'This value exceeds the supported amount range.']);
        }

        return $minor;
    }
}
