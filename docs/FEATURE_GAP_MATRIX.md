# PawHaven — Feature Gap Matrix & Inventory

**Document Version:** 1.0  
**Audit Baseline:** October 30, 2026 (`docs/PROJECT_AUDIT.md`)  
**Scope:** Core system domains (Auth, Admin, Website Manager, Public Website, Seller, Customer, Commerce, Community, Platform Security).

---

## 1. Classification Definitions

Every functional capability is assessed and assigned one of five strict architectural states:
- **`Reuse as-is`**: Fully implemented, verified with tests, and meets all PawHaven architectural standards.
- **`Repair`**: Implementation exists but contains defects, routing contradictions, or test failures that must be rectified.
- **`Extend`**: Solid foundational implementation exists (schema/controller/view) but requires additional workflows or integration.
- **`Build new`**: Feature is specified or required by the platform mission but currently has no implementation.
- **`Defer`**: Non-essential feature that should be postponed to later milestones (e.g. Kotlin Android app, complex multi-branch logistics).

---

## 2. Comprehensive Domain Matrix

### A. Authentication & Role-Based Access Control (RBAC)

| Feature / Capability | Classification | Priority | Existing Asset / Evidence | Exact Gap / Defect | Compatibility & Action Required |
| :--- | :---: | :---: | :--- | :--- | :--- |
| **User Registration** | `Reuse as-is` | P0 | `App\Actions\Fortify\CreateNewUser`, `resources/views/pages/auth/register.blade.php` | None. Validates name, email, password, role (`in:customer,seller`), securely downgrades `admin` attempts. | Keep existing implementation intact. |
| **Customer Login & Redirect** | `Reuse as-is` | P0 | `app/Http/Responses/LoginResponse.php`, `tests/Feature/Auth/AuthenticationTest.php` | None. Intended URL handling and redirection to `route('dashboard')` verified. | Maintain session clearing for `/admin` and `/seller`. |
| **Admin Login & Email OTP 2FA** | `Reuse as-is` | P0 | `AdminAuthController`, `EmailOtpService`, `resources/views/admin/auth/` | None. Dedicated administrative gateway with rate limiting and 6-digit OTP verified. | Retain as the primary admin access path. |
| **Seller Post-Login Redirection** | `Repair` | P0 | `app/Http/Responses/LoginResponse.php:35` | Calls `route('seller.dashboard')` which is undefined, crashing with 500 RouteNotFoundException. | Fix route definition or redirect to authorized seller dashboard. |
| **Seller `/dashboard` Routing** | `Repair` | P0 | `routes/web.php:34`, `EnsureUserIsStaffOrAdmin.php` | `web.php` redirects seller to `admin.dashboard`, but `EnsureUserIsStaffOrAdmin` rejects seller with 403. | Clarify seller boundary: redirect sellers to `/seller/dashboard`. |
| **Role Verification Middleware** | `Repair` | P0 | `EnsureUserIsAdmin`, `EnsureUserIsStaffOrAdmin` | Missing dedicated `EnsureUserIsSeller` middleware; seller role ambiguous between shopkeeper and pet lister. | Introduce clean `EnsureUserIsSeller` and align operational access. |
| **User Profile & Password Update** | `Reuse as-is` | P1 | `routes/settings.php`, `resources/views/pages/settings/` | None. Verified by `ProfileUpdateTest` and `SecurityTest` (8/8 and 6/6 passing). | Maintain Flux UI Livewire components. |
| **Account Deletion Safeguards** | `Reuse as-is` | P1 | `⚡delete-user-modal.blade.php`, `ProfileUpdateTest` | None. Blocks deletion if user has active pet listings (`User::petListings()`). | Preserve Eloquent constraint check. |

---

### B. Admin Dashboard & Operations Backend

