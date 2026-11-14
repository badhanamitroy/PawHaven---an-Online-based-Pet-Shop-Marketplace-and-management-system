# PawHaven Functional Audit

**Audit date:** 2026-11-07  
**Classification:** Working, Partially working, Broken, Missing, Unverified. “Working” requires implementation evidence plus an exercised test or runtime behavior; a route/button alone is not proof.

## Verification basis

- Current routes/controllers/policies/views were traced; public browser checks were run against `http://127.0.0.1:8000/`.
- The full existing suite passed: **176 tests, 765 assertions**. Test configuration uses `sqlite :memory:`; it did not migrate or alter the active `database/database.sqlite`.
- Public GET evidence: `/` 200, `/listings` 200, `/listings/1` 200, `/login` 200, `/contact` 404, guest `/dashboard` 302 to auth, guest `/admin` 302 to admin login, guest `/seller/dashboard` 302 to seller login.
- Admin and seller authenticated browser journeys were not replayed with real credentials. Their server behavior is backed by the named feature tests and source tracing, not a live authenticated session.

## Authentication and role entry points

| Feature | Status | Evidence and observed gap |
|---|---|---|
| Customer/seller registration and standard login | **Working** | Fortify registration/login/password reset/email verification routes and tests pass. Public registration only assigns customer or seller even if a request attempts admin. |
| Admin/staff login and OTP | **Working** | Dedicated admin login, throttling and email OTP challenge are covered by `AdminAuthenticationTest`; a session is granted only after OTP verification. |
| Seller login to seller workspace | **Working** | Seller role is redirected to `seller.dashboard`; seller dashboard access tests pass. |
| Seller using admin login | **Broken** | Admin login accepts seller and completes OTP, but redirects to `admin.dashboard`, whose `staff_or_admin` middleware denies seller with 403. The current auth test stops after asserting the redirect/authentication and misses the resulting denied workspace. |
| Staff role/workspace in active data | **Unverified** | Staff routes and tests exist, but the inspected database contains no staff account. |

## Public website

| Feature | Status | Evidence and observed gap |
|---|---|---|
| Homepage | **Working** | Route assembles published sale listings, adoption/rescue highlights, categories, breeds and published products; live response 200. Homepage sections include category discovery, pets, shop, adoption/rescue, breeds, how-it-works and trust/community. |
| Header/navigation | **Partially working** | Main menu links work as home-page anchors and auth links. Marketplace/shop/adoption/breed destinations are not all dedicated pages. At the 617×408 browser viewport the mobile menu had no horizontal overflow; at the desktop-nav breakpoint (~772 CSS px in the browser harness) the header overflowed by about 95 px, clipping the Sign Up control. |
| Footer | **Partially working** | Branding and informational/community anchors exist in the homepage. No dedicated `/contact` route; `/contact` returned 404. Contact/social values are stored in branding settings rather than a standalone contact experience. |
| Search | **Working (pet listings only)** | `/listings` accepts keyword search over listing title, description and location. It is not site-wide and does not search products. |
| Product categories | **Partially working** | Homepage category discovery and admin category CRUD exist; no public category-specific product browse route was found. |
| Product listing | **Partially working** | Homepage shows a featured subset of four products; there is no complete public shop listing route. |
| Product details | **Missing** | No public product detail route/view. |
| Contact / informational / educational pages | **Missing / Partially working** | Homepage has informational sections and admin-stored adoption guidelines; dedicated contact, policy, educational/article or breed-detail routes were not found. `/contact` is 404. |

## Marketplace and shop

| Feature | Status | Evidence and observed gap |
|---|---|---|
| Pet discovery | **Working** | `/listings` is paginated, published-only and eager-loads category, breed, images and seller; response 200. |
| Listing filters and sorting | **Working** | Category, breed, sale/adoption type, keyword and latest/price ordering are implemented in `ListingBrowseController`; exact test coverage is in `MarketplaceBrowseTest`. |
| Pet listing detail | **Working** | `/listings/{listing}` loads seller/category/breed/images and related listings; `/listings/1` returned 200. Non-published listings are hidden from ordinary users. |
| Seller information | **Working** | Listing detail loads and renders its seller relation. |
| Product cart | **Missing** | Header cart is an anchor to `#pet-shop` and explicitly labels the cart “enabled in upcoming e-commerce milestone”; no cart routes/model exist. |
| Checkout | **Partially working** | Authenticated/verified product or pet purchase submits a purchase request and creates an order. This is not a cart/checkout/payment-provider flow. |
| Product order request | **Partially working** | `OrderRequestController` validates product ID, quantity and shipping fields, calculates configured fee and stores order/item; it does not enforce published status or available stock and does not decrement inventory. |
| Pet sale/adoption request | **Partially working** | Sale requests and adoption/rescue inquiries create order records. Adoption inquiries are represented as orders rather than a separate application/review workflow. |
| Order history | **Working** | Customer dashboard loads the signed-in user's orders and related items/listing/seller; seller requests filter by seller ID; admin orders are paginated. Feature journey tests pass. |
| Transactions/payment | **Partially working** | Status/payment fields and manual Cash on Delivery verification exist. No external payment gateway or transaction ledger was found. |

## Adoption and rescue community

