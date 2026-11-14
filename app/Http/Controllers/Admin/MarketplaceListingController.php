<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PetCategory;
use App\Models\PetListing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceListingController extends Controller
{
    public function index(Request $request): View
    {
        $query = PetListing::with(['petCategory', 'breed', 'seller', 'images']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('pet_category_id', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('seller', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $listings = $query->latest()->paginate(15)->withQueryString();
        $categories = PetCategory::orderBy('name')->get();

        return view('admin.marketplace.listings.index', compact('listings', 'categories'));
    }

    public function show(PetListing $listing): View
    {
        $listing->load(['petCategory', 'breed', 'seller', 'images', 'moderationActions.admin']);

        return view('admin.marketplace.listings.show', compact('listing'));
    }
}
