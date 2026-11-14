# PawHaven Redesign Roadmap

**Status:** Audit-first proposal; no application behavior/source code changed in this audit.  
**Preservation constraints:** retain the existing Laravel application, SQLite/production data, uploaded files, authentication/OTP, authorization and historical orders. Any production schema change must be additive, backed up, reversible where practical, and tested against a copy of existing data. Never use destructive refresh/wipe workflows.

## Current state

- Laravel 13.35 / PHP 8.5 full-stack monolith; Blade, Livewire and Tailwind CSS v4.
- Active local SQLite snapshot contains real records (8 users, 4 pet listings, 4 products, 2 orders and audit/settings records); it was read-only during this audit.
- Homepage, public pet browse/detail, customer/seller dashboards, admin workspaces, OTP and listing moderation exist. 176 automated tests pass.
- The product shop is single-store and request-based; pet sale/adoption/rescue listings are seller-owned. No cart/payment gateway, product detail, reviews, user content reporting or general community timeline exists.
- Design tokens already include the proposed brand palette, but template-level values and breakpoint behavior are inconsistent. The 15-second cold-load report was not reproduced; first-browser TTFB was ~1.384 s, warmed ~0.110 s.
- Current reports and tests are a baseline, not a production certification. The prior October 30 audit is stale in at least its “seller dashboard missing” claim.

## Problems to solve (verified vs pending decisions)

| Problem | Evidence | Dependency/decision |
|---|---|---|
| Single homepage mixes marketplace, shop, adoption and educational intents | Homepage uses one long multi-section Blade page; nav is mainly anchor-based | Agree on top-level navigation and keep three page families distinct |
| No full public product journey | No product browse/detail/cart routes; header cart is an anchor | Decide whether and when to build cart/checkout and its data model |
| Adoption/community capability gap | Listing inquiries exist; general posts/reports/comments do not | Define rescue/adoption governance and moderation policy before new entities |
| Admin is a flat/long destination list | Current nav groups Overview/My Website/My Business | Approve the proposed main-tab → subtab → workspace IA; map existing routes first |
| Responsive navigation clips at an intermediate breakpoint | Browser measured ~95px horizontal overflow at ~772 CSS px | Re-test with CSS viewport controls and approve breakpoint behavior |
| Product order can reference hidden/out-of-stock product; order/item writes are non-atomic | `OrderRequestController` validates existence; creates two records separately | Agree order semantics, stock reservation and failure/rollback policy |
| Admin login role mismatch | Seller passes admin OTP flow, but admin middleware denies seller | Explicitly decide whether seller accounts should ever use `/admin/login` |
| Data/perf risk as volume grows | No explicit order owner / order-item secondary indexes; customer lists unbounded in dashboard | Capture realistic query plans and expected data volume before index/pagination changes |
| International defaults unresolved | Local config defaults USD/US-like contact text; no locale/currency localization layer | Decide launch country/locale, currency, timezone, tax, shipping, language and payment provider |

## Implementation order and gates