| Feature | Status | Evidence and observed gap |
|---|---|---|
| Adoption listings/pet profiles | **Partially working** | Adoption posts use `PetListing` with type/status and the same listing browse/detail surfaces. There is no dedicated adoption profile or application workspace separate from marketplace listing pages. |
| Rescue posts | **Partially working** | `rescue` and `rehoming` are listing types; the current SQLite snapshot has no rescue/rehoming records. Runtime behavior for populated rescue content is therefore unverified. |
| Adoption workflow | **Partially working** | Customer inquiry creates an order/request record; seller can view and update it. No distinct applicant screening, decision history, visit scheduling or handover workflow was found. |
| Community interactions | **Partially working** | Authenticated customers can favorite/save pet listings. General posts, comments, follows, reactions or member activity are missing. |
| Reporting/user-flagged content | **Missing** | No report entity, public report route, or reported-content queue was found. |
| Listing moderation | **Working (listing scope)** | Admin can review, approve or reject pending pet listings; moderation actions are transactionally recorded and admin audit events are written. No broader community moderation exists. |
| Moderation history | **Partially working** | Per-listing moderation actions are loaded for seller/admin workflows; no general moderation history or report-resolution workspace was found. |

## Customer dashboard

| Feature | Status | Evidence and observed gap |
|---|---|---|
| Profile | **Working** | Livewire profile component and profile tests pass. |
| Orders | **Working** | Customer-owned orders are loaded and cancellation is policy/state-transition checked. |
| Transactions | **Partially working** | Orders expose payment status; no separate transaction ledger or payment provider exists. |
| Reviews | **Missing** | No review model, route or view found. |
| Community activity | **Partially working** | Saved pets and owned pet listings are displayed; no general community timeline. Both collections are unpaginated. |
| Account settings/security | **Working** | Profile, appearance, password-confirmed security, passkey and Fortify-related views/routes exist; relevant settings/auth tests pass. |

## Seller dashboard

| Feature | Status | Evidence and observed gap |
|---|---|---|
| Seller dashboard/profile | **Partially working** | `/seller/dashboard` and seller dashboard view exist and show owned listings/request metrics. No distinct public seller profile/merchant profile editor or application approval flow was found. |
| Product management | **Missing (seller-owned shop products)** | Seller listings are pet listings only. Products belong to the single PawHaven shop, not marketplace sellers. |
| Pet listing management | **Working** | Seller can create, edit, save/submit, pause/complete and delete owned listings; policy checks ownership; image upload is validated. |
| Purchase/adoption requests | **Working (request workflow)** | Seller queries are scoped to `seller_id`, list/status/payment filters exist, and order policies protect detail/update access. |
| Transactions | **Partially working** | Payment status/reference can be updated manually; no external settlement/ledger. |
| Reviews/ratings | **Missing** | No review/rating entity or seller review route found. |
| Account settings | **Partially working** | Shared profile/security settings are available; no seller-specific account, payout or verification settings found. |

## Admin dashboard

| Feature | Status | Evidence and observed gap |
|---|---|---|
| Overview/pending actions | **Working / Partially working** | Dashboard metrics and pending moderation/order counts exist. A dedicated unified “Pending Actions” workspace is not established. |
| Website identity/branding/assets | **Working** | Admin brand settings, validated logo/favicon uploads and audit logging exist. |
| Homepage content | **Working** | Draft, preview, publish and discard endpoints plus section visibility controls exist. Section ordering is not available. |
| Seller directory/application approval/profile | **Partially working / Missing** | General user list supports role filters, but no seller application/approval or seller profile detail workflow. |
| Seller listings/transactions | **Working (existing scope)** | Marketplace listing directory and admin order/payment management exist. No seller activity workspace. |
| Customer directory | **Partially working** | Search, role filter, order/listing counts and spend aggregate exist. `CustomerController` starts from all users, so the “customers” page can include non-customers until filtered. No customer detail page. |
| Product and order management | **Working** | Product/category/inventory CRUD and order status/payment management exist; related integration tests pass. |
| Adoption/rescue moderation | **Partially working** | Shared pet-listing moderation covers listing content. No reporting queue, comments/engagement moderation or member enforcement tools. |
| Staff and permissions | **Partially working** | Admin role management can assign admin/staff/seller/customer and protects against self-demotion/last-admin demotion. No granular permission matrix, staff invitations or staff-specific audit workspace. |
| Security/activity logs | **Partially working** | Admin audit logs exist and many management mutations record events. This is not a comprehensive immutable audit of every seller/customer action or read. |
| System configuration/status | **Partially working** | Shop/features/branches and a system page exist. No notification center; branch feature is disabled and there are no branch records in the current DB. |

## Cross-cutting behaviors

- **Single-store vs marketplace:** **Partially working / hybrid by design.** Shop products are single-owner inventory; pet listings have sellers. No runtime mode switch or multi-vendor product catalog is present.
- **Historical records:** Product deletion is blocked when order items refer to it; account administration changes roles instead of deleting users. No account suspension/ban lifecycle is present.
- **Current test result:** 176/176 tests passed. This validates current automated behavior, not every listed unverified workflow or production integration.

See the [architecture audit](PawHaven_Architecture_Audit.md) for table counts, middleware boundaries and source map; see the [security audit](PawHaven_Security_Audit.md) for concrete risks and the [redesign roadmap](../design/PawHaven_Redesign_Roadmap.md) for sequenced changes.
