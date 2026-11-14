<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Company;
use App\Models\ProductCategory;
use App\Services\BusinessAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ProductCategory::class);

        $categories = ProductCategory::with(['company'])->withCount('products')->orderBy('order')->orderBy('name')->get();
        $companies = Company::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.categories.index', compact('categories', 'companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', ProductCategory::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $count = 1;
        while (ProductCategory::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $assignment = app(BusinessAccessService::class)->creationAssignment(
            $request->user(),
            false,
            isset($validated['company_id']) ? (int) $validated['company_id'] : null,
        );
        $category = ProductCategory::create([
            'company_id' => $assignment['company_id'],
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'order' => (int) ($validated['order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AdminAuditLog::record(
            'shop.category.create',
            "Created shop category '{$category->name}'.",
            ['category_id' => $category->id]
        );

        return redirect()->route('admin.categories.index')->with('success', "Category '{$category->name}' created successfully.");
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'order' => (int) ($validated['order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        AdminAuditLog::record(
            'shop.category.update',
            "Updated shop category '{$category->name}'.",
            ['category_id' => $category->id]
        );

        return redirect()->route('admin.categories.index')->with('success', "Category '{$category->name}' updated successfully.");
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->products()->exists()) {
            return redirect()->route('admin.categories.index')
                ->with('error', "Cannot delete category '{$category->name}' because it contains products. Reassign or delete products first.");
        }

        $name = $category->name;
        $category->delete();

        AdminAuditLog::record(
            'shop.category.delete',
            "Deleted shop category '{$name}'.",
            ['name' => $name, 'company_id' => $category->company_id]
        );

        return redirect()->route('admin.categories.index')->with('success', "Category '{$name}' deleted successfully.");
    }
}