| Feature / Capability | Classification | Priority | Existing Asset / Evidence | Exact Gap / Defect | Compatibility & Action Required |
| :--- | :---: | :---: | :--- | :--- | :--- |
| **Overview Dashboard Metrics** | `Reuse as-is` | P0 | `Admin\DashboardController@index`, `resources/views/admin/dashboard.blade.php` | None. Real queries for daily sales, low stock, pending orders, and moderation queue. | Preserve queries and KPI layout. |
| **Product Catalog Management** | `Reuse as-is` | P0 | `Admin\ProductController`, `resources/views/admin/products/` | None. Full CRUD, SKU generation, image upload, price minor conversion, restrict delete on order items. | Preserve product data models. |
| **Product Categories** | `Reuse as-is` | P1 | `Admin\ProductCategoryController`, `resources/views/admin/categories/` | None. Real-time product counters and active status toggles. | Maintain existing schema. |
| **Inventory Stock Adjustments** | `Reuse as-is` | P0 | `Admin\InventoryController`, `resources/views/admin/inventory/` | None. Inline increment/decrement stock actions with threshold alert. | Retain inventory view. |
| **Order Processing & Invoicing** | `Reuse as-is` | P0 | `Admin\OrderController`, `OrderStatus` enum, `resources/views/admin/orders/` | None. State machine transitions, cancellation notes, Cash on Delivery verification. | Keep order state machine. |
| **Customer Spending Directory** | `Reuse as-is` | P1 | `Admin\CustomerController`, `resources/views/admin/customers/` | None. Lifetime spend aggregated from delivered orders. | Retain as customer analytics. |
| **Marketplace Moderation Queue** | `Reuse as-is` | P0 | `Admin\MarketplaceModerationController`, `resources/views/admin/marketplace/` | None. Approve/reject actions with audit logging to `listing_moderation_actions`. | Keep moderation action logging. |
| **Species Categories & Breeds** | `Reuse as-is` | P1 | `MarketplaceCategoryController`, `BreedController`, `resources/views/admin/breeds/` | None. Full administrative CRUD linked to parent pet categories. | Retain schema and models. |
| **Staff & User Role Administration** | `Reuse as-is` | P0 | `Admin\UserController`, `resources/views/admin/users/` | None. Protects against self-demotion and demoting the last active administrator. | Maintain anti-lockout safeguards. |
| **Administrative Audit Trail** | `Reuse as-is` | P1 | `Admin\AuditLogController`, `AdminAuditLog` model, `resources/views/admin/audit-logs/` | None. Searchable log with modal JSON payload inspection. | Retain audit log table. |
| **Shop Settings (Delivery & COD)** | `Repair` | P0 | `Admin\BusinessSettingController@shop`, `resources/views/admin/settings/shop.blade.php` | Delivery fee saved to `Settings`, but `OrderRequestController` hardcodes fee as 0. | Connect order creation logic to read from `Settings::get('shop.delivery_fee_minor')`. |
| **Feature Flags & Branches** | `Reuse as-is` | P2 | `BusinessSettingController@features`, `Branch` model | None. Multi-branch and multi-staff flags default to disabled. | Keep feature flag gating. |

---

### C. Website Manager & Branding

| Feature / Capability | Classification | Priority | Existing Asset / Evidence | Exact Gap / Defect | Compatibility & Action Required |
| :--- | :---: | :---: | :--- | :--- | :--- |
| **Brand Identity & Color Tokens** | `Reuse as-is` | P0 | `BrandIdentityController`, `resources/views/admin/brand/` | None. Persists brand name, colors, contact details, social links, meta description. | Retain custom brand palette. |
| **Brand Logo Upload & Storage** | `Reuse as-is` | P0 | `BrandIdentityController@update`, `storage/app/public/branding/` | Fixed broken junction (`php artisan storage:link`). Logos load with HTTP 200 OK. | Retain secure upload validator. |
| **Homepage Copy Customization** | `Reuse as-is` | P0 | `HomepageCustomizerController`, `resources/views/admin/homepage/` | None. Live editor for headline, supporting text, CTA labels, and announcement banner. | Retain default headline fallback. |
| **Section Visibility Toggles** | `Reuse as-is` | P0 | `HomepageCustomizerController@update`, `resources/views/welcome.blade.php` | None. Controls visibility of `#find-pets`, `#pet-shop`, `#adoption-rescue`, `#breeds`. | Maintain boolean settings. |
| **Section Ordering / Reorder** | `Extend` | P2 | None (sections render in fixed sequence in `welcome.blade.php`) | Sections have fixed order in Blade. Admin cannot change section sequence. | Add optional `order` array to `homepage.sections` settings. |
| **Draft / Preview Mode** | `Extend` | P2 | None (settings apply immediately upon save) | No staging view to preview unpublished homepage changes. | Provide preview parameter (`/?preview=true`) accessible to admins. |
| **Settings Version History** | `Defer` | P2 | `AdminAuditLog` logs action description | Does not store previous JSON values for one-click rollback. | Reliable rollback requires dedicated schema; defer to post-launch. |