| Phase | Current state → proposed improvement | Dependencies | Risk | Acceptance criteria |
|---|---|---|---|---|
| 0. Baseline and decisions | Audit current app/data; freeze behavior and approve scope before redesign | Owner approval; backups/snapshot policy; launch market decisions | Low | Existing 176 tests pass; key public/admin/seller/customer flows captured; all protected data is tested on a non-production copy |
| 1. Semantic design foundation | Existing green/ivory palette → semantic tokens, coherent typography/spacing/radius/state rules and shared primitives | Approve spelling/logo, tokens and contrast pairs | Medium (visual regression) | Contrast checked for all token pairs; no horizontal overflow at target widths/zoom; existing Blade layouts and route behavior remain intact |
| 2. Public information architecture | Anchor-heavy homepage → clear entry points for shop/marketplace, adoption/rescue and learning | Phase 1; navigation/content decisions | Medium | All current destinations still resolve; search, filters, product/adoption cards and errors/empty states are clear; page-specific browser regression passes |
| 3. Shop/marketplace journey | Featured-only shop/request flow → approved browse/detail/cart/checkout design; enforce publish/stock constraints and atomic persistence | Product decision on cart persistence, checkout, tax/currency/delivery/payment; migration plan if data model needed | High (orders, money, inventory) | Existing orders unchanged; tests cover hidden/unavailable product, stock concurrency, order rollback and historical item snapshots; no payment-success claim without provider confirmation |
| 4. Adoption/rescue and education | Listing-only adoption + generic homepage content → distinct trust-first adoption and reading experiences; add reporting/community only if approved | Moderation roles, data/privacy/retention policy and explicit feature scope | High if introducing user-generated content | Existing listing moderation preserved; owner isolation tested; reports/comments only appear after backed workflows and permission tests exist |
| 5. Customer and seller workspaces | Existing dashboard pages → role-specific, task-based navigation and paginated scalable activity | Phases 1–3; confirmed order/status semantics | Medium | Customer cannot read another user's data; seller sees only their own listings/orders; all existing profile, cancellation and listing paths continue passing |
| 6. Admin information architecture | Current Overview/My Website/My Business list → main tab → subtab → dedicated workspaces; map existing features and mark gaps | Approve the IA mapping; do not present missing controls as implemented | Medium/High (admin access) | Every existing route remains reachable to the same role; no unauthorized access; pending actions, audit and moderation paths are test-covered |
| 7. Security, data integrity and performance release gate | Fix confirmed order/auth boundary gaps; measure before tuning; preserve historical records | Phases 3–6; production-like config and DB copy; deployment/backup plan | High | Production-like 176-test baseline plus focused tests pass; TTFB/query/LCP budgets agreed and measured; OTP/hash unchanged; data/file restore rehearsal passes |

## Admin navigation target mapped to actual capability

| Proposed section/subsection | Current capability | Classification |
|---|---|---|
| Overview → Dashboard | `admin.dashboard` metrics | Existing |
| Overview → Pending Actions | Pending order/moderation counts, not one unified workspace | Partial |
| Overview → Activity Log | Admin audit logs | Existing, not universal |
| Website & Branding → General Identity / Logo & Assets / Contact & Social | `admin.brand` settings and upload | Existing |
| Website & Branding → Homepage Content | Draft/preview/publish and visibility toggles | Existing; no reorder editor |
| Website & Branding → Appearance | Brand colors plus shared CSS | Partial; no comprehensive theme editor |
| Website & Branding → Website Configuration | shop/features/branches settings | Existing but narrow |
| Seller Management → Directory | admin users/customer filter can filter role | Partial; customer listing query initially includes all roles |
| Seller Management → Applications & Approval / Profiles | no seller application/detail workflow | Missing |
| Seller Management → Listings | admin marketplace listing directory | Existing |
| Seller Management → Transactions | shared admin order/payment management | Existing for current request model |
| Seller Management → Reviews & Ratings / Account Actions / Seller Activity | no reviews, suspension or seller-specific activity workspace | Missing |
| Customer Management → Directory | searchable user list | Partial |
| Customer Management → Profiles / Orders & Transactions | customer list and shared order pages; no profile detail | Partial |
| Customer Management → Reviews & Ratings / Community Activity / Account Actions / Customer Activity | no review, general timeline or suspension workspace | Missing |
| Adoption Community → Overview | adoption/rescue counts/configuration | Partial |
| Adoption Community → Adoption/Rescue Posts | shared pet listings and moderation | Existing for listing types |
| Adoption Community → Reported Content / Comments & Engagement / Members | no report/comment system | Missing |
| Adoption Community → Moderation History | per-listing actions and admin audit records | Partial |
| System & Access → Admin & Staff / Roles & Permissions | user role list and role change only | Partial |
| System & Access → Security / Audit Log / System Status | OTP, audit log and system page | Existing in current scope |
| System & Access → Notifications | no notification center | Missing |

## Exact application files likely to change by phase

This is the current-file impact list for the proposal, not permission to change files during this audit. New files are labeled **new candidate**; they do not exist today. Migrations must only be added after a data-preserving design review.

