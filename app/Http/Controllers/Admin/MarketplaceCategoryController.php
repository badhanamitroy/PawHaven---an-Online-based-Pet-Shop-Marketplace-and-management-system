<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\PetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MarketplaceCategoryController extends Controller
{
    public function index(): View
    {
        $categories = PetCategory::withCount(['breeds', 'petListings'])->orderBy('name')->get();

        return view('admin.marketplace.categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:pet_categories,name'],
        ]);

        $slug = Str::slug($validated['name']);

        $category = PetCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        AdminAuditLog::record(
            'pet_category.create',
            "Created pet category '{$category->name}'.",
            ['category_id' => $category->id]
        );

        return redirect()->route('admin.marketplace.categories.index')
            ->with('success', "Pet category '{$category->name}' created successfully.");
    }

    public function destroy(PetCategory $category): RedirectResponse
    {
        if ($category->petListings()->exists()) {
            return redirect()->route('admin.marketplace.categories.index')
                ->with('error', "Cannot delete '{$category->name}' because it contains active or historical pet listings.");
        }

        $name = $category->name;
        $category->delete();

        AdminAuditLog::record(
            'pet_category.delete',
            "Deleted pet category '{$name}'.",
            ['name' => $name]
        );

        return redirect()->route('admin.marketplace.categories.index')
            ->with('success', "Pet category '{$name}' deleted successfully.");
    }
}
