# PawHaven Architecture Audit

**Audit date:** 2026-11-07  
**Scope:** Existing Laravel application at `PH Web Version/WebApp`; read-only application/database inspection.  
**Evidence labels:** **Verified** means observed in current source, runtime, tests, browser, or read-only database query. **Unverified** means the required production access, user credentials, or environment was unavailable. Recommendations are not assertions about current behavior.

## Executive summary

PawHaven is a full-stack, server-rendered Laravel monolith, not a starter-only site. It combines a single-store pet-products catalog, a pet sale/adoption/rescue listing marketplace, account dashboards, and an admin operation area. Livewire is used for account settings; most storefront and management pages are Blade views backed by controllers or route closures. There is no repository/service layer or separately deployed API.

The current application has substantial existing behavior and data. Its active configuration points to a local SQLite database containing 23 tables, 11 applied migrations, 8 users, 4 pet listings, 4 products, 2 orders, and other settings/audit records. No rows were changed during this audit. The complete automated test suite passed: 176 tests / 765 assertions.

Important architecture findings:

- **Verified:** Admin, seller, and customer flows exist. In particular, the seller dashboard and listing/request workspaces are present; the 2026-10-30 `docs/PROJECT_AUDIT.md` assertion that the seller dashboard is missing is stale.
- **Verified:** Store products and pet listings are separate catalog domains, while both purchase paths converge on `orders`/`order_items`. There is no configurable global “single-store vs marketplace” mode; the code is a hybrid application with feature flags for branches/staff.
- **Verified:** Admin/staff authorization is role-based middleware, not a permissions matrix. Seller ownership is enforced through seller middleware and policies on listing/order operations.
- **Verified:** Checkout is request-based and Cash on Delivery oriented. There is no cart model, product-detail route, payment gateway, product reviews, general community-post/report model, or localized content subsystem in the inspected route/schema inventory.
- **Risk:** Several writes span multiple records without a database transaction (for example, order followed by order item), and the current order schema lacks indexes on common user/seller lookup columns.
- **Unverified:** Production deployment configuration, production database engine/size, real web-SAPI OPcache state, queue worker and SMTP health, and production-scale query behavior.

## Project structure and layers

**Project type:** Full-stack PHP application; Laravel MVC/server-rendered monolith with Livewire islands.

| Area | Current responsibility | Inventory |
|---|---|---:|
| `app/Actions` | Fortify registration/auth actions | 3 PHP files |
| `app/Concerns` | Shared validation rules | 3 |
| `app/Console` | Console command(s) | 1 |
| `app/Enums` | Listing, order, payment and moderation states | 5 |
| `app/Facades` | Settings facade | 1 |
| `app/Http` | Admin, marketplace and seller controllers; middleware, requests and responses | 33 |
| `app/Livewire` | Account/settings Livewire actions | 1 |
| `app/Mail` | OTP, security, order and moderation mail | 7 |
| `app/Models` | Eloquent entities | 13 |
| `app/Policies` | Order, product and pet-listing policy | 3 |
| `app/Providers` | Application and Fortify wiring | 2 |
| `app/Services` | Settings and email OTP services | 2 |
| `resources/views` | Public, auth, customer, seller, admin, email and shared Blade views | 83 Blade files |

Primary entry points are [bootstrap/app.php](../../bootstrap/app.php), [routes/web.php](../../routes/web.php), [routes/admin.php](../../routes/admin.php), [routes/seller.php](../../routes/seller.php), and [routes/settings.php](../../routes/settings.php). The home page and customer dashboard are implemented as route closures in `routes/web.php`; admin and seller domains use controllers. Settings profile/appearance/security are Livewire routes. `bootstrap/app.php` registers `/up`, web exception presentation, guest redirects, and the `admin`, `staff_or_admin`, `seller`, and `customer` aliases.

There are 81 registered application routes in the current route table. No API route file or API route group was found. Admin and seller routes live in separate files included by `routes/web.php`; all run through Laravel's web stack unless a route explicitly changes middleware.

### Route and access boundaries

| Surface | Access boundary |
|---|---|
| Home and public pet listing browse/detail | Public |
| Product order request; pet purchase/inquiry/favorite; customer order cancellation | `auth` + `verified` |
| Customer dashboard | `auth` + `verified`; redirects admins/staff and sellers to role workspaces |
| Admin operations | `auth` + `verified` + `staff_or_admin`; a nested `admin` group protects administrator-only configuration, user roles, audit logs, moderation and system settings |
| Admin login and OTP | Guest-only credential/OTP routes; logout requires authentication |
| Seller workspace | `auth` + `verified` + `seller` |
| Profile settings | `auth`; appearance/security additionally require verified email; security also requires password confirmation |

