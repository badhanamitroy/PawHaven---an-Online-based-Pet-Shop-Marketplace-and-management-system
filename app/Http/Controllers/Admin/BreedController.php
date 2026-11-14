<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Breed;
use App\Models\PetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BreedController extends Controller
{
    public function index(Request $request): View
    {
        $query = Breed::with('petCategory')->withCount('listings');

        if ($request->filled('category')) {
            $query->where('pet_category_id', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $breeds = $query->orderBy('name')->paginate(20)->withQueryString();
        $categories = PetCategory::orderBy('name')->get();

        return view('admin.breeds.index', compact('breeds', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pet_category_id' => ['required', 'exists:pet_categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'care_information' => ['nullable', 'string', 'max:2000'],
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $count = 1;
        while (Breed::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        $breed = Breed::create([
            'pet_category_id' => (int) $validated['pet_category_id'],
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'care_information' => $validated['care_information'] ?? null,
        ]);

        AdminAuditLog::record(
            'breed.create',
            "Created breed profile for '{$breed->name}'.",
            ['breed_id' => $breed->id, 'name' => $breed->name]
        );

        return redirect()->route('admin.breeds.index')->with('success', "Breed '{$breed->name}' added successfully.");
    }

    public function destroy(Breed $breed): RedirectResponse
    {
        if ($breed->listings()->exists()) {
            return redirect()->route('admin.breeds.index')
                ->with('error', "Cannot delete breed '{$breed->name}' because active pet listings reference it.");
        }

        $name = $breed->name;
        $breed->delete();

        AdminAuditLog::record(
            'breed.delete',
            "Deleted breed profile '{$name}'.",
            ['name' => $name]
        );

        return redirect()->route('admin.breeds.index')->with('success', "Breed '{$name}' deleted successfully.");
    }
}
