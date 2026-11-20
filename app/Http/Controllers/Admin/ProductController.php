<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\BusinessAccessService;
use App\Services\InventoryMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $query = Product::with('category');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('product_category_id', $request->input('category'));
        }

        if ($request->filled('stock_status')) {
            $status = $request->input('stock_status');
            if ($status === 'out_of_stock') {
                $query->where('stock_quantity', '<=', 0);
            } elseif ($status === 'low_stock') {
                $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                    ->where('stock_quantity', '>', 0);
            } elseif ($status === 'in_stock') {
                $query->whereColumn('stock_quantity', '>', 'low_stock_threshold');
            }
        }

        $products = $query->latest()->paginate(15)->withQueryString();
        $categories = ProductCategory::orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::query()->with('company')->where('is_active', true)->orderBy('name')->get();

        return view('admin.products.create', compact('categories', 'branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/'],
            'sale_price' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
            'ingredients' => ['nullable', 'string', 'max:1000'],
            'storage_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $category = $this->assertVisibleCategory((int) $validated['product_category_id']);
        $branchId = isset($validated['branch_id']) && $validated['branch_id'] !== ''
            ? (int) $validated['branch_id']
            : null;
        $this->assertVisibleBranch($branchId, $category->company_id);
        $assignment = app(BusinessAccessService::class)->creationAssignment(
            $request->user(),
            true,
            $category->company_id,
            $branchId,
        );

        $priceMinor = $this->decimalToMinor($validated['price']);
        $salePriceMinor = isset($validated['sale_price']) && $validated['sale_price'] !== ''
            ? $this->decimalToMinor($validated['sale_price'])
            : null;
        if ($salePriceMinor !== null && $salePriceMinor > $priceMinor) {
            throw ValidationException::withMessages(['sale_price' => 'The sale price cannot exceed the regular product price.']);
        }

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $count = 1;
        while (Product::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            if ($path !== false) {
                $imagePath = Storage::url($path);
            }
        }

        $product = DB::transaction(function () use ($request, $validated, $assignment, $slug, $priceMinor, $salePriceMinor, $imagePath): Product {
            $product = Product::create([
                'company_id' => $assignment['company_id'],
                'branch_id' => $assignment['branch_id'],
                'product_category_id' => (int) $validated['product_category_id'],
                'name' => $validated['name'],
                'slug' => $slug,
                'sku' => strtoupper($validated['sku']),
                'description' => $validated['description'] ?? null,
                'price_minor' => $priceMinor,
                'sale_price_minor' => $salePriceMinor,
                'stock_quantity' => (int) $validated['stock_quantity'],
                'low_stock_threshold' => (int) $validated['low_stock_threshold'],
                'is_published' => $request->boolean('is_published'),
                'is_featured' => $request->boolean('is_featured'),
                'image_path' => $imagePath,
                'ingredients' => $validated['ingredients'] ?? null,
                'storage_instructions' => $validated['storage_instructions'] ?? null,
            ]);
            app(InventoryMovementService::class)->recordOpening($product, $request->user());

            return $product;
        });

        AdminAuditLog::record(
            'product.create',
            "Created product '{$product->name}' (SKU: {$product->sku}).",
            [
                'product_id' => $product->id, 'sku' => $product->sku,
                'company_id' => $product->company_id, 'branch_id' => $product->branch_id,
                'price_minor' => $product->price_minor, 'sale_price_minor' => $product->sale_price_minor,
                'currency' => $product->currency,
            ]
        );

        return redirect()->route('admin.products.index')->with('success', "Product '{$product->name}' created successfully.");
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        $categories = ProductCategory::orderBy('name')->get();
        $branches = Branch::query()->with('company')->where('is_active', true)->orderBy('name')->get();

        return view('admin.products.edit', compact('product', 'categories', 'branches'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);
        if ($product->archived_at !== null) {
            return redirect()->route('admin.products.index')->with('error', 'Archived products are read-only to preserve catalogue history.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/'],
            'sale_price' => ['nullable', 'string', 'max:11', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'stock_adjustment_reason' => ['nullable', 'string', 'max:1000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
            'ingredients' => ['nullable', 'string', 'max:1000'],
            'storage_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $category = $this->assertVisibleCategory((int) $validated['product_category_id']);
        if ($category->company_id !== $product->company_id) {
            throw ValidationException::withMessages([
                'product_category_id' => 'A product cannot be moved to another company by changing its category.',
            ]);
        }

        $branchId = isset($validated['branch_id']) && $validated['branch_id'] !== ''
            ? (int) $validated['branch_id']
            : null;
        $this->assertVisibleBranch($branchId, $product->company_id);
        if ($branchId !== $product->branch_id
            && ! $request->user()->isAdmin()
            && ! app(BusinessAccessService::class)->canManageCompany($request->user(), $product->company_id, $branchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Only a company owner or manager can reassign a product to another branch.',
            ]);
        }

        if ($branchId !== $product->branch_id
            && ($product->stock_quantity > 0 || $product->orderItems()->exists() || $product->inventoryMovements()->exists())) {
            throw ValidationException::withMessages([
                'branch_id' => 'Products with stock or movement history cannot change inventory branches until branch transfers are implemented.',
            ]);
        }

        $priceMinor = $this->decimalToMinor($validated['price']);
        $salePriceMinor = isset($validated['sale_price']) && $validated['sale_price'] !== ''
            ? $this->decimalToMinor($validated['sale_price'])
            : null;
        if ($salePriceMinor !== null && $salePriceMinor > $priceMinor) {
            throw ValidationException::withMessages(['sale_price' => 'The sale price cannot exceed the regular product price.']);
        }

        $updateData = [
            'product_category_id' => (int) $validated['product_category_id'],
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'sku' => strtoupper($validated['sku']),
            'description' => $validated['description'] ?? null,
            'price_minor' => $priceMinor,
            'sale_price_minor' => $salePriceMinor,
            'low_stock_threshold' => (int) $validated['low_stock_threshold'],
            'is_published' => $request->boolean('is_published'),
            'is_featured' => $request->boolean('is_featured'),
            'ingredients' => $validated['ingredients'] ?? null,
            'storage_instructions' => $validated['storage_instructions'] ?? null,
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            if ($path !== false) {
                $updateData['image_path'] = Storage::url($path);
            }
        }

        $targetStock = (int) $validated['stock_quantity'];
        $reason = trim((string) ($validated['stock_adjustment_reason'] ?? ''));
        if ($targetStock !== (int) $product->stock_quantity && $reason === '') {
            throw ValidationException::withMessages([
                'stock_adjustment_reason' => 'Explain the stock change so it can be reconciled later.',
            ]);
        }

        $oldPriceMinor = (int) $product->price_minor;
        $oldSalePriceMinor = $product->sale_price_minor === null ? null : (int) $product->sale_price_minor;
        DB::transaction(function () use ($product, $updateData, $targetStock, $reason, $request): void {
            $lockedProduct = Product::query()->withoutGlobalScopes()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $lockedProduct->update($updateData);
            if ($targetStock !== (int) $lockedProduct->stock_quantity) {
                app(InventoryMovementService::class)->adjustToQuantity(
                    $lockedProduct,
                    $request->user(),
                    $targetStock,
                    $reason,
                    (string) Str::uuid(),
                );
            }
        });

        AdminAuditLog::record(
            'product.update',
            "Updated product '{$product->name}' (SKU: {$product->sku}).",
            [
                'product_id' => $product->id, 'company_id' => $product->company_id, 'branch_id' => $product->branch_id,
                'old_price_minor' => $oldPriceMinor, 'new_price_minor' => $priceMinor,
                'old_sale_price_minor' => $oldSalePriceMinor, 'new_sale_price_minor' => $salePriceMinor,
                'currency' => $product->currency,
            ]
        );

        return redirect()->route('admin.products.index')->with('success', "Product '{$product->name}' updated successfully.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        // Check if product has been ordered
        if ($product->orderItems()->exists() || $product->inventoryMovements()->exists() || $product->branchPrices()->exists()) {
            return redirect()->route('admin.products.index')
                ->with('error', "Cannot delete '{$product->name}' because order, inventory, or branch pricing history exists. Archive it instead.");
        }

        $name = $product->name;
        $sku = $product->sku;
        $product->delete();

        AdminAuditLog::record(
            'product.delete',
            "Deleted product '{$name}' (SKU: {$sku}).",
            [
                'sku' => $sku,
                'company_id' => $product->company_id,
                'branch_id' => $product->branch_id,
            ]
        );

        return redirect()->route('admin.products.index')->with('success', "Product '{$name}' was deleted successfully.");
    }

    public function archive(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);
        if ($product->archived_at !== null) {
            return redirect()->route('admin.products.index')->with('success', "'{$product->name}' is already archived.");
        }

        DB::transaction(function () use ($product): void {
            $locked = Product::query()->withoutGlobalScopes()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['is_published' => false, 'archived_at' => now()])->save();
        });
        AdminAuditLog::record('product.archive', "Archived product '{$product->name}'.", [
            'product_id' => $product->id, 'company_id' => $product->company_id, 'branch_id' => $product->branch_id,
        ]);

        return redirect()->route('admin.products.index')->with('success', "'{$product->name}' was archived and is no longer purchasable.");
    }

    private function assertVisibleCategory(int $categoryId): ProductCategory
    {
        $category = ProductCategory::query()->whereKey($categoryId)->first();
        if ($category === null) {
            throw ValidationException::withMessages([
                'product_category_id' => 'Choose a category in your assigned company.',
            ]);
        }

        return $category;
    }

    private function decimalToMinor(string $value): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/', $value, $matches)) {
            throw ValidationException::withMessages(['price' => 'Enter a non-negative amount with no more than two decimal places.']);
        }
        $minor = ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
        if ($minor > 4_294_967_295) {
            throw ValidationException::withMessages(['price' => 'The price exceeds the amount supported by the current product schema.']);
        }

        return $minor;
    }

    private function assertVisibleBranch(?int $branchId, int $companyId): void
    {
        if ($branchId !== null && ! Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereKey($branchId)
            ->exists()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Choose an active branch in the product company.',
            ]);
        }
    }
}