Admin and staff are distinguished by a role string and middleware. Staff can access the general admin operations group; branding, homepage management, role management, moderation and system settings are administrator-only. There is no fine-grained permission table or policy-backed capability editor.

### Main code domains

- **Identity:** Laravel Fortify, `User`, registration/login actions, passkeys, email verification, password reset and account settings.
- **Admin:** dashboard, product/category/inventory/order management, customer/user directory, brand/homepage settings, pet listing moderation, community settings and audit log.
- **Seller:** dashboard, seller-owned pet listings/images and seller-owned purchase/adoption requests.
- **Marketplace:** `PetListing`, pet categories/breeds, favorites, listing inquiry/purchase, and moderation.
- **Single-store shop:** `Product`, product categories, inventory and direct order requests.
- **Shared services:** cached settings and hashed email OTP.

There is no repository abstraction. `SettingsService` centralizes settings storage/caching; controllers otherwise query Eloquent directly. The root route owns homepage query composition, and the homepage draft-preview action repeats much of that query composition.

## Authentication, authorization and business modes

**Verified:** User passwords use Laravel's `hashed` cast. Public registration allows customer/seller selection and forcibly downgrades a submitted `admin` value to `customer`. Admin login verifies credentials, throttles attempts, requires email OTP before creating an authenticated session, and regenerates the session after verification. OTP values are hashed in cache, expire after five minutes, permit five attempts and have a 60-second resend cooldown. Admin/staff are the intended operations roles; sellers also pass the admin-login role check, which creates a contradictory path because the admin workspace later denies seller role access (see the security/functional reports).

**Verified:** `PetListingPolicy` scopes seller create/update/delete to seller ownership; `OrderPolicy` scopes seller order access by `seller_id` and customer access by `user_id`. Seller controllers also query the current seller's listings/requests. Admin/staff bypass those owner filters only in authorized operations.

**Business modes:** The product shop is single-store (products have no seller/merchant owner). Pet listing sales and adoption/rescue posts are seller-owned marketplace content. Shop settings and branch/staff feature flags exist, but the inspected DB settings have no active mode switch. This is two coexisting domains, not a switchable “single-store” or multi-vendor product-marketplace runtime.

## Data model and active database

**Verified active database:** `sqlite`, `<project-root>\database\database.sqlite`, SQLite 3.45.2; 23 tables; 11 migration rows. The DB was queried through a read-only SQLite connection. `PRAGMA foreign_key_check` returned no violations. The user-specified preservation constraints were observed: no migration, reset, seeding or record/file deletion was run.

Current record counts are a snapshot, not an assertion that the database is production data:

