<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BrandIdentityController;
use App\Http\Controllers\Admin\BreedController;
use App\Http\Controllers\Admin\BusinessAccessController;
use App\Http\Controllers\Admin\BusinessSettingController;
use App\Http\Controllers\Admin\CommunityController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CareersAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeApprovalController;
use App\Http\Controllers\Admin\HomepageCustomizerController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\MarketplaceCategoryController;
use App\Http\Controllers\Admin\MarketplaceListingController;
use App\Http\Controllers\Admin\MarketplaceModerationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\EnsureBusinessAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {

    // 1. Overview Dashboard (accessible to staff & admin)
    Route::middleware('staff_or_admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('careers/approvals', [EmployeeApprovalController::class, 'index'])->name('careers.approvals.index');
        Route::post('careers/approvals/{offer}/approve', [EmployeeApprovalController::class, 'approve'])->name('careers.approvals.approve');
        Route::post('careers/approvals/{offer}/reject', [EmployeeApprovalController::class, 'reject'])->name('careers.approvals.reject');

        // Company setup and employee operations apply their own role-aware policies.
        Route::prefix('business')->name('business.')->group(function (): void {
            Route::get('/', [BusinessAccessController::class, 'index'])->name('index');
            Route::post('companies', [BusinessAccessController::class, 'storeCompany'])->name('companies.store');
            Route::put('companies/{company}', [BusinessAccessController::class, 'updateCompany'])->name('companies.update');
            Route::post('branches', [BusinessAccessController::class, 'storeBranch'])->name('branches.store');
            Route::post('memberships', [BusinessAccessController::class, 'storeMembership'])->name('memberships.store');
            Route::delete('memberships/{membership}', [BusinessAccessController::class, 'destroyMembership'])->name('memberships.destroy');
        });

        Route::middleware(EnsureBusinessAccess::class)->group(function (): void {
            Route::resource('products', ProductController::class)->except(['show']);
            Route::get('categories', [ProductCategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [ProductCategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [ProductCategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [ProductCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::patch('inventory/{product}', [InventoryController::class, 'update'])->name('inventory.update');

            // Commerce & Customer Orders
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
            Route::patch('orders/{order}/payment', [OrderController::class, 'updatePayment'])->name('orders.update-payment');

            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');

        });

        // Employee invitations are scoped by InvitationService to the Shop Owner.
        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::get('invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('settings/shop', [BusinessSettingController::class, 'shop'])->name('settings.shop');
        Route::put('settings/shop', [BusinessSettingController::class, 'updateShop'])->name('settings.shop.update');
        Route::post('settings/branches', [BusinessSettingController::class, 'storeBranch'])->name('settings.branches.store');
        Route::delete('settings/branches/{branch}', [BusinessSettingController::class, 'destroyBranch'])->name('settings.branches.destroy');
    });

    // 2. Strict Administrator Only Sections
    Route::middleware('admin')->group(function () {
        Route::get('careers/vacancies', [CareersAdminController::class, 'vacancies'])->name('careers.vacancies.index');
        Route::get('careers/vacancies/create', [CareersAdminController::class, 'createVacancy'])->name('careers.vacancies.create');
        Route::post('careers/vacancies', [CareersAdminController::class, 'storeVacancy'])->name('careers.vacancies.store');
        Route::get('careers/vacancies/{vacancy}/edit', [CareersAdminController::class, 'editVacancy'])->name('careers.vacancies.edit');
        Route::put('careers/vacancies/{vacancy}', [CareersAdminController::class, 'updateVacancy'])->name('careers.vacancies.update');
        Route::get('careers/applications', [CareersAdminController::class, 'applications'])->name('careers.applications.index');
        Route::get('careers/applications/{application}', [CareersAdminController::class, 'showApplication'])->name('careers.applications.show');
        Route::post('careers/applications/{application}/review', [CareersAdminController::class, 'reviewApplication'])->name('careers.applications.review');
        Route::get('careers/applications/{application}/cv', [CareersAdminController::class, 'downloadCv'])->name('careers.applications.cv');
        Route::post('careers/applications/{application}/offers', [CareersAdminController::class, 'issueOffer'])->name('careers.applications.offers.store');
        Route::post('careers/offers/{offer}/resend', [CareersAdminController::class, 'resendOffer'])->name('careers.offers.resend');
        Route::delete('careers/offers/{offer}', [CareersAdminController::class, 'revokeOffer'])->name('careers.offers.revoke');

        // Website & Branding
        Route::get('brand', [BrandIdentityController::class, 'index'])->name('brand.index');
        Route::put('brand', [BrandIdentityController::class, 'update'])->name('brand.update');

        Route::get('homepage', [HomepageCustomizerController::class, 'index'])->name('homepage.index');
        Route::put('homepage', [HomepageCustomizerController::class, 'update'])->name('homepage.update');
        Route::post('homepage/draft', [HomepageCustomizerController::class, 'saveDraft'])->name('homepage.draft');
        Route::get('homepage/preview', [HomepageCustomizerController::class, 'previewDraft'])->name('homepage.preview');
        Route::post('homepage/publish', [HomepageCustomizerController::class, 'publishDraft'])->name('homepage.publish');
        Route::post('homepage/discard', [HomepageCustomizerController::class, 'discardDraft'])->name('homepage.discard');

        // Marketplace Moderation & Categories
        Route::get('marketplace/moderation', [MarketplaceModerationController::class, 'index'])->name('marketplace.moderation.index');
        Route::post('marketplace/moderation/{listing}/approve', [MarketplaceModerationController::class, 'approve'])->name('marketplace.moderation.approve');
        Route::post('marketplace/moderation/{listing}/reject', [MarketplaceModerationController::class, 'reject'])->name('marketplace.moderation.reject');

        Route::get('marketplace/categories', [MarketplaceCategoryController::class, 'index'])->name('marketplace.categories.index');
        Route::post('marketplace/categories', [MarketplaceCategoryController::class, 'store'])->name('marketplace.categories.store');
        Route::delete('marketplace/categories/{category}', [MarketplaceCategoryController::class, 'destroy'])->name('marketplace.categories.destroy');

        // Platform-wide marketplace catalogue and community administration.
        Route::get('marketplace/listings', [MarketplaceListingController::class, 'index'])->name('marketplace.listings.index');
        Route::get('marketplace/listings/{listing}', [MarketplaceListingController::class, 'show'])->name('marketplace.listings.show');
        Route::get('breeds', [BreedController::class, 'index'])->name('breeds.index');
        Route::post('breeds', [BreedController::class, 'store'])->name('breeds.store');
        Route::delete('breeds/{breed}', [BreedController::class, 'destroy'])->name('breeds.destroy');
        Route::get('community', [CommunityController::class, 'index'])->name('community.index');
        Route::put('community', [CommunityController::class, 'update'])->name('community.update');

        // People, Staff Roles & Activity Logs
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.update-role');

        Route::get('system', [SystemHealthController::class, 'index'])->name('system.index');

        // Platform-wide feature flags.
        Route::get('settings/features', [BusinessSettingController::class, 'features'])->name('settings.features');
        Route::put('settings/features', [BusinessSettingController::class, 'updateFeatures'])->name('settings.features.update');
    });
});

// Dedicated Internal Admin Authentication Portal
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');

        // Email-Based Two-Factor Authentication (Mandatory for Admin & Staff)
        Route::get('two-factor', [AdminAuthController::class, 'showTwoFactorForm'])->name('two-factor');
        Route::post('two-factor', [AdminAuthController::class, 'verifyTwoFactor'])->middleware('throttle:5,1')->name('two-factor.store');
        Route::post('two-factor/resend', [AdminAuthController::class, 'resendTwoFactor'])->middleware('throttle:3,1')->name('two-factor.resend');
        Route::post('two-factor/cancel', [AdminAuthController::class, 'cancelTwoFactor'])->name('two-factor.cancel');
    });

    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout')->middleware('auth');
});