### Phase 0 — Baseline and decisions

No application files. Audit artifacts: `docs/audit/PawHaven_Architecture_Audit.md`, `docs/audit/PawHaven_Functional_Audit.md`, `docs/audit/PawHaven_Performance_Audit.md`, `docs/audit/PawHaven_UI_UX_Audit.md`, `docs/audit/PawHaven_Security_Audit.md`, `docs/design/PawHaven_International_Design_System.md`, `docs/design/PawHaven_Redesign_Roadmap.md`.

### Phase 1 — Shared visual foundation

- `resources/css/app.css`
- `resources/views/partials/head.blade.php`
- `resources/views/partials/favicon.blade.php`
- `resources/views/components/app-logo.blade.php`
- `resources/views/components/app-logo-icon.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/app/header.blade.php`
- `resources/views/layouts/app/sidebar.blade.php`
- `resources/views/layouts/auth.blade.php`, `resources/views/layouts/auth/card.blade.php`, `resources/views/layouts/auth/simple.blade.php`, `resources/views/layouts/auth/split.blade.php`
- `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/admin/nav.blade.php`
- New component files are not selected yet; define exact components only after the token/component review.
- Tests: `tests/Feature/HomepageTest.php`, `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/EmailVerificationTest.php`, `tests/Feature/Auth/PasswordConfirmationTest.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/TwoFactorChallengeTest.php`, plus new visual/responsive browser checks.

### Phase 2 — Public navigation and browse

- `routes/web.php`
- `app/Http/Controllers/Marketplace/ListingBrowseController.php`
- `resources/views/welcome.blade.php`
- `resources/views/marketplace/index.blade.php`
- `resources/views/marketplace/show.blade.php`
- `resources/views/components/app-logo.blade.php`, `resources/views/components/app-logo-icon.blade.php`, `resources/views/layouts/app/header.blade.php`
- **New candidate:** dedicated product browse/detail controller and views (for example `app/Http/Controllers/Marketplace/ProductBrowseController.php`, `resources/views/products/index.blade.php`, `resources/views/products/show.blade.php`) after catalog requirements are approved.
- Tests: `tests/Feature/HomepageTest.php`, `tests/Feature/MarketplaceBrowseTest.php`, and new product-search/detail tests.

### Phase 3 — Cart, order integrity and payment (scope-gated)

- `app/Http/Controllers/OrderRequestController.php`
- `app/Http/Controllers/Marketplace/ListingBrowseController.php`
- `app/Http/Controllers/Admin/OrderController.php`
- `app/Http/Controllers/Seller/SellerRequestController.php`
- `app/Models/Order.php`, `app/Models/OrderItem.php`, `app/Models/Product.php`
- `resources/views/welcome.blade.php`, `resources/views/marketplace/show.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/seller/requests/index.blade.php`, `resources/views/seller/requests/show.blade.php`, `resources/views/admin/orders/index.blade.php`, `resources/views/admin/orders/show.blade.php`
- **New candidates only if approved:** `app/Models/Cart.php`, `app/Models/CartItem.php`, `app/Http/Controllers/Marketplace/CartController.php`, and `resources/views/cart/show.blade.php`. Routes would be added to `routes/web.php`; the additive migration filename/schema is intentionally undecided until anonymous/authenticated cart, inventory reservation and payment-provider behavior are approved.
- Tests: `tests/Feature/EndToEndIntegrationJourneysTest.php`, `tests/Feature/MarketplaceBrowseTest.php`, `tests/Feature/Admin/OrderManagementTest.php`, new stock/rollback/payment contract tests.

### Phase 4 — Adoption/rescue and learning (only approved capability)

