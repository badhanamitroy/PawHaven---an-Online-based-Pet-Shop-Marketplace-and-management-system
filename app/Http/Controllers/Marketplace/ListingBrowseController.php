<?php

namespace App\Http\Controllers\Marketplace;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Http\Controllers\Controller;
use App\Models\Breed;
use App\Models\Order;
use App\Models\PetCategory;
use App\Models\PetListing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListingBrowseController extends Controller
{
    /**
     * Browse all publicly available, verified pet listings.
     */
    public function index(Request $request): View
    {
        $query = PetListing::query()
            ->where('status', PetListingStatus::Published)
            ->with(['petCategory', 'breed', 'images', 'seller']);

        // Filter by category
        if ($categoryId = $request->input('category')) {
            $query->where('pet_category_id', $categoryId);
        }

        // Filter by breed
        if ($breedId = $request->input('breed')) {
            $query->where('breed_id', $breedId);
        }

        // Filter by listing type (e.g. sale vs adoption)
        if ($type = $request->input('type')) {
            if ($type === 'adoption') {
                $query->whereIn('listing_type', [
                    PetListingType::Adoption,
                    PetListingType::Rescue,
                    PetListingType::Rehoming,
                ]);
            } elseif ($type === 'sale') {
                $query->where('listing_type', PetListingType::Sale);
            } elseif ($enumType = PetListingType::tryFrom($type)) {
                $query->where('listing_type', $enumType);
            }
        }

        // Search by keywords (title, description, location)
        if ($search = $request->input('q')) {
            $searchString = trim((string) $search);
            $query->where(function ($q) use ($searchString) {
                $q->where('title', 'like', "%{$searchString}%")
                    ->orWhere('description', 'like', "%{$searchString}%")
                    ->orWhere('location', 'like', "%{$searchString}%");
            });
        }

        // Sort order
        $sort = $request->input('sort', 'latest');
        if ($sort === 'price_asc') {
            $query->orderBy('price_minor', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('price_minor', 'desc');
        } else {
            $query->latest();
        }

        $listings = $query->paginate(12)->withQueryString();

        $categories = PetCategory::query()->orderBy('name')->get();
        $breeds = Breed::query()->orderBy('name')->get();

        return view('marketplace.index', [
            'listings' => $listings,
            'categories' => $categories,
            'breeds' => $breeds,
            'currentCategory' => $categoryId ? PetCategory::find($categoryId) : null,
            'currentBreed' => $breedId ? Breed::find($breedId) : null,
            'currentType' => $type,
            'searchQuery' => $search,
            'sort' => $sort,
        ]);
    }

    /**
     * Display a single public pet listing detail page.
     */
    public function show(PetListing $listing): View
    {
        abort_unless(
            Gate::allows('view', $listing),
            404,
            'Pet listing not found or is currently undergoing moderation.'
        );

        $listing->load(['petCategory', 'breed', 'images', 'seller']);

        // Related published listings in the same category (or same type)
        $relatedListings = PetListing::query()
            ->where('status', PetListingStatus::Published)
            ->where('id', '!=', $listing->id)
            ->where(function ($q) use ($listing) {
                if ($listing->pet_category_id) {
                    $q->where('pet_category_id', $listing->pet_category_id);
                } else {
                    $q->where('listing_type', $listing->listing_type);
                }
            })
            ->with(['petCategory', 'breed', 'images'])
            ->latest()
            ->take(3)
            ->get();

        return view('marketplace.show', [
            'listing' => $listing,
            'relatedListings' => $relatedListings,
            'isOwner' => auth()->check() && auth()->id() === $listing->seller_id,
        ]);
    }

    /**
     * Submit a customer purchase request for a commercial pet listing.
     */
    public function purchase(Request $request, PetListing $listing): RedirectResponse
    {
        // Must be published and of type sale
        if ($listing->status !== PetListingStatus::Published || $listing->listing_type !== PetListingType::Sale) {
            abort(400, 'This listing is not eligible for commercial purchase requests.');
        }

        // Sellers cannot purchase their own listing
        if (auth()->id() === $listing->seller_id) {
            return back()->with('error', 'You cannot submit a purchase request for your own listing.');
        }

        $validated = $request->validate([
            'customer_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $price = $listing->price_minor ?? 0;
        if ($price < 0 || $price > 4_294_967_295) {
            throw ValidationException::withMessages([
                'listing' => 'This listing price exceeds the amount range supported by order records.',
            ]);
        }

        $order = DB::transaction(function () use ($listing, $user, $validated, $price): Order {
            $order = Order::create([
                'order_number' => 'PET-'.strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'seller_id' => $listing->seller_id,
                'pet_listing_id' => $listing->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $validated['customer_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'status' => OrderStatus::Pending,
                'payment_method' => 'cod',
                'payment_status' => PaymentStatus::Pending,
                'subtotal_minor' => $price,
                'delivery_fee_minor' => 0,
                'total_minor' => $price,
                'currency' => $listing->currency,
                'notes' => "Purchase request for pet listing #{$listing->id} ({$listing->title}). Seller: {$listing->seller?->name}. ".($validated['notes'] ?? ''),
            ]);

            $order->items()->create([
                'product_name' => $listing->title,
                'sku' => "PET-{$listing->id}",
                'price_minor' => $price,
                'quantity' => 1,
                'subtotal_minor' => $price,
            ]);

            return $order;
        });

        return redirect()->route('dashboard')->with(
            'success',
            "Your purchase request for '{$listing->title}' (Request #{$order->order_number}) was submitted to the seller! You can monitor progress on your dashboard."
        );
    }

    /**
     * Submit an adoption inquiry or application for an adoption/rescue post.
     */
    public function inquire(Request $request, PetListing $listing): RedirectResponse
    {
        if ($listing->status !== PetListingStatus::Published || $listing->listing_type === PetListingType::Sale) {
            abort(400, 'This listing is not an adoption or rescue post.');
        }

        if (auth()->id() === $listing->seller_id) {
            return back()->with('error', 'You cannot submit an adoption inquiry for your own post.');
        }

        $validated = $request->validate([
            'customer_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'notes' => ['required', 'string', 'max:1000'], // Household / pet experience details
        ]);

        $user = $request->user();

        $order = DB::transaction(function () use ($listing, $user, $validated): Order {
            $order = Order::create([
                'order_number' => 'ADOPT-'.strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'seller_id' => $listing->seller_id,
                'pet_listing_id' => $listing->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $validated['customer_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'status' => OrderStatus::Pending,
                'payment_method' => 'inquiry',
                'payment_status' => PaymentStatus::Pending,
                'subtotal_minor' => 0,
                'delivery_fee_minor' => 0,
                'total_minor' => 0,
                'currency' => $listing->currency,
                'notes' => "Adoption inquiry for pet listing #{$listing->id} ({$listing->title}). Rehoming Shelter/Owner: {$listing->seller?->name}. Applicant Notes: {$validated['notes']}",
            ]);

            $order->items()->create([
                'product_name' => "Adoption: {$listing->title}",
                'sku' => "ADOPT-{$listing->id}",
                'price_minor' => 0,
                'quantity' => 1,
                'subtotal_minor' => 0,
            ]);

            return $order;
        });

        return redirect()->route('dashboard')->with(
            'success',
            "Your adoption application for '{$listing->title}' (Application #{$order->order_number}) has been sent to the shelter/owner! They will reach out to you."
        );
    }

    /**
     * Toggle saving a pet listing for the authenticated customer.
     */
    public function toggleFavorite(Request $request, PetListing $listing): RedirectResponse
    {
        abort_unless(
            Gate::allows('view', $listing),
            404,
            'Pet listing not found or is currently undergoing moderation.'
        );

        $user = $request->user();

        if ($user->savedPets()->where('pet_listing_id', $listing->id)->exists()) {
            $user->savedPets()->detach($listing->id);
            $message = "Removed '{$listing->title}' from your saved pets.";
        } else {
            $user->savedPets()->attach($listing->id);
            $message = "Saved '{$listing->title}' to your favorites!";
        }

        return back()->with('success', $message);
    }
}