| Table/group | Rows |
|---|---:|
| `users` | 8 (1 admin, 5 customer, 2 seller; no staff account) |
| `pet_categories` / `breeds` | 4 / 3 |
| `pet_listings` / `listing_images` / `listing_moderation_actions` | 4 / 4 / 1 |
| `products` / `product_categories` | 4 / 5 |
| `orders` / `order_items` | 2 / 2 |
| `settings` / `admin_audit_logs` / `branches` | 33 / 9 / 0 |
| `saved_pets` / `passkeys` | 0 / 0 |
| Framework tables: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` | 46 / 13 / 0 / 0 / 0 / 0 / 0 |

All four current listings are published (three sale, one adoption); no rescue/rehoming listing is present in this snapshot. All four products are published. The two orders are pending or processing.

| Entity/table | Important schema and relationships |
|---|---|
| `users` | Name/email/password, verification and remember token, Fortify two-factor fields, role; unique email and role index |
| `passkeys` | Credential and user FK; unique credential ID and user index |
| `pet_categories`, `breeds` | Category name/slug; breed belongs to category and has description/care information; category/slug uniqueness/indexes |
| `pet_listings` | Seller, category, breed, title/description, listing type, minor-unit price, location/status; belongs to seller/category/breed; indexed by status/date, category/status and seller/status |
| `listing_images` | Listing FK, storage path and position; unique listing/position |
| `listing_moderation_actions` | Listing/admin FKs, action and note; listing/admin date indexes |
| `saved_pets` | User/listing many-to-many with unique pair |
| `product_categories`, `products` | Product category, SKU/slug, minor-unit prices, stock, published/featured flags, image, ingredients/storage text; category FK; SKU/slug unique and published/featured indexes |
| `orders` | Unique order number, customer snapshot/contact/address, optional user/seller/pet listing, status/payment fields, minor-unit subtotal/fee/total, cancellation and payment references |
| `order_items` | Order/product FKs plus product/SKU/price snapshot, quantity and subtotal |
| `settings` | Unique key, value and group |
| `branches` | Business branch identity/contact/address/active state; unique code |
| `admin_audit_logs` | Optional user FK, action, description, IP, payload and timestamps |
| Laravel support tables | Sessions, cache/cache locks, jobs/batches/failed jobs and password reset tokens |

The authoritative, complete column/index definitions are the applied files under [database/migrations](../../database/migrations). `orders.user_id`, `orders.seller_id`, and `order_items.order_id` do not have explicit secondary indexes in the inspected SQLite schema; this is a scale risk to measure before adding non-destructive index migrations. The schema contains no review, cart, payment-provider, reported-content, general community-post/comment, notification or seller-application entity.

## Storage and assets

Uploads use Laravel's public disk and local `storage/app/public` paths. Existing branding uploads are under `storage/app/public/branding`; seller listing images are stored under `listings`; product images under `products`. The current public image set contains duplicate/near-duplicate logo assets both at `public/` and `public/images/`; stored branding copies also exist. Nothing was changed or removed.

Brand/product upload controllers validate image type and size; branding adds explicit allowed MIME extensions and dimension limits. Listing uploads allow up to five images at 2 MB each and use Laravel's `image` validation. `public/build` contains the Vite manifest and compiled CSS, JS and font assets.

## Frontend, packages and runtime

| Component | Manifest constraint | Installed/runtime evidence |
|---|---|---|
| PHP | `^8.3` | CLI PHP 8.5.0 |
| Laravel | `^13.17` | 13.35.0 |
| Livewire | `^4.1` | 4.4.7 |
| Flux UI | `^2.13.1` | 2.20.1 |
| Fortify | `^1.37.2` | 1.41.0 |
| Livewire Blaze | `^1.0` | 1.0.19 |
| Tailwind CSS / Vite plugin | `^4.0.7` / `^4.1.11` | 4.3.3 / 4.3.3 |
| Vite / Laravel Vite plugin / Vite Plus | `^8.0.0` / `^3.1` / `0.3.0` | 8.3.4 / 3.2.0 / 0.3.0 |
| Passkeys JS / Puppeteer Core | `^0.2.0` / `^25.16.0` | 0.2.0 / 25.16.0 (Puppeteer is dev-only) |
| PHPUnit / Larastan / Pint | `^12.5.23` / `^3.9` / `^1.27` | 12.5.38 / 3.13.0 / 1.32.1 |
| Node / npm | — | 24.22.1 / 11.19.1 |

The frontend is Blade + Tailwind CSS v4 + Flux/Livewire, built by Vite. Instrument Sans is locally bundled. The palette in `resources/css/app.css` already includes the requested forest/deep green, ivory, coral, dark and muted colors. The homepage is a large Blade file (1,425 lines); several dashboard/admin views and navigation use additional inline utility color/size values.

Active `.env` values relevant to this local audit were read without printing secrets: `APP_ENV=local`, `APP_DEBUG=true`, SQLite, database-backed sessions/cache/queue, SMTP mailer, local filesystem, and `BCRYPT_ROUNDS=12`. Do not treat this local environment as production configuration. The CLI has OPcache enabled (including CLI) and a 4 MB realpath cache; web-SAPI OPcache settings are **unverified**. The PHP `intl` extension is absent; the app works in the browser, but `artisan db:show --counts` fails when Laravel tries to format row counts.

## Architectural risks and opportunities

1. **Boundary duplication:** homepage and draft preview repeat data-selection logic; route closures own homepage/dashboard behavior while adjacent domains use controllers.
2. **Hybrid commerce is easy to misread:** single-store products and seller-owned pet listings share orders but not a common cart/merchant product model.
3. **Order data integrity:** product and pet order-request paths create the order and item separately without a transaction; a second-write failure can leave an incomplete order. Seller/admin status state transitions are enforced, but inventory reservation/decrement and external payment settlement are not implemented.
4. **Scale:** customer dashboard retrieves all saved pets and owned listings without pagination; several common order owner lookups have no explicit indexes.
5. **UI naming:** the browser title/logo says “PawHeaven” while the code/default brand is “PawHaven”; public settings values can override defaults, so confirm the intended spelling before harmonizing assets.
6. **Documentation drift:** the existing October 30 audit describes missing seller routes that now exist and are covered by passing tests. Re-validate old reports against current source before using them as requirements.
7. **No production scale evidence:** the real database is small. Query and file-size improvements should be measured on representative data and a production-like SAPI before implementation.

## Audit boundaries

Verified evidence is specific to the source/worktree and local runtime on the date above. Browser access used the running local app and no authenticated admin/seller credentials were used. Production role behavior, SMTP delivery, accessibility with assistive technology, concurrency races and production database behavior remain unverified. See the [functional audit](PawHaven_Functional_Audit.md), [performance audit](PawHaven_Performance_Audit.md), [security audit](PawHaven_Security_Audit.md), and [redesign roadmap](../design/PawHaven_Redesign_Roadmap.md).