- `app/Enums/PetListingType.php`, `app/Enums/PetListingStatus.php`
- `app/Models/PetListing.php`, `app/Models/ListingModerationAction.php`
- `app/Policies/PetListingPolicy.php`
- `app/Http/Controllers/Marketplace/ListingBrowseController.php`
- `app/Http/Controllers/Seller/SellerListingController.php`
- `app/Http/Controllers/Admin/MarketplaceModerationController.php`, `app/Http/Controllers/Admin/CommunityController.php`
- `routes/web.php`, `routes/seller.php`, `routes/admin.php`
- `resources/views/marketplace/index.blade.php`, `resources/views/marketplace/show.blade.php`, `resources/views/seller/listings/index.blade.php`, `resources/views/seller/listings/create.blade.php`, `resources/views/seller/listings/edit.blade.php`, `resources/views/admin/marketplace/categories/index.blade.php`, `resources/views/admin/marketplace/listings/index.blade.php`, `resources/views/admin/marketplace/listings/show.blade.php`, `resources/views/admin/marketplace/moderation/index.blade.php`, `resources/views/admin/community/index.blade.php`, `resources/views/welcome.blade.php`
- No new report/comment/member files are selected. Add them only after moderation/privacy rules and exact feature scope are specified.
- Tests: `tests/Feature/PetMarketplace/ListingImageUrlTest.php`, `tests/Feature/PetMarketplace/PetListingAuthorizationTest.php`, `tests/Feature/PetMarketplace/PetListingSchemaTest.php`, `tests/Feature/PetMarketplace/PetListingValidationTest.php`, `tests/Feature/Admin/MarketplaceModerationTest.php`, plus new community authorization/moderation tests if those capabilities are approved.

### Phase 5 — Customer and seller workspaces

- `routes/web.php`, `routes/seller.php`, `routes/settings.php`
- `app/Http/Controllers/Seller/SellerDashboardController.php`, `SellerListingController.php`, `SellerRequestController.php`
- `resources/views/dashboard.blade.php`, `resources/views/seller/dashboard.blade.php`, `resources/views/seller/listings/index.blade.php`, `resources/views/seller/listings/create.blade.php`, `resources/views/seller/listings/edit.blade.php`, `resources/views/seller/requests/index.blade.php`, `resources/views/seller/requests/show.blade.php`, `resources/views/pages/settings/layout.blade.php`, `resources/views/pages/settings/⚡profile.blade.php`, `resources/views/pages/settings/⚡appearance.blade.php`, `resources/views/pages/settings/⚡security.blade.php`, `resources/views/layouts/app/sidebar.blade.php`
- Tests: `tests/Feature/CustomerDashboardTest.php`, `tests/Feature/SellerDashboardTest.php`, `tests/Feature/PetMarketplace/PetListingAuthorizationTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/Settings/SecurityTest.php`.

### Phase 6 — Admin information architecture

