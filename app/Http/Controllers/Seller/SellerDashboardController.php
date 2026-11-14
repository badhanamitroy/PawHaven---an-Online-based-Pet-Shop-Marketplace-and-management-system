<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PetListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerDashboardController extends Controller
{
    /**
     * Display the personalized seller dashboard.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // 1. New purchase requests
        $newRequestsCount = Order::query()
            ->where('seller_id', $user->id)
            ->where('status', OrderStatus::Pending)
            ->count();

        // 2. Requests awaiting seller action (pending or processing)
        $awaitingActionCount = Order::query()
            ->where('seller_id', $user->id)
            ->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing])
            ->count();

        // 3. Payment submissions requiring verification (pending payment on non-cancelled orders)
        $pendingPaymentCount = Order::query()
            ->where('seller_id', $user->id)
            ->where('payment_status', PaymentStatus::Pending)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->count();

        // 4. Active commercial and adoption listings
        $activeListingsCount = $user->petListings()
            ->where('status', PetListingStatus::Published)
            ->count();

        // 5. Listings that need attention (rejected, pending review, draft, or paused)
        $attentionListingsCount = $user->petListings()
            ->whereIn('status', [
                PetListingStatus::Rejected,
                PetListingStatus::Draft,
                PetListingStatus::PendingReview,
                PetListingStatus::Paused,
            ])
            ->count();

        // Recent incoming purchase / adoption requests
        $recentRequests = Order::query()
            ->where('seller_id', $user->id)
            ->with(['items.product', 'petListing.images'])
            ->latest()
            ->take(5)
            ->get();

        // Recent listings
        $recentListings = $user->petListings()
            ->with(['petCategory', 'breed', 'images'])
            ->latest()
            ->take(5)
            ->get();

        $metrics = [
            'new_requests' => $newRequestsCount,
            'awaiting_action' => $awaitingActionCount,
            'pending_payment' => $pendingPaymentCount,
            'active_listings' => $activeListingsCount,
            'attention_listings' => $attentionListingsCount,
        ];

        return view('seller.dashboard', compact('metrics', 'recentRequests', 'recentListings'));
    }
}
