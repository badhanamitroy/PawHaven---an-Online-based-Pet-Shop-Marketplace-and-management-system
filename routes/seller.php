<?php

use App\Http\Controllers\Seller\SellerDashboardController;
use App\Http\Controllers\Seller\SellerListingController;
use App\Http\Controllers\Seller\SellerRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'seller'])->prefix('seller')->name('seller.')->group(function () {
    // Seller Dashboard
    Route::get('dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');

    // Listing Operations
    Route::get('listings', [SellerListingController::class, 'index'])->name('listings.index');
    Route::get('listings/create', [SellerListingController::class, 'create'])->name('listings.create');
    Route::post('listings', [SellerListingController::class, 'store'])->name('listings.store');
    Route::get('listings/{listing}/edit', [SellerListingController::class, 'edit'])->name('listings.edit');
    Route::put('listings/{listing}', [SellerListingController::class, 'update'])->name('listings.update');
    Route::delete('listings/{listing}', [SellerListingController::class, 'destroy'])->name('listings.destroy');
    Route::patch('listings/{listing}/status', [SellerListingController::class, 'updateStatus'])->name('listings.status');

    // Purchase & Adoption Requests
    Route::get('requests', [SellerRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/{order}', [SellerRequestController::class, 'show'])->name('requests.show');
    Route::patch('requests/{order}/status', [SellerRequestController::class, 'updateStatus'])->name('requests.status');
    Route::patch('requests/{order}/payment', [SellerRequestController::class, 'verifyPayment'])->name('requests.payment');
});