- `routes/admin.php`
- `resources/views/layouts/admin.blade.php`, `resources/views/layouts/admin/nav.blade.php`
- Existing screens: `resources/views/admin/dashboard.blade.php`, `resources/views/admin/audit-logs/index.blade.php`, `resources/views/admin/brand/index.blade.php`, `resources/views/admin/homepage/index.blade.php`, `resources/views/admin/customers/index.blade.php`, `resources/views/admin/users/index.blade.php`, `resources/views/admin/orders/index.blade.php`, `resources/views/admin/orders/show.blade.php`, `resources/views/admin/products/index.blade.php`, `resources/views/admin/products/create.blade.php`, `resources/views/admin/products/edit.blade.php`, `resources/views/admin/inventory/index.blade.php`, `resources/views/admin/marketplace/categories/index.blade.php`, `resources/views/admin/marketplace/listings/index.blade.php`, `resources/views/admin/marketplace/listings/show.blade.php`, `resources/views/admin/marketplace/moderation/index.blade.php`, `resources/views/admin/community/index.blade.php`, `resources/views/admin/settings/features.blade.php`, `resources/views/admin/settings/shop.blade.php`, `resources/views/admin/system/index.blade.php`
- Related current controllers: `app/Http/Controllers/Admin/DashboardController.php`, `app/Http/Controllers/Admin/AuditLogController.php`, `app/Http/Controllers/Admin/BrandIdentityController.php`, `app/Http/Controllers/Admin/BusinessSettingController.php`, `app/Http/Controllers/Admin/CommunityController.php`, `app/Http/Controllers/Admin/CustomerController.php`, `app/Http/Controllers/Admin/HomepageCustomizerController.php`, `app/Http/Controllers/Admin/MarketplaceListingController.php`, `app/Http/Controllers/Admin/MarketplaceModerationController.php`, `app/Http/Controllers/Admin/OrderController.php`, `app/Http/Controllers/Admin/ProductController.php`, `app/Http/Controllers/Admin/SystemHealthController.php`, `app/Http/Controllers/Admin/UserController.php`.
- No pending-actions, seller-application/profile, customer-detail, notification or reported-content file is selected; add only after approval and specification.
- Tests: `tests/Feature/Admin/AdminAccessTest.php`, `tests/Feature/Admin/AdminAuthenticationTest.php`, `tests/Feature/Admin/BrandIdentityTest.php`, `tests/Feature/Admin/BusinessSettingsTest.php`, `tests/Feature/Admin/DashboardOrderStatusTest.php`, `tests/Feature/Admin/HomepageCustomizerTest.php`, `tests/Feature/Admin/MarketplaceModerationTest.php`, `tests/Feature/Admin/OrderManagementTest.php`, `tests/Feature/Admin/ProductManagementTest.php`, `tests/Feature/Admin/SellerDashboardAccessTest.php`, `tests/Feature/Admin/SystemHealthTest.php`; add role-matrix and reachability tests for every navigation node.

### Phase 7 — Security, database and performance hardening

- `app/Http/Controllers/OrderRequestController.php`, `app/Http/Controllers/Marketplace/ListingBrowseController.php`, `app/Http/Controllers/Admin/AdminAuthController.php`, `app/Http/Controllers/Admin/CustomerController.php`, `app/Http/Controllers/Admin/UserController.php`, `app/Http/Controllers/Admin/BrandIdentityController.php`
- `app/Services/EmailOtpService.php`, `app/Services/SettingsService.php`
- `app/Models/Order.php`, `app/Models/OrderItem.php`
- `config/app.php`, `config/session.php`, `config/database.php` only where deployment behavior is intentionally changed; never replace the local `.env` as an audit shortcut.
- `database/migrations/` additive index/state migrations only after query-plan and backup review.
- `tests/Feature/Performance/PerformanceCharacterizationTest.php`, `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/TwoFactorChallengeTest.php`, `tests/Feature/Admin/AdminAuthenticationTest.php`, `tests/Feature/Admin/OrderManagementTest.php`, `tests/Feature/PetMarketplace/ListingImageUrlTest.php`, `tests/Feature/PetMarketplace/PetListingAuthorizationTest.php`, `tests/Feature/PetMarketplace/PetListingSchemaTest.php`, `tests/Feature/PetMarketplace/PetListingValidationTest.php`, `tests/Feature/EndToEndIntegrationJourneysTest.php`.
- Production deployment environment and SAPI settings are external configuration, not `.env` edits in this audit.

## Regression requirements for every phase

1. Run the existing full test suite (audit baseline: 176 passing/765 assertions) and targeted feature tests above.
2. Preserve authentication, verified-email boundaries, admin OTP, rate limits, password hashing and session regeneration; no design migration may bypass them.
3. Re-test customer/seller/admin/staff access and cross-account isolation by direct URL and form submission.
4. Preserve order numbers, order/item snapshots, moderation/audit history and existing media paths. Test upgrades on a copy of the active schema/data and verify row counts/checksums before/after.
5. Re-run responsive browser checks at 320/360/375/390/768/1024/1440 CSS px, keyboard, 200% zoom and screenshot comparisons for public, auth, customer, seller and admin views.
6. Measure cold/warm browser timing, DB query count/duration and Web Vitals using the same environment; compare before/after, do not claim a proxy metric is end-to-end improvement.
7. Treat missing reviews, suspension, payment, cart, reporting, notifications and role permissions as separately scoped capabilities, not as visual-only controls.
