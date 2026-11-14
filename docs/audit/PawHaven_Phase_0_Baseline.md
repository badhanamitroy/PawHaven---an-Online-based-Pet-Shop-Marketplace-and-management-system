# PawHaven Phase 0 — Safety and Baseline Report

**Recorded:** 2026-11-08  
**Project:** `D:\PawHaven\PH Web Version\WebApp`  
**Phase:** Step 0 only — no application-code implementation. This report is the approval gate before Step 0.5.

## 1. Scope and safety outcome

The existing application and its uncommitted work were inspected in place. No application source, route, test, config, environment file, database row, migration state, or uploaded asset was modified by this Phase 0 work. The only new project-tree file is this report. The previously requested audit/design documents were already present as untracked project documentation before this phase.

No `migrate`, `db:seed`, `migrate:fresh`, `db:wipe`, delete, reset, Git stage, commit, push or deploy command was run. The test suite is configured for an in-memory SQLite database and passed without modifying the configured local database.

## 2. Git and uncommitted-work safety baseline

- Repository root: `D:\PawHaven\PH Web Version\WebApp`
- Branch: `master`
- Initial `git status --short --branch`: **33 modified tracked files, 0 staged files, 281 untracked files** (314 porcelain entries, expanded with `-uall`).
- Initial tracked diff: **33 files changed, 3,111 insertions, 479 deletions**.
- Existing modified and untracked work includes source, route, middleware, models, migrations, seeders, views, tests, image assets and documentation. Do not reset, clean, checkout, stage, or overwrite these files.
- The `docs/` directory was already untracked. This report is a documentation-only addition to that existing untracked work.
- Relevant ignored files include `.env`, `database/database.sqlite` (covered by `database/.gitignore`), `storage/app/public/*`, `public/build/*`, `public/storage`, `vendor/` and `node_modules/`. The `.env` values/secrets were not copied into this report.

### Recoverable backups

Backups were written outside the project tree under:

`C:\Users\Dell\.copilot\session-state\6ddfa72c-25bb-4595-b023-bc2da8efad81\files\pawhaven-phase-0-baseline-20261108\`

| Source | Backup | Verification |
|---|---|---|
| `database/database.sqlite` (323,584 bytes) | `database.sqlite` in the session backup folder | SQLite online backup API; backup opened read-only; `PRAGMA integrity_check` returned `ok`; all 23 table row counts matched source |
| `storage/app/public/` (4 files including `.gitignore`, 538,411 bytes) | `storage-app-public/` in the session backup folder | All four files copied and per-file SHA-256 hashes matched source |

`verification.json` in that folder records the table counts and upload-file hashes. The local `public/storage` junction already targets `storage/app/public`; it was not changed. The copied upload directory currently contains three branding PNG uploads and `.gitignore`; no local pet/product image files were present there in this snapshot.

The backups are local session-state files, not project changes or a substitute for an owner-managed production backup. They do not include `.env`, source code, or the Git repository.

## 3. Application, database and test baseline

### Runtime and application startup

- `php artisan --version`: **Laravel Framework 13.35.0**
- CLI PHP: **8.5.0**; Node **24.22.1**; npm **11.19.1**.
- Current local server at `http://127.0.0.1:8000/` responded during baseline checks.
- `php artisan route:list --except-vendor`: **81 registered routes**.
- Vite 8.3.4's installed package engine requires Node `^20.19.0 || >=22.12.0`; current Node 24.22.1 meets it.
- Test config in `phpunit.xml` sets `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`, and `BCRYPT_ROUNDS=4`.

### Active database (inspected read-only)

- `.env` selects SQLite at `database/database.sqlite`; `artisan db:show` identified SQLite 3.45.2 and 23 tables.
- Applied migration table contains 11 migration rows.
- Snapshot counts: 8 users (1 admin, 5 customers, 2 sellers, no staff), 4 pet categories, 3 breeds, 4 listings, 4 listing images, 1 listing-moderation action, 5 product categories, 4 products, 2 orders, 2 order items, 33 settings, 9 admin audit logs, 0 branches, 0 saved pets and 0 passkeys. The remaining tables are Laravel support tables.
- All four stored listings were published (three sale, one adoption); no rescue/rehoming listing was present. All four products were published. The two orders were pending or processing.
- `PRAGMA foreign_key_check` returned no violations.
- Schema sources are the 11 files in `database/migrations/`; the schema and relationships are inventoried in [PawHaven_Architecture_Audit.md](PawHaven_Architecture_Audit.md).

### Automated baseline

Command:

```powershell
Set-Location 'D:\PawHaven\PH Web Version\WebApp'
php artisan test --no-coverage
```

**Result:** 176 tests passed, 765 assertions, approximately 24.5 seconds. The performance characterization test also ran in this suite. This is a test baseline against its in-memory SQLite DB, not production data. Hashing in tests is deliberately configured to four rounds; local `.env` is configured separately. No security setting was weakened.

