<?php

namespace App\Http\Controllers\Seller;

use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Http\Controllers\Controller;
use App\Models\Breed;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SellerListingController extends Controller
{
    /**
     * Display a listing of the seller's pet listings with filtering.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $statusFilter = $request->query('status', 'all');

        $query = $user->petListings()
            ->with(['petCategory', 'breed', 'images', 'moderationActions.admin'])
            ->latest();

        match ($statusFilter) {
            'published' => $query->where('status', PetListingStatus::Published),
            'pending', 'pending_review' => $query->where('status', PetListingStatus::PendingReview),
            'draft' => $query->where('status', PetListingStatus::Draft),
            'rejected' => $query->where('status', PetListingStatus::Rejected),
            'paused' => $query->where('status', PetListingStatus::Paused),
            'completed' => $query->where('status', PetListingStatus::Completed),
            'attention' => $query->whereIn('status', [
                PetListingStatus::Rejected,
                PetListingStatus::Draft,
                PetListingStatus::PendingReview,
                PetListingStatus::Paused,
            ]),
            default => null,
        };

        $listings = $query->paginate(10)->withQueryString();

        return view('seller.listings.index', [
            'listings' => $listings,
            'currentFilter' => $statusFilter,
            'counts' => [
                'all' => $user->petListings()->count(),
                'published' => $user->petListings()->where('status', PetListingStatus::Published)->count(),
                'pending' => $user->petListings()->where('status', PetListingStatus::PendingReview)->count(),
                'draft' => $user->petListings()->where('status', PetListingStatus::Draft)->count(),
                'rejected' => $user->petListings()->where('status', PetListingStatus::Rejected)->count(),
                'paused' => $user->petListings()->where('status', PetListingStatus::Paused)->count(),
                'attention' => $user->petListings()->whereIn('status', [
                    PetListingStatus::Rejected,
                    PetListingStatus::Draft,
                    PetListingStatus::PendingReview,
                    PetListingStatus::Paused,
                ])->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new pet listing.
     */
    public function create(): View
    {
        Gate::authorize('create', PetListing::class);

        return view('seller.listings.create', [
            'categories' => PetCategory::orderBy('name')->get(),
            'breeds' => Breed::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created pet listing in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', PetListing::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'pet_category_id' => ['nullable', 'exists:pet_categories,id'],
            'breed_id' => ['nullable', 'exists:breeds,id'],
            'listing_type' => ['required', 'string', 'in:sale,adoption,rescue,rehoming'],
            'price' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:3000'],
            'action' => ['required', 'string', 'in:draft,submit'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'max:2048'], // Max 2MB per image
        ]);

        /** @var User $user */
        $user = $request->user();
        $isSale = $validated['listing_type'] === 'sale';
        $priceMinor = $isSale && isset($validated['price']) ? (int) round(((float) $validated['price']) * 100) : 0;
        $initialStatus = $validated['action'] === 'submit' ? PetListingStatus::PendingReview : PetListingStatus::Draft;

        $listing = PetListing::create([
            'seller_id' => $user->id,
            'pet_category_id' => $validated['pet_category_id'] ? (int) $validated['pet_category_id'] : null,
            'breed_id' => $validated['breed_id'] ? (int) $validated['breed_id'] : null,
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'listing_type' => PetListingType::from((string) $validated['listing_type']),
            'price_minor' => $priceMinor,
            'location' => (string) $validated['location'],
            'status' => $initialStatus,
        ]);

        if ($request->hasFile('images')) {
            $files = $request->file('images');
            if (is_array($files)) {
                foreach ($files as $position => $file) {
                    if ($file->isValid()) {
                        $path = $file->store('listings', 'public');
                        $listing->images()->create([
                            'path' => $path,
                            'position' => $position,
                        ]);
                    }
                }
            }
        }

        $message = $initialStatus === PetListingStatus::PendingReview
            ? "Listing '{$listing->title}' created and submitted for administrator review!"
            : "Draft listing '{$listing->title}' saved successfully.";

        return redirect()->route('seller.listings.index')->with('success', $message);
    }

    /**
     * Show the form for editing the specified pet listing.
     */
    public function edit(PetListing $listing): View
    {
        Gate::authorize('update', $listing);

        $listing->load(['images', 'moderationActions.admin']);

        return view('seller.listings.edit', [
            'listing' => $listing,
            'categories' => PetCategory::orderBy('name')->get(),
            'breeds' => Breed::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified pet listing in storage.
     */
    public function update(Request $request, PetListing $listing): RedirectResponse
    {
        Gate::authorize('update', $listing);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'pet_category_id' => ['nullable', 'exists:pet_categories,id'],
            'breed_id' => ['nullable', 'exists:breeds,id'],
            'listing_type' => ['required', 'string', 'in:sale,adoption,rescue,rehoming'],
            'price' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:3000'],
            'action' => ['nullable', 'string', 'in:save,submit'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'max:2048'],
        ]);

        $isSale = $validated['listing_type'] === 'sale';
        $priceMinor = $isSale && isset($validated['price']) ? (int) round(((float) $validated['price']) * 100) : 0;

        $updateData = [
            'pet_category_id' => $validated['pet_category_id'] ? (int) $validated['pet_category_id'] : null,
            'breed_id' => $validated['breed_id'] ? (int) $validated['breed_id'] : null,
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'listing_type' => PetListingType::from((string) $validated['listing_type']),
            'price_minor' => $priceMinor,
            'location' => (string) $validated['location'],
        ];

        // If seller explicitly clicks "Submit for Review" or if the listing was previously rejected/draft
        if (($request->input('action') === 'submit') && in_array($listing->status, [PetListingStatus::Draft, PetListingStatus::Rejected], true)) {
            $updateData['status'] = PetListingStatus::PendingReview;
        }

        $listing->update($updateData);

        if ($request->hasFile('images')) {
            $files = $request->file('images');
            if (is_array($files)) {
                $currentCount = $listing->images()->count();
                foreach ($files as $offset => $file) {
                    if ($file->isValid() && ($currentCount + $offset) < 5) {
                        $path = $file->store('listings', 'public');
                        $listing->images()->create([
                            'path' => $path,
                            'position' => $currentCount + $offset,
                        ]);
                    }
                }
            }
        }

        return redirect()->route('seller.listings.index')->with(
            'success',
            "Listing '{$listing->title}' has been updated successfully."
        );
    }

    /**
     * Remove the specified pet listing from storage.
     */
    public function destroy(PetListing $listing): RedirectResponse
    {
        Gate::authorize('delete', $listing);

        // Remove images from public disk
        foreach ($listing->images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }

        $listingTitle = $listing->title;
        $listing->delete();

        return redirect()->route('seller.listings.index')->with(
            'success',
            "Listing '{$listingTitle}' has been permanently deleted."
        );
    }

    /**
     * Handle state transitions for seller-owned listings (pause, submit for review, mark completed).
     */
    public function updateStatus(Request $request, PetListing $listing): RedirectResponse
    {
        Gate::authorize('update', $listing);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:pause,submit_review,mark_completed'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $action = (string) $validated['action'];

        match ($action) {
            'pause' => $listing->pause($user),
            'submit_review' => $listing->submitForReview($user),
            'mark_completed' => $listing->markCompleted($user),
            default => null,
        };

        return back()->with('success', "Listing status updated successfully to {$listing->status->value}.");
    }
}