---

### D. Public Website & Homepage

| Feature / Capability | Classification | Priority | Existing Asset / Evidence | Exact Gap / Defect | Compatibility & Action Required |
| :--- | :---: | :---: | :--- | :--- | :--- |
| **Public Landing Page** | `Repair` | P0 | `resources/views/welcome.blade.php` | Links to undefined `route('seller.dashboard')` (lines 95, 186, 1251) which crashes for logged-in sellers. | Update links to route to the resolved seller dashboard. |
| **Dynamic Brand Identity** | `Reuse as-is` | P0 | `welcome.blade.php:1-18`, `Settings` facade | None. Reads brand name, tagline, announcement text, and logos dynamically. | Maintain dynamic bindings. |
| **Featured Listings Grid** | `Reuse as-is` | P0 | `routes/web.php:9-28`, `welcome.blade.php:240-360` | None. Dynamic query fetching `PetListingStatus::Published` with relationships and fallback. | Maintain query scoping. |
| **Single-Store Products Grid** | `Reuse as-is` | P0 | `routes/web.php:17-23`, `welcome.blade.php:590-720` | None. Displays published database products with price minors and fallback preview cards. | Retain dynamic product cards. |
| **Single-Product Buy Modal** | `Reuse as-is` | P0 | `welcome.blade.php:1300-1360`, `OrderRequestController` | None. Alpine.js modal submitting purchase requests directly to `/orders/request`. | Retain modal flow for quick orders. |
| **Public Listing Detail Page** | `Build new` | P1 | Card CTAs link to `#find-pets` anchor | Missing dedicated `/listings/{listing:slug}` view with gallery, seller info, and inquiry form. | Implement dedicated listing detail view and route. |
| **Public Breed Detail Page** | `Build new` | P1 | Card CTAs link to `#breeds` anchor | Missing dedicated `/breeds/{breed:slug}` view with care information and temperament traits. | Implement dedicated breed detail view and route. |
| **Adoption & Rescue Hub** | `Extend` | P1 | `welcome.blade.php:730-890` (`#adoption-rescue`) | Represented as homepage section; lacks dedicated filterable adoption directory page. | Add dedicated `/adoption` route with filtered queries for `listing_type in [adoption, rescue, rehoming]`. |

---

### E. Seller Dashboard & Pet Marketplace

