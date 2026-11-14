<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PetListingType;
use App\Facades\Settings;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\PetListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(): View
    {
        $adoptionCount = PetListing::where('listing_type', PetListingType::Adoption)->count();
        $rehomeCount = PetListing::where('listing_type', PetListingType::Rehoming)->count();
        $recentAdoptions = PetListing::whereIn('listing_type', [PetListingType::Adoption, PetListingType::Rehoming, PetListingType::Rescue])
            ->with(['petCategory', 'breed', 'seller'])
            ->latest()
            ->take(6)
            ->get();

        return view('admin.community.index', [
            'settings' => Settings::all(),
            'adoptionCount' => $adoptionCount,
            'rehomeCount' => $rehomeCount,
            'recentAdoptions' => $recentAdoptions,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rescue_email' => ['required', 'email', 'max:255'],
            'rescue_hotline' => ['required', 'string', 'max:50'],
            'adoption_guidelines' => ['nullable', 'string', 'max:2000'],
            'rescue_partners_count' => ['nullable', 'integer', 'min:0'],
        ]);

        Settings::setMany([
            'community.rescue_email' => $validated['rescue_email'],
            'community.rescue_hotline' => $validated['rescue_hotline'],
            'community.adoption_guidelines' => $validated['adoption_guidelines'] ?? '',
            'community.rescue_partners_count' => (string) ($validated['rescue_partners_count'] ?? '0'),
        ], 'community');

        AdminAuditLog::record(
            'community.update',
            'Updated adoption & rescue hub community parameters.',
            ['rescue_email' => $validated['rescue_email']]
        );

        return redirect()->route('admin.community.index')->with('success', 'Adoption & Rescue Hub settings updated successfully.');
    }
}