### Public startup and guest journeys

Local HTTP samples (one request each; the local server was already running; not a cold process start):

| Path | Result | TTFB / total |
|---|---:|---:|
| `/` | 200 | 0.069 / 0.071 s |
| `/listings` | 200 | 0.046 / 0.048 s |
| `/listings/1` | 200 | 0.055 / 0.057 s |
| `/login` | 200 | 0.568 / 0.569 s |
| `/register` | 200 | 0.065 / 0.066 s |
| `/contact` | 404 | 0.045 / 0.046 s |
| `/dashboard` | 302 (guest auth redirect) | 0.032 / 0.033 s |
| `/admin` | 302 (guest admin-login redirect) | 0.028 / 0.030 s |
| `/seller/dashboard` | 302 (guest seller-login redirect) | 0.028 / 0.029 s |

The measurements are local one-off samples and must not be interpreted as cold-start, production, or Core Web Vitals results. The login outlier also shows that more repeatable warm/cold testing is needed. No authenticated production-user login was attempted.

## 4. Routes, roles and permissions baseline

The application has web routes in `routes/web.php`, `routes/admin.php`, `routes/seller.php`, and `routes/settings.php`. `bootstrap/app.php` aliases `admin`, `staff_or_admin`, `seller`, and `customer`. Admin routes require authentication and verified email; staff/admin share an operations route group, with nested administrator-only functions. Seller workspace routes require authentication, verified email and seller role. Customer dashboard routes require authentication and verified email. Account security additionally requires password confirmation.

Models include user, order/order item, product/category, pet listing/category/breed/image, moderation action, setting, branch and audit log. Policies exist for orders, products and pet listings; services include cached settings and hashed email OTP. OTP is required on the dedicated admin authentication path.

Guest redirects were verified above; role-specific direct requests and authenticated cross-account requests are covered only by source/tests in this baseline. The active local DB has no staff user. See the [functional audit](PawHaven_Functional_Audit.md) for role feature classification and the original source paths.

## 5. High-priority findings for approval-gated Step 0.5

These are findings only; **no fixes were applied**.

1. **Seeded administrator credentials — high risk if the documented seeder is used for installation.** `database/seeders/ShopSeeder.php` uses `firstOrCreate` for an admin, seller and customer with hard-coded email identities and predictable default passwords. `docs/INSTALLATION.md` tells installers they may run `ShopSeeder`. Review whether demo seeding should ever create authenticated accounts, and how to ensure production setup uses only the explicit admin bootstrap command. Do not run that seeder against the active database.
2. **Seller/admin boundary is contradictory.** `AdminAuthController` accepts seller role through the admin OTP challenge and redirects authenticated sellers to `admin.dashboard`; the `staff_or_admin` middleware denies seller access there. Public registration also permits choosing seller, which conflicts with the confirmed initial single-store distribution requirement not to activate independent seller registration by default. This needs an explicit compatibility plan for existing seller accounts before changing registration or redirects.
3. **Product purchase checks are incomplete.** `OrderRequestController` validates product existence and quantity but does not check published status or stock, and does not decrement/reserve inventory. A verified customer can submit an order request for an unpublished or unavailable product if its ID is supplied. The product price is read server-side from the database, but race-safe availability is not enforced.
4. **Order persistence is not atomic.** Product and pet request paths create an order and then an order item separately. A failure during item creation can leave a partial order. No stock locking/conditional decrement was found. Requires focused rollback and concurrent-stock tests before modifying behavior.
5. **Customer directory classification is too broad.** `Admin\CustomerController` begins with all `User` records and only narrows when a role filter is supplied; its total count is also all users. The default “customers” view can include admins, staff and sellers.
6. **No account suspension lifecycle exists in inspected schema/models.** There is no suspension flag/reason/actor or corresponding activity enforcement. Do not represent account suspension as existing functionality. If introduced later, retain orders, listings, audit records and related history.
7. **Production security is environment-dependent.** Current local `.env` has `APP_ENV=local` and `APP_DEBUG=true`; `config/app.php` disables debug by default in production environment. Production environment, HTTPS/Secure cookie, mail transport, secret management and web-SAPI settings were not available to verify. Do not edit the existing `.env` during this baseline.
8. **Delivery-threshold key mismatch warrants focused verification.** `SettingsService` defaults use `shop.free_shipping_threshold_minor`; `OrderRequestController` reads `shop.free_delivery_threshold_minor`. Verify the admin shop-settings write key and expected shipping policy before correcting it.

Additional confirmed distribution/configuration risks:

