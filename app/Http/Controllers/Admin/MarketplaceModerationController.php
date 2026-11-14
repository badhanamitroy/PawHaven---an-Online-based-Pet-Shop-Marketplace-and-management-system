<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ListingModerationActionType;
use App\Enums\PetListingStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\PetListing;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceModerationController extends Controller
{
    public function index(): View
    {
        $pendingListings = PetListing::query()
            ->where('status', PetListingStatus::PendingReview)
            ->with(['petCategory', 'breed', 'seller', 'images'])
            ->latest()
            ->paginate(15);

        return view('admin.marketplace.moderation.index', compact('pendingListings'));
    }

    public function approve(Request $request, PetListing $listing): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $action = $listing->moderate(
            $admin,
            ListingModerationActionType::Approved,
            $request->input('note')
        );

        AdminAuditLog::record(
            'listing.moderate.approved',
            "Approved pet listing '{$listing->title}' (ID: {$listing->id}).",
            [
                'listing_id' => $listing->id,
                'action_id' => $action->id,
                'note' => $request->input('note'),
            ]
        );

        return redirect()->route('admin.marketplace.moderation.index')
            ->with('success', "Listing '{$listing->title}' approved and published.");
    }

    public function reject(Request $request, PetListing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $action = $listing->moderate(
            $admin,
            ListingModerationActionType::Rejected,
            $validated['note']
        );

        AdminAuditLog::record(
            'listing.moderate.rejected',
            "Rejected pet listing '{$listing->title}' (ID: {$listing->id}). Reason: {$validated['note']}",
            [
                'listing_id' => $listing->id,
                'action_id' => $action->id,
                'reason' => $validated['note'],
            ]
        );

        return redirect()->route('admin.marketplace.moderation.index')
            ->with('success', "Listing '{$listing->title}' rejected.");
    }
}
