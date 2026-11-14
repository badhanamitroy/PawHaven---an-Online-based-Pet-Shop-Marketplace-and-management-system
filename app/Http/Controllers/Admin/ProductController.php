<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\BusinessAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
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

        $priceMinor = (int) round(((float) $validated['price']) * 100);
        $salePriceMinor = isset($validated['sale_price']) && $validated['sale_price'] !== ''
            ? (int) round(((float) $validated['sale_price']) * 100)
            : null;

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

        AdminAuditLog::record(
            'product.create',
            "Created product '{$product->name}' (SKU: {$product->sku}).",
            ['product_id' => $product->id, 'sku' => $product->sku]
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
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

        $priceMinor = (int) round(((float) $validated['price']) * 100);
        $salePriceMinor = isset($validated['sale_price']) && $validated['sale_price'] !== ''
            ? (int) round(((float) $validated['sale_price']) * 100)
            : null;

        $updateData = [
            'product_category_id' => (int) $validated['product_category_id'],
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'sku' => strtoupper($validated['sku']),
            'description' => $validated['description'] ?? null,
            'price_minor' => $priceMinor,
            'sale_price_minor' => $salePriceMinor,
            'stock_quantity' => (int) $validated['stock_quantity'],
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

        $product->update($updateData);

        AdminAuditLog::record(
            'product.update',
            "Updated product '{$product->name}' (SKU: {$product->sku}).",
            ['product_id' => $product->id]
        );

        return redirect()->route('admin.products.index')->with('success', "Product '{$product->name}' updated successfully.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        // Check if product has been ordered
        if ($product->orderItems()->exists()) {
            return redirect()->route('admin.products.index')
                ->with('error', "Cannot delete '{$product->name}' because it is associated with existing customer orders. You may unpublish it instead.");
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