- `SettingsService` defaults include USD, UTC, US-style sample address/telephone/contact values and PawHaven brand/support values. Admin settings can change some current values, but primary country, locale-specific address rules, tax, shipping country/region restrictions, payment-provider configuration and currency-conversion policy are not implemented as a complete installation-level configuration.
- `.env.example` contains a Gmail SMTP host with placeholder credentials; it is an example, not an actual credential. Production email still depends on the buyer configuring their own delivery service.
- `config/mail.php` and settings defaults contain PawHaven-branded fallback sender/contact addresses. Brand customization does not by itself replace every email/template literal.
- Seeders refer to external Unsplash image URLs and Seattle/US demo content. The public homepage currently requests remote Unsplash pet photos. Network independence, image rights, and production seeding behavior need separate owner-approved decisions; no network callback or telemetry dependency was installed in this phase.
- The browser rendered “PawHeaven” in the page title/brand area while application defaults are PawHaven. There are many mixed literal branding references across Blade views. Do not globally replace strings in administrator-customized content.
- Installation documentation states PHP 8.2 minimum although `composer.json` requires `^8.3`; it states Node 18 although installed Vite 8.3.4 requires at least Node 20.19 or 22.12. Configuration documentation points to `/admin/settings/business`, while registered settings routes use `/admin/settings/shop`. Reconcile before commercial distribution.
- The package metadata declares MIT; confirm distribution/licensing terms, third-party notices and rights to included logo/demo imagery before sale. This is not a legal determination.

## 6. Existing configuration and distributability snapshot

**Existing configurable admin capabilities:** brand name/tagline/colors/contact/logo/favicon/meta description; homepage hero text/CTAs/announcement/section visibility and draft preview/publish; shop currency/symbol, delivery and free-delivery settings, COD flag and store details; feature flags and branches; community rescue contact/guideline settings. These are implemented to varying scope; fields do not imply that every target workflow is complete.

**Not established by current app configuration:** per-installation country, general locale/date format, dynamic address schema, payment provider boundary/credentials/webhooks, manual payment instructions, shipping country availability, all footer content, homepage ordering/featured-content management, typography selector, registration/role policy controls, maintenance-mode admin workflow or notification preferences.

**Payment baseline:** current order records have payment method/status and reference fields. The product request uses COD; seller/admin can update payment status manually. No provider-independent initiation/verification/webhook/refund adapter or active online gateway was identified. COD/manual/online modes must not be advertised as equally operational today.

**Future marketplace boundary:** the product catalog is store-owned (products have no vendor/owner field); pet listings have `seller_id`. Orders contain optional `seller_id` and `pet_listing_id`, with item snapshots. Future vendor product ownership, attribution and settlement must be designed separately; no seller payout or commission implementation exists.

**Installation material exists but is inconsistent:** README, `docs/INSTALLATION.md`, `docs/CONFIGURATION.md`, and `docs/BACKUP_AND_RESTORE.md` cover basic setup, env, storage, mail, admin bootstrap, and backup. They require a technical reconciliation pass for runtime requirements, seeder safety, config route names, and actual implemented commerce/payment capabilities.

## 7. Files changed, migrations and test additions

- **Application files modified in this Phase 0 turn:** none.
- **Database migrations added by this phase:** none.
- **Tests added by this phase:** none.
- **Documentation added by this phase:** `docs/audit/PawHaven_Phase_0_Baseline.md`.
- The existing dirty files listed in the initial Git baseline remain untouched.
- DB backup and uploaded-file backup are outside the project tree in session state as described above.

## 8. Performance baseline and limitations

The earlier audit's browser capture observed a first fresh-tab request around 1.89 seconds load and warmed reloads around 0.36–0.40 seconds; that was against an already-running local server, not a cold process startup. This Phase 0 curl sample had a 0.071-second homepage response but `/login` took 0.569 seconds. The in-memory performance test observed homepage at 12 queries/~1.58 ms DB time in this run. None of these reproduces or resolves the earlier 14–15-second report. Web SAPI OPcache, clean-process cold start, real production cache, remote client network and reliable LCP/CLS/INP remain unverified. Keep hashing and OTP controls unchanged during any investigation.

## 9. Commands to reproduce the Phase 0 checks

From PowerShell:

```powershell
Set-Location 'D:\PawHaven\PH Web Version\WebApp'
git status --short --branch
php artisan --version
php artisan route:list --except-vendor
php artisan test --no-coverage
curl.exe -sS -o NUL -w '%{http_code} %{time_starttransfer} %{time_total}' http://127.0.0.1:8000/
```

The last command assumes an application server is already running. A local development server can be started with `php artisan serve --host=127.0.0.1 --port=8000`; do not launch another if port 8000 is already occupied.

## 10. Approval gate and deferred scope

**Step 0 baseline is complete. Stop here.** No Step 0.5 or Step 1 implementation has been started. Review the backup location and the findings above. Step 0.5 should begin only after explicit approval and must be limited to the brief's correctness/security scope, with tests and another stop/report gate afterward.

Full homepage redesign, cart/checkout UI, online payment integration, independent seller registration/payout activation, full adoption workflow, full admin/customer/seller redesign, multilingual implementation, DRM/remote license activation and unapproved external integrations remain deferred.