| Feature / Capability | Classification | Priority | Existing Asset / Evidence | Exact Gap / Defect | Compatibility & Action Required |
| :--- | :---: | :---: | :--- | :--- | :--- |
| **Seller Dashboard View** | `Build new` | P0 | None (`route('seller.dashboard')` is missing) | Sellers have no interface to monitor their listings, view moderation outcomes, or manage inquiries. | Build dedicated `/seller/dashboard` route, controller, and layout. |
| **Seller Listings Inventory** | `Build new` | P0 | `PetListingPolicy`, `User::petListings()` | Sellers cannot view a table of their listings filtered by status (`Draft`, `Pending`, `Published`, `Rejected`). | Implement `/seller/listings` table with edit/delete controls. |
| **Listing Creation Wizard** | `Build new` | P0 | `PetListingValidationRules`, `PetListingPolicy@create` | No UI form for sellers to submit pets with multiple photos, categories, breeds, and pricing. | Build multi-step or comprehensive submission form at `/seller/listings/create`. |
| **Listing Image Uploads** | `Build new` | P0 | `listing_images` table, `ListingImage` model | No seller image upload handler with order/position assignment. | Build image upload handler enforcing max 5 images, 2MB each, positions 0..4. |
| **Listing Moderation Status Notice** | `Build new` | P0 | `listing_moderation_actions` table | Sellers cannot view admin rejection notes to correct and resubmit listings. | Display moderation timeline and admin notes on the seller listing detail page. |
| **Seller Purchase Inquiries** | `Extend` | P1 | `OrderPolicy`, `orders` table | Currently, `Order` belongs to `User` (customer) and `OrderItem` belongs to single-store `Product`. Marketplace pet inquiries are not modeled as orders. | Implement clean `PetInquiry` model or structured inquiry message log linked to `PetListing`. |

---

### F. Customer Dashboard & E-Commerce Cart

| Feature / Capability | Classification | Priority | Existing Asset / Evidence | Exact Gap / Defect | Compatibility & Action Required |
| :--- | :---: | :---: | :--- | :--- | :--- |
| **Customer Overview Metrics** | `Reuse as-is` | P0 | `resources/views/dashboard.blade.php` | None. Displays real counts for listings, shop orders, and verified account status. | Retain existing metric cards. |
| **Buy Requests / Orders Table** | `Reuse as-is` | P0 | `dashboard.blade.php:90-210`, `User::orders()` | None. Paginated table with order number, line items, status pills, delivery fee, and total. | Retain order history table. |
| **Shopping Cart (Session Store)** | `Build new` | P1 | Navigation cart icon displays static badge `0` linking to `#pet-shop` | No multi-item cart session or live update mechanism. | Implement session-backed cart service with add/remove/update quantity actions. |
| **Multi-Item COD Checkout** | `Build new` | P1 | `OrderRequestController` only handles single product | No multi-item checkout view calculating delivery fees and thresholds dynamically from `Settings`. | Implement `/checkout` route and view supporting Cash on Delivery. |
| **Saved / Favorite Pets** | `Extend` | P2 | None | No bookmark/favorites table or button on listing cards. | Add lightweight `favorite_listings` pivot table and dashboard tab. |
| **Customer Pet Rehoming Posts** | `Reuse as-is` | P1 | `dashboard.blade.php:35-48`, `User::petListings()` | None. Customers who post rehoming listings can view their count directly on dashboard. | Retain listing counter and link to listings. |

---

### G. Security, Self-Hosting & Deployment

| Feature / Capability | Classification | Priority | Existing Asset / Evidence | Exact Gap / Defect | Compatibility & Action Required |
| :--- | :---: | :---: | :--- | :--- | :--- |
| **Mass-Assignment Defense** | `Reuse as-is` | P0 | `User.php:32` (`#[Fillable]`) | None. Role cannot be injected via registration or profile update. | Retain attribute-level protection. |
| **IDOR Protection on Listings** | `Reuse as-is` | P0 | `App\Policies\PetListingPolicy` | None. Ensures sellers can only update/delete their own listings; admins moderate. | Retain policy checks in controllers. |
| **IDOR Protection on Orders** | `Reuse as-is` | P0 | `App\Policies\OrderPolicy` | None. Customers view only their own orders (`order->user_id === user->id`); staff/admin view all. | Retain policy checks in controllers. |
| **Self-Hosted Asset Isolation** | `Repair` | P1 | `ShopSeeder` references Unsplash URLs | In air-gapped or offline hosting, external images fail to render. | Bundle fallback product images in `public/images/products/`. |
| **Zero Telemetry / Zero SaaS** | `Reuse as-is` | P0 | Entire codebase | None. Zero remote licensing checks, zero hidden cloud calls, zero SaaS dependencies. | Preserve complete buyer self-hosting independence. |

