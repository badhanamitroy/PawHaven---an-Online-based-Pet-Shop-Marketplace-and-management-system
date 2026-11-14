<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\ListingModerationAction;
use App\Models\Order;
use App\Models\PetListing;
use App\Models\Product;
use App\Models\User;
use App\Services\BusinessAccessService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the comprehensive administrative overview dashboard.
     */
    public function index(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        $platformAdmin = $user->isAdmin();
        $businessAccess = app(BusinessAccessService::class);
        $businessMember = $user->isStaff() && $businessAccess->hasBusinessAccess($user);
        $businessManager = $businessMember && $businessAccess->activeMemberships($user)->contains(
            fn ($membership): bool => in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)
        );
        abort_unless($platformAdmin || $businessMember, 403);

        // 1. Commercial listings (Sale) & Adoption / Rescue posts
        $activeCommercialListings = $platformAdmin ? PetListing::query()
            ->where('status', PetListingStatus::Published)
            ->where('listing_type', PetListingType::Sale)
            ->count() : 0;

        $publishedAdoptionPosts = $platformAdmin ? PetListing::query()
            ->where('status', PetListingStatus::Published)
            ->whereIn('listing_type', [
                PetListingType::Adoption,
                PetListingType::Rescue,
                PetListingType::Rehoming,
            ])
            ->count() : 0;

        $pendingModerationTasks = $platformAdmin ? PetListing::query()
            ->where('status', PetListingStatus::PendingReview)
            ->count() : 0;

        // 2. Orders & Purchase Requests
        $totalOrders = $businessManager ? Order::count() : 0;
        $pendingPurchaseRequests = $businessManager ? Order::where('status', OrderStatus::Pending)->count() : 0;
        $paymentsRequiringReview = $businessManager ? Order::where('payment_status', PaymentStatus::Pending)->count() : 0;
        $deliveredOrders = $businessManager ? Order::where('status', OrderStatus::Delivered)->count() : 0;

        // Calculate confirmed sales only from completed / delivered orders
        $totalSalesMinor = $businessManager ? Order::where('status', OrderStatus::Delivered)->sum('total_minor') : 0;

        // 3. Products & Inventory Alerts
        $productsCount = $businessManager ? Product::count() : 0;
        $lowStockCount = $businessManager ? Product::lowStock()->count() : 0;

        // 4. Real Recent Activity Lists
        $recentOrders = $businessManager ? Order::with('user')->latest()->take(5)->get() : new Collection;
        $recentModerations = $platformAdmin
            ? ListingModerationAction::with(['petListing'])->latest()->take(5)->get()
            : new Collection;
        $recentAuditLogs = $businessManager ? AdminAuditLog::with('user')->latest()->take(6)->get() : new Collection;

        return view('admin.dashboard', compact(
            'activeCommercialListings',
            'publishedAdoptionPosts',
            'pendingModerationTasks',
            'totalOrders',
            'pendingPurchaseRequests',
            'paymentsRequiringReview',
            'deliveredOrders',
            'totalSalesMinor',
            'productsCount',
            'lowStockCount',
            'recentOrders',
            'recentModerations',
            'recentAuditLogs'
        ));
    }
}
