<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Facades\Settings;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Breed;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomepageCustomizerController extends Controller
{
    /**
     * Display the section-based Website Manager editor.
     */
    public function index(): View
    {
        return view('admin.homepage.index', [
            'settings' => Settings::all(),
        ]);
    }

    /**
     * Save draft modifications without altering the public live presentation.
     */
    public function saveDraft(Request $request): RedirectResponse
    {
        $validated = $this->validateInput($request);

        Settings::setMany([
            'homepage.draft.hero_headline' => $validated['hero_headline'],
            'homepage.draft.hero_supporting' => $validated['hero_supporting'],
            'homepage.draft.cta_primary_label' => $validated['cta_primary_label'],
            'homepage.draft.cta_primary_url' => $validated['cta_primary_url'],
            'homepage.draft.cta_secondary_label' => $validated['cta_secondary_label'],
            'homepage.draft.cta_secondary_url' => $validated['cta_secondary_url'],
            'homepage.draft.cta_adoption_label' => $validated['cta_adoption_label'],
            'homepage.draft.cta_adoption_url' => $validated['cta_adoption_url'],
            'homepage.draft.announcement_text' => $validated['announcement_text'],
            'homepage.draft.announcement_enabled' => $request->boolean('announcement_enabled'),
            'homepage.draft.section_categories_visible' => $request->boolean('section_categories_visible'),
            'homepage.draft.section_marketplace_visible' => $request->boolean('section_marketplace_visible'),
            'homepage.draft.section_shop_visible' => $request->boolean('section_shop_visible'),
            'homepage.draft.section_adoption_visible' => $request->boolean('section_adoption_visible'),
            'homepage.draft.section_breeds_visible' => $request->boolean('section_breeds_visible'),
            'homepage.draft.section_how_it_works_visible' => $request->boolean('section_how_it_works_visible'),
            'homepage.draft.section_trust_safety_visible' => $request->boolean('section_trust_safety_visible'),
            'homepage.draft_exists' => true,
        ], 'homepage_draft');

        AdminAuditLog::record(
            'homepage.draft_saved',
            'Saved unpublished draft changes for homepage presentation.'
        );

        return redirect()->route('admin.homepage.index')
            ->with('success', 'Homepage draft saved successfully. Preview changes before publishing.');
    }

    /**
     * Preview the rendered public homepage with draft content applied (Authorized admins only).
     */
    public function previewDraft(): View
    {
        $draftSettings = [
            'brand.name' => Settings::get('brand.name', 'PawHaven'),
            'brand.tagline' => Settings::get('brand.tagline', 'Find Pets. Give Hope. Shop with Love.'),
            'brand.logo_light_path' => Settings::get('brand.logo_light_path'),
            'brand.logo_dark_path' => Settings::get('brand.logo_dark_path'),
            'homepage.hero_headline' => Settings::get('homepage.draft.hero_headline', Settings::get('homepage.hero_headline', 'Your next best friend is waiting.')),
            'homepage.hero_supporting' => Settings::get('homepage.draft.hero_supporting', Settings::get('homepage.hero_supporting', 'Discover pets, shop food and accessories, and help animals find loving homes—all in one place.')),
            'homepage.cta_primary_label' => Settings::get('homepage.draft.cta_primary_label', Settings::get('homepage.cta_primary_label', 'Explore Pets')),
            'homepage.cta_primary_url' => Settings::get('homepage.draft.cta_primary_url', Settings::get('homepage.cta_primary_url', '#find-pets')),
            'homepage.cta_secondary_label' => Settings::get('homepage.draft.cta_secondary_label', Settings::get('homepage.cta_secondary_label', 'Shop Pet Supplies')),
            'homepage.cta_secondary_url' => Settings::get('homepage.draft.cta_secondary_url', Settings::get('homepage.cta_secondary_url', '#pet-shop')),
            'homepage.cta_adoption_label' => Settings::get('homepage.draft.cta_adoption_label', Settings::get('homepage.cta_adoption_label', 'Explore Adoption')),
            'homepage.cta_adoption_url' => Settings::get('homepage.draft.cta_adoption_url', Settings::get('homepage.cta_adoption_url', '#adoption-rescue')),
            'homepage.announcement_text' => Settings::get('homepage.draft.announcement_text', Settings::get('homepage.announcement_text', 'Connecting ethical breeders, local shelters, and loving families.')),
            'homepage.announcement_enabled' => Settings::get('homepage.draft_exists')
                ? filter_var(Settings::get('homepage.draft.announcement_enabled', true), FILTER_VALIDATE_BOOLEAN)
                : filter_var(Settings::get('homepage.announcement_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_marketplace_visible' => Settings::get('homepage.draft_exists')
                ? filter_var(Settings::get('homepage.draft.section_marketplace_visible', true), FILTER_VALIDATE_BOOLEAN)
                : filter_var(Settings::get('homepage.section_marketplace_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_shop_visible' => Settings::get('homepage.draft_exists')
                ? filter_var(Settings::get('homepage.draft.section_shop_visible', true), FILTER_VALIDATE_BOOLEAN)
                : filter_var(Settings::get('homepage.section_shop_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_adoption_visible' => Settings::get('homepage.draft_exists')
                ? filter_var(Settings::get('homepage.draft.section_adoption_visible', true), FILTER_VALIDATE_BOOLEAN)
                : filter_var(Settings::get('homepage.section_adoption_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_breeds_visible' => Settings::get('homepage.draft_exists')
                ? filter_var(Settings::get('homepage.draft.section_breeds_visible', true), FILTER_VALIDATE_BOOLEAN)
                : filter_var(Settings::get('homepage.section_breeds_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_community_visible' => filter_var(Settings::get('homepage.section_community_visible', true), FILTER_VALIDATE_BOOLEAN),
        ];

        $featuredListings = PetListing::query()
            ->where('status', PetListingStatus::Published)
            ->where('listing_type', PetListingType::Sale)
            ->with(['petCategory', 'breed', 'images', 'seller'])
            ->latest()
            ->take(6)
            ->get();

        $featuredAdoptions = PetListing::query()
            ->where('status', PetListingStatus::Published)
            ->whereIn('listing_type', [
                PetListingType::Adoption,
                PetListingType::Rescue,
                PetListingType::Rehoming,
            ])
            ->with(['petCategory', 'breed', 'images', 'seller'])
            ->latest()
            ->take(3)
            ->get();

        $petCategories = PetCategory::query()
            ->withCount(['petListings' => fn ($query) => $query->where('status', PetListingStatus::Published)])
            ->get();

        $featuredBreeds = Breed::query()
            ->with('petCategory')
            ->latest()
            ->take(4)
            ->get();

        $featuredProducts = Product::query()
            ->published()
            ->with('category')
            ->latest()
            ->take(4)
            ->get();

        return view('welcome', [
            'featuredListings' => $featuredListings,
            'featuredAdoptions' => $featuredAdoptions,
            'petCategories' => $petCategories,
            'featuredBreeds' => $featuredBreeds,
            'featuredProducts' => $featuredProducts,
            'previewSettings' => $draftSettings,
            'isDraftPreview' => true,
        ]);
    }

    /**
     * Explicitly publish currently staged draft to canonical public settings.
     */
    public function publishDraft(): RedirectResponse
    {
        if (! Settings::get('homepage.draft_exists')) {
            return redirect()->route('admin.homepage.index')
                ->with('error', 'No active draft found to publish.');
        }

        Settings::setMany([
            'homepage.hero_headline' => Settings::get('homepage.draft.hero_headline', Settings::get('homepage.hero_headline')),
            'homepage.hero_supporting' => Settings::get('homepage.draft.hero_supporting', Settings::get('homepage.hero_supporting')),
            'homepage.cta_primary_label' => Settings::get('homepage.draft.cta_primary_label', Settings::get('homepage.cta_primary_label')),
            'homepage.cta_primary_url' => Settings::get('homepage.draft.cta_primary_url', Settings::get('homepage.cta_primary_url')),
            'homepage.cta_secondary_label' => Settings::get('homepage.draft.cta_secondary_label', Settings::get('homepage.cta_secondary_label')),
            'homepage.cta_secondary_url' => Settings::get('homepage.draft.cta_secondary_url', Settings::get('homepage.cta_secondary_url')),
            'homepage.cta_adoption_label' => Settings::get('homepage.draft.cta_adoption_label', Settings::get('homepage.cta_adoption_label')),
            'homepage.cta_adoption_url' => Settings::get('homepage.draft.cta_adoption_url', Settings::get('homepage.cta_adoption_url')),
            'homepage.announcement_text' => Settings::get('homepage.draft.announcement_text', Settings::get('homepage.announcement_text')),
            'homepage.announcement_enabled' => filter_var(Settings::get('homepage.draft.announcement_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_categories_visible' => filter_var(Settings::get('homepage.draft.section_categories_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_marketplace_visible' => filter_var(Settings::get('homepage.draft.section_marketplace_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_shop_visible' => filter_var(Settings::get('homepage.draft.section_shop_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_adoption_visible' => filter_var(Settings::get('homepage.draft.section_adoption_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_breeds_visible' => filter_var(Settings::get('homepage.draft.section_breeds_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_how_it_works_visible' => filter_var(Settings::get('homepage.draft.section_how_it_works_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.section_trust_safety_visible' => filter_var(Settings::get('homepage.draft.section_trust_safety_visible', true), FILTER_VALIDATE_BOOLEAN),
            'homepage.draft_exists' => false,
        ], 'homepage');

        AdminAuditLog::record(
            'homepage.publish',
            'Published website draft to public live environment.'
        );

        return redirect()->route('admin.homepage.index')
            ->with('success', 'Homepage draft has been successfully published to the live website!');
    }

    /**
     * Discard staged draft changes without affecting live public settings.
     */
    public function discardDraft(): RedirectResponse
    {
        Settings::set('homepage.draft_exists', false, 'homepage');

        AdminAuditLog::record(
            'homepage.draft_discarded',
            'Discarded unpublished homepage draft.'
        );

        return redirect()->route('admin.homepage.index')
            ->with('success', 'Unpublished draft has been discarded.');
    }

    /**
     * Publish directly from form input (Save & Publish immediately).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $this->validateInput($request);

        Settings::setMany([
            'homepage.hero_headline' => $validated['hero_headline'],
            'homepage.hero_supporting' => $validated['hero_supporting'],
            'homepage.cta_primary_label' => $validated['cta_primary_label'],
            'homepage.cta_primary_url' => $validated['cta_primary_url'],
            'homepage.cta_secondary_label' => $validated['cta_secondary_label'],
            'homepage.cta_secondary_url' => $validated['cta_secondary_url'],
            'homepage.cta_adoption_label' => $validated['cta_adoption_label'],
            'homepage.cta_adoption_url' => $validated['cta_adoption_url'],
            'homepage.announcement_text' => $validated['announcement_text'],
            'homepage.announcement_enabled' => $request->boolean('announcement_enabled'),
            'homepage.section_categories_visible' => $request->boolean('section_categories_visible'),
            'homepage.section_marketplace_visible' => $request->boolean('section_marketplace_visible'),
            'homepage.section_shop_visible' => $request->boolean('section_shop_visible'),
            'homepage.section_adoption_visible' => $request->boolean('section_adoption_visible'),
            'homepage.section_breeds_visible' => $request->boolean('section_breeds_visible'),
            'homepage.section_how_it_works_visible' => $request->boolean('section_how_it_works_visible'),
            'homepage.section_trust_safety_visible' => $request->boolean('section_trust_safety_visible'),
            'homepage.draft_exists' => false,
        ], 'homepage');

        AdminAuditLog::record(
            'homepage.update',
            'Updated and published public homepage presentation directly.'
        );

        return redirect()->route('admin.homepage.index')
            ->with('success', 'Homepage content and visibility saved and published successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateInput(Request $request): array
    {
        return $request->validate([
            'hero_headline' => ['required', 'string', 'max:150'],
            'hero_supporting' => ['required', 'string', 'max:500'],
            'cta_primary_label' => ['required', 'string', 'max:50'],
            'cta_primary_url' => ['required', 'string', 'max:255'],
            'cta_secondary_label' => ['required', 'string', 'max:50'],
            'cta_secondary_url' => ['required', 'string', 'max:255'],
            'cta_adoption_label' => ['required', 'string', 'max:50'],
            'cta_adoption_url' => ['required', 'string', 'max:255'],
            'announcement_text' => ['required', 'string', 'max:255'],
            'announcement_enabled' => ['nullable', 'boolean'],
            'section_categories_visible' => ['nullable', 'boolean'],
            'section_marketplace_visible' => ['nullable', 'boolean'],
            'section_shop_visible' => ['nullable', 'boolean'],
            'section_adoption_visible' => ['nullable', 'boolean'],
            'section_breeds_visible' => ['nullable', 'boolean'],
            'section_how_it_works_visible' => ['nullable', 'boolean'],
            'section_trust_safety_visible' => ['nullable', 'boolean'],
        ]);
    }
}
