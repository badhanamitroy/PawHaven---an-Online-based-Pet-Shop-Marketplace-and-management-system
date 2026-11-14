# PawHaven Phase 0.5 Implementation Report

**Date:** 2026-11-08  
**Scope:** Critical correctness and security fixes only. Step 1 and the full redesign remain gated.

## Outcome

Product purchase requests now validate publication and stock against the database, reserve inventory atomically, and use the persisted product price. Order and order-item creation is transactional for product orders, pet-sale requests, and adoption inquiries. New reserved stock is restored on cancellation; existing orders are not treated as reservations.

Administrative access is limited to Admin and Staff roles, including a role re-check after the OTP challenge. Customer workflows require the Customer role, public registration cannot assign privileged or Seller roles, and the customer directory/report counts exclude Staff, Admin, and Seller accounts. Unknown roles no longer fall through to the customer dashboard.

Seeders no longer create predictable user accounts or sample orders. Demo catalog/listing seeders are restricted to local and test environments. The existing production debug safeguard was reviewed and remains covered by the error-page test.

## Safety and database status

- The initial worktree was on `master` with **33 modified tracked files, 281 untracked entries, and no staged changes**. Existing changes were preserved; no reset, stash, commit, or push was performed.
- The previously verified backup is at `C:\Users\Dell\.copilot\session-state\6ddfa72c-25bb-4595-b023-bc2da8efad81\files\pawhaven-phase-0-baseline-20261108\`. Its SQLite backup passed `PRAGMA integrity_check`; the four backed-up upload files (538,411 bytes total) were hash-verified.
- Before applying the migration, the active SQLite database again passed its integrity check, and all baseline table row counts matched the backup.
- The active database now has the single new migration applied. Compared with baseline, the only table-count difference is the expected `migrations` row increasing from 11 to 12. All application-data table counts are unchanged; the existing two orders and two order items remain present.
- No destructive SQL, reset, or data backfill was used. The application database migration adds one nullable timestamp only.
- Tests use the project’s in-memory SQLite test configuration; the application migration was separately previewed and applied against the configured local SQLite database.

## Changes made

### Purchase, orders, and inventory

- [OrderRequestController.php](../../app/Http/Controllers/OrderRequestController.php) validates current publication and stock while holding a row lock, then uses a conditional stock decrement to guard concurrent requests. Prices and sale prices come from the locked database record; client-supplied prices are ignored.
- The free-shipping calculation now reads the existing `shop.free_shipping_threshold_minor` setting key.
- [OrderInventoryService.php](../../app/Services/OrderInventoryService.php) restores stock only for orders marked as reserved, and clears that marker to prevent duplicate restoration.
- [OrderController.php](../../app/Http/Controllers/Admin/OrderController.php) and customer cancellation now lock the order and perform status change, inventory restoration, and relevant audit logging within a transaction.
- [ListingBrowseController.php](../../app/Http/Controllers/Marketplace/ListingBrowseController.php) persists pet-sale and adoption orders with their items atomically.
- [2026_11_08_000000_add_inventory_reserved_at_to_orders_table.php](../../database/migrations/2026_11_08_000000_add_inventory_reserved_at_to_orders_table.php) adds nullable `orders.inventory_reserved_at`. Existing orders remain `NULL`; no legacy inventory is changed.

### Roles, registration, and customer classification

- [AdminAuthController.php](../../app/Http/Controllers/Admin/AdminAuthController.php) rejects Seller accounts at the admin login step and re-checks Admin/Staff access after OTP verification.
- Customer-only order, purchase, adoption inquiry, and favorite endpoints use the existing customer middleware. The dashboard dispatcher rejects unknown roles rather than treating every non-admin account as a customer.
- [CreateNewUser.php](../../app/Actions/Fortify/CreateNewUser.php), the registration view, and seller registration shortcut make public registration customer-only. Existing Seller accounts and their Seller workspace are preserved; the Seller portal remains available to accounts provisioned by an administrator.
- [User.php](../../app/Models/User.php) provides the shared customer-role query scope. The customer directory and user-role totals use it; the directory UI no longer offers a filter for non-customer roles.

### Seed and installation safety

- [ShopSeeder.php](../../database/seeders/ShopSeeder.php) and [MarketplaceSeeder.php](../../database/seeders/MarketplaceSeeder.php) skip demo content outside local/testing environments.
- `ShopSeeder` no longer creates Admin, Seller, or Customer accounts or sample orders. [DatabaseSeeder.php](../../database/seeders/DatabaseSeeder.php) no longer creates a default test user.
- [INSTALLATION.md](../INSTALLATION.md) now identifies catalog seeding as local/demo-only and directs initial Admin setup to the existing `admin:create` command.

### Tests added or updated

- Product purchase rejection for unpublished, out-of-stock, and insufficient-stock products.
- Authoritative sale-price usage, stock decrement, and cancellation restoration.
- Atomic rollback when order-item persistence fails.
- Customer-only purchase access and dashboard role fall-through rejection.
- Admin/Seller login separation, Staff OTP login, and role re-check during OTP.
- Customer-directory role filtering and counts.
- Safe local/production seeder behavior and legacy-order inventory preservation.
- Existing pet-listing purchase expectations and performance characterization were updated for the customer-only registration/access policy.

## Verification results

- Focused Step 0.5 regression run: **44 passed, 209 assertions** before the final dashboard-role regression test was added.
- Final full suite: **192 passed, 843 assertions**, 0 failures.
- Laravel Pint: **passed** for the PHP files changed in this phase.
- The project-wide Pint check is not clean: it reports existing formatting issues in `docs/qa/scripts/benchmark_bcrypt.php`, `docs/qa/scripts/benchmark_bootstrap.php`, `docs/qa/scripts/benchmark_subsystems.php`, and `tests/Feature/PetMarketplace/ListingImageUrlTest.php`. Those files were not changed in this phase.
- Migration preview: `alter table "orders" add column "inventory_reserved_at" datetime`.
- Migration application: completed successfully; `php artisan migrate:status` reports the migration as `Ran`.
- Active database after migration: `PRAGMA integrity_check` returned `ok`; all baseline application-data row counts are preserved.

## Performance and configuration

No browser cold-start, warm reload, or customer-perceived performance benchmark was run as part of this correctness/security phase; no end-to-end performance improvement is claimed. The test suite’s performance instrumentation is a test-harness measurement, not a browser or cold-server benchmark. In the final run it reported, for example, 11.54 ms for the home workflow (12 queries, 0.66 ms database time) and 205.17 ms for customer login (6 queries, 0.31 ms database time); the customer profile-update sample varied up to 1,203.34 ms. These values do not reproduce or resolve the previously reported 14–15-second cold load.

No new admin-configurable commerce, internationalization, or payment-provider settings were introduced in this step. Production debug is already forced off by [config/app.php](../../config/app.php) when `APP_ENV=production`, even if `APP_DEBUG=true`; its regression test passed.

## Known limitations and deferred work

- No suspension lifecycle exists, so suspension behavior was not added or represented as implemented.
- Existing orders have no inventory reservation marker and are intentionally not credited back to stock when cancelled. Only new orders created by the updated purchase flow reserve stock.
- A stock reservation remains held while a new product order is active; cancellation restores it. Payment and fulfillment states remain as before.
- Seller registration is disabled publicly by default, but existing Seller accounts and Seller routes remain for compatibility. Vendor payouts, commissions, and multi-vendor behavior are not enabled.
- Online payment integration, complete checkout redesign, full admin/customer/Seller workspace redesign, international settings, translation work, and the Step 1 design-system foundation remain deferred pending separate approval.

## Commands to verify or run

```powershell
php artisan migrate:status
php artisan test
php vendor\bin\pint --test app\Http\Controllers\OrderRequestController.php app\Http\Controllers\Marketplace\ListingBrowseController.php app\Http\Controllers\Admin\AdminAuthController.php app\Http\Controllers\Admin\CustomerController.php app\Http\Controllers\Admin\OrderController.php app\Http\Controllers\Admin\UserController.php app\Models\Order.php app\Models\User.php app\Services\OrderInventoryService.php app\Actions\Fortify\CreateNewUser.php database\migrations\2026_11_08_000000_add_inventory_reserved_at_to_orders_table.php database\seeders\ShopSeeder.php database\seeders\MarketplaceSeeder.php database\seeders\DatabaseSeeder.php routes\web.php tests\Feature\ProductPurchaseTest.php tests\Feature\Admin\CustomerDirectoryTest.php tests\Feature\Admin\AdminAuthenticationTest.php tests\Feature\Admin\OrderManagementTest.php tests\Feature\Admin\ShopSeederTest.php tests\Feature\Auth\RegistrationTest.php tests\Feature\Admin\AdminAccessTest.php tests\Feature\MarketplaceBrowseTest.php tests\Feature\Performance\PerformanceCharacterizationTest.php
```

For a deployment with a backup already verified, apply pending backward-compatible migrations with:

```powershell
php artisan migrate --force
```

For local development:

```powershell
php artisan serve --host 127.0.0.1 --port 8000
```

No commit, push, deployment, or Step 1 work was performed.
