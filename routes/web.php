<?php

use App\Enums\OrderStatus;
use App\Enums\PetListingStatus;
use App\Enums\PetListingType;
use App\Enums\SalesChannel;
use App\Http\Controllers\Auth\EmailOtpChallengeController;
use App\Http\Controllers\Auth\InvitationRegistrationController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\CareerOfferController;
use App\Http\Controllers\Marketplace\ListingBrowseController;
use App\Http\Controllers\OrderRequestController;
use App\Http\Controllers\PaymentRefundController;
use App\Http\Controllers\ProductReturnController;
use App\Models\Breed;
use App\Models\Order;
use App\Models\PetCategory;
use App\Models\PetListing;
use App\Models\Product;
use App\Models\User;
use App\Services\PricingService;
use App\Services\OrderInventoryService;
use App\Services\FulfilmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\RoutePath;

/** @var Closure(): View $renderHomepage */
$renderHomepage = function () {
    // 1. Featured Commercial Pet Listings (Sale only)
    $featuredListings = PetListing::query()
        ->where('status', PetListingStatus::Published)
        ->where('listing_type', PetListingType::Sale)
        ->with(['petCategory', 'breed', 'images', 'seller'])
        ->latest()
        ->take(6)
        ->get();

    // 2. Featured Adoption & Rescue Highlights (Separate from commercial)
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

    // 3. Category discovery counts
    $petCategories = PetCategory::query()
        ->withCount(['petListings' => fn ($query) => $query->where('status', PetListingStatus::Published)])
        ->get();

    // 4. Breed discovery
    $featuredBreeds = Breed::query()
        ->with('petCategory')
        ->latest()
        ->take(4)
        ->get();

    // 5. Featured Shop Products
    $featuredProducts = Product::query()
        ->published()
        ->with('category')
        ->latest()
        ->take(4)
        ->get();

    $pricing = app(PricingService::class);
    foreach ($featuredProducts as $product) {
        $quote = $pricing->quote(
            [(int) $product->id => 1], SalesChannel::Online,
            $product->branch_id === null ? null : (int) $product->branch_id,
        );
        $freeShippingThreshold = (int) \App\Facades\Settings::get('shop.free_shipping_threshold_minor', 0);
        $configuredFee = (int) \App\Facades\Settings::get('shop.delivery_fee_minor', 0);
        $quote['delivery_fee_minor'] = ($freeShippingThreshold > 0 && $quote['subtotal_minor'] >= $freeShippingThreshold)
            ? 0
            : $configuredFee;
        $product->setAttribute('display_price_minor', $quote['subtotal_minor']);
        $product->setAttribute('display_resolved_price_minor', $quote['lines'][$product->id]['resolved_unit_minor']);
        $product->setAttribute('display_total_minor', $quote['subtotal_minor'] + $quote['delivery_fee_minor']);
        $product->setAttribute('pricing_signature', $pricing->signature($quote));
        $product->setAttribute('pricing_issued_at', $quote['issued_at']);
        $product->setAttribute('display_promotion_discount_minor', $quote['promotion_discount_minor']);
        $details = [];
        if ($quote['promotion_discount_minor'] > 0) {
            $details[] = 'Promotion discount: −'.$quote['currency'].' '.number_format($quote['promotion_discount_minor'] / 100, 2);
        }
        $details[] = 'Delivery: '.$quote['currency'].' '.number_format($quote['delivery_fee_minor'] / 100, 2);
        $product->setAttribute('display_price_details', implode(' · ', $details));
    }

    return view('welcome', [
        'featuredListings' => $featuredListings,
        'featuredAdoptions' => $featuredAdoptions,
        'petCategories' => $petCategories,
        'featuredBreeds' => $featuredBreeds,
        'featuredProducts' => $featuredProducts,
    ]);
};

Route::get('/home', $renderHomepage)->name('public.home');

Route::get('careers', [CareersController::class, 'index'])->name('careers.index');
Route::get('careers/{slug}', [CareersController::class, 'show'])->name('careers.show');
Route::post('careers/{slug}/apply', [CareersController::class, 'apply'])->middleware('throttle:5,10,careers-application')->name('careers.apply');
Route::get('career-offers/complete', [CareerOfferController::class, 'complete'])->name('careers.complete');
Route::get('career-offers/verify', [CareerOfferController::class, 'verifyPage'])->name('careers.offers.verify');
Route::post('career-offers/verify', [CareerOfferController::class, 'verify'])->middleware('throttle:5,10,career-otp-verify')->name('careers.offers.verify.submit');
Route::post('career-offers/verify/resend', [CareerOfferController::class, 'resendChallenge'])->middleware('throttle:3,10,career-otp-resend')->name('careers.offers.verify.resend');
Route::get('career-offers/{token}', [CareerOfferController::class, 'show'])->where('token', '[A-Za-z0-9]+')->name('careers.offers.show');
Route::post('career-offers/{token}/accept', [CareerOfferController::class, 'accept'])->middleware('throttle:5,10,career-offer-accept')->where('token', '[A-Za-z0-9]+')->name('careers.offers.accept');
Route::post('career-offers/{token}/decline', [CareerOfferController::class, 'decline'])->middleware('throttle:5,10,career-offer-decline')->where('token', '[A-Za-z0-9]+')->name('careers.offers.decline');
Route::post('career-offers/{token}/resend', [CareerOfferController::class, 'resend'])->middleware('throttle:3,10,career-offer-otp-resend')->where('token', '[A-Za-z0-9]+')->name('careers.offers.resend');

Route::get('/', function (Request $request) use ($renderHomepage) {
    $user = $request->user();

    if ($user) {
        if ($user->isAdmin() || $user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isSeller()) {
            return redirect()->route('seller.dashboard');
        }

        abort_unless($user->isCustomer(), 403, 'Access denied. A recognized account role is required.');

        return redirect()->route('dashboard');
    }

    return $renderHomepage();
})->name('home');

// Public Pet Marketplace Browsing & Detail Routes
Route::get('listings', [ListingBrowseController::class, 'index'])->name('listings.index');
Route::get('listings/{listing}', [ListingBrowseController::class, 'show'])->name('listings.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        /** @var User $user */
        $user = auth()->user();

        if ($user->isAdmin() || $user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isSeller()) {
            return redirect()->route('seller.dashboard');
        }

        abort_unless($user->isCustomer(), 403, 'Access denied. A recognized account role is required.');

        $orders = $user->orders()
            ->with([
                'items.productReturns.reviewedBy', 'items.productReturns.receivedBy', 'items.product', 'petListing.images', 'seller',
                'paymentTransactions.refunds.initiatedBy', 'paymentTransactions.refunds.approvedBy',
                'fulfilmentEvents' => fn ($events) => $events->select(['id', 'order_id', 'event_type', 'to_status', 'buyer_message', 'created_at'])
                    ->whereNotNull('buyer_message')->orderBy('created_at'),
            ])
            ->latest()
            ->paginate(10);

        $savedPets = $user->savedPets()
            ->where('pet_listings.status', PetListingStatus::Published)
            ->with(['petCategory', 'breed', 'images', 'seller'])
            ->latest()
            ->get();

        $myListings = $user->petListings()
            ->with(['petCategory', 'breed', 'images', 'moderationActions'])
            ->latest()
            ->get();

        return view('dashboard', compact('orders', 'savedPets', 'myListings'));
    })->name('dashboard');

    Route::middleware('customer')->group(function () {
        Route::post('orders/{order}/cancel', function (
            Order $order,
            OrderInventoryService $inventory,
            FulfilmentService $fulfilment,
            Request $request,
        ) {
            Gate::authorize('cancel', $order);

            if ($order->company_id !== null && $order->seller_id === null && $order->pet_listing_id === null) {
                $cancelled = $fulfilment->cancelByBuyer($order, $request->user());

                return $cancelled
                    ? back()->with('success', "Your order #{$order->order_number} has been cancelled.")
                    : back()->with('error', 'This order can no longer be cancelled at its current stage.');
            }

            $cancelledOrder = DB::transaction(function () use ($order, $inventory, $request): ?Order {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                Gate::authorize('cancel', $order);

                if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
                    return null;
                }

                $order->status = OrderStatus::Cancelled;
                $order->save();
                $inventory->restoreReservedInventory($order, $request->user());

                return $order;
            });

            if ($cancelledOrder === null) {
                return back()->with('error', 'This request cannot be cancelled at its current stage.');
            }

            return back()->with('success', "Your request #{$cancelledOrder->order_number} has been cancelled.");
        })->name('orders.cancel');

        Route::post('orders/quote', [OrderRequestController::class, 'quote'])->name('orders.quote');
        Route::post('orders/request', [OrderRequestController::class, 'store'])->name('orders.request');
        Route::post('orders/{order}/refunds', [PaymentRefundController::class, 'request'])->name('orders.refunds.store');
        Route::post('orders/{order}/items/{item}/returns', [ProductReturnController::class, 'store'])->name('orders.items.returns.store');

        Route::post('listings/{listing}/purchase', [ListingBrowseController::class, 'purchase'])->name('listings.purchase');
        Route::post('listings/{listing}/inquire', [ListingBrowseController::class, 'inquire'])->name('listings.inquire');
        Route::post('listings/{listing}/favorite', [ListingBrowseController::class, 'toggleFavorite'])->name('listings.favorite');
    });
});

Route::middleware('guest')->prefix('login/email-otp')->name('login.otp.')->group(function () {
    Route::get('/', [EmailOtpChallengeController::class, 'show'])->name('show');
    Route::post('/', [EmailOtpChallengeController::class, 'verify'])->middleware('throttle:5,1')->name('verify');
    Route::post('resend', [EmailOtpChallengeController::class, 'resend'])->middleware('throttle:3,1')->name('resend');
    Route::post('cancel', [EmailOtpChallengeController::class, 'cancel'])->name('cancel');
});

Route::middleware('guest')->prefix('invitations')->name('invitations.')->group(function () {
    Route::get('{token}', [InvitationRegistrationController::class, 'show'])->name('register');
    Route::post('{token}', [InvitationRegistrationController::class, 'complete'])->name('complete');
});

// Passkey login is disabled until it can complete the mandatory OTP/TOTP policy.
Route::get(RoutePath::for('passkey.login-options', '/passkeys/login/options'), function () {
    abort(404);
})->name('passkey.login-options');

Route::post(RoutePath::for('passkey.login', '/passkeys/login'), function () {
    abort(404);
})->name('passkey.login');

// Dedicated seller authentication shortcuts
Route::get('seller/login', function () {
    return redirect()->route('login', ['role' => 'seller']);
})->name('seller.login');

Route::get('seller/register', function () {
    return redirect()->route('register');
})->name('seller.register');

require __DIR__.'/admin.php';
require __DIR__.'/seller.php';
require __DIR__.'/settings.php';
