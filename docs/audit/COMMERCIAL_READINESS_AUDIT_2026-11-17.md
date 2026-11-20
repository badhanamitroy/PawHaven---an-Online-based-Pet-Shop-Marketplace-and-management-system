# PawHeaven Commercial Readiness Audit

**Audit date:** 2026-11-17  
**Scope:** Stage A inventory and risk review, safe critical repairs, buyer installation guidance, and isolated SQLite installation/upgrade validation.

This is an engineering assessment, not a security certification or release approval. Existing source changes were preserved; no changes were staged or committed.

## Architecture map

| Layer | Current organization |
|---|---|
| HTTP entry points | `routes/web.php` for public marketplace and account flows; `routes/admin.php` for authenticated admin/business operations; seller and settings route groups for their bounded workflows. |
| Middleware and authorization | Laravel authentication and verified-email middleware; staff/admin and employee-seller middleware; gates and policies; company/branch access is resolved through business memberships and scoped model queries. |
| UI | Blade layouts and page components with Livewire 4 components under `resources/views/pages` and supporting classes under `app/Livewire`; Vite 8 and Tailwind 4 compile the frontend. |
| Application logic | Controllers validate requests and delegate business workflows to services under `app/Services`, including business access, invitations/recruitment, sales desk, payments/refunds, inventory/returns, fulfilment, pricing, and currency policy. |
| Data model | Eloquent models and scope concerns under `app/Models`; policies under `app/Policies`; 24 ordered migrations under `database/migrations`. |
| Authentication and communications | Laravel Fortify, password hashing, verified email, email OTP for platform/admin access, device-session management, and mail classes under `app/Mail`. |
| Validation and tests | Form requests under `app/Http/Requests`; feature and unit suites under `tests`; PHPStan configuration targets level 7 and Pint is configured for formatting checks. |
| Integrations | Database, mail, filesystem, queue, cache, passkeys, Vite, and browser QA tooling. MySQL is configured but was not integration-tested in this audit. |

The route and component map is controller/service oriented; no broad repository layer was introduced. Migration history and business behavior remain the source of truth. A complete workflow review is still limited by the PHPUnit bootstrap issue and lack of a reachable network-level test server.

## Prioritized issue register

| ID | Severity | Component | Confirmed issue and root cause | Fix or action | Validation | Status |
|---|---|---|---|---|---|---|
| DB-01 | High | Active SQLite schema | The earlier homepage failure came from an older partial database: the company/branch migration and later feature migrations had not been applied. | The previously authorized recovery applied the pending chain. Added required-table/column and migration-history checks to System Health; documented backup-first upgrades. | Active `migrate:status`: 24 applied, 0 pending. In-process health inspection: schema current. | Resolved; active database unchanged during this audit. |
| DATA-01 | Critical | Demo seeder | The demo seeder targeted the repository's active SQLite database and deleted unrelated accounts when its partial reference scan found no links. | Restricted it to `testing` plus SQLite `:memory:`, changed accounts to synthetic `.test` identities, made it create-only, and made conflicts abort without changing accounts. The seeder was not run. | PHP syntax validation; no seeder execution. | Implemented; isolated behavior not PHPUnit-tested. |
| SEC-01 | High | Account self-deletion | The profile action could delete a platform administrator or staff/owner user, cascading business memberships; seller order attribution could also be detached. | Server-side deletion now rejects admin/staff and any account with membership history, listings, or seller orders; UI hides deletion for admin/staff. | Regression tests added; PHPUnit not run. | Implemented; not integration-tested. |
| AUTH-01 | High | Inventory, return, and refund reviews | Some services authorized using a cached membership role after locking the membership row, allowing a role/branch change race. | Recheck actor, active state, role, company, and branch from the locked membership before sensitive operations. | PHP syntax validation and static code review. | Implemented; concurrency tests not run. |
| AUTH-02 | High | Last platform administrator | Concurrent role changes could both pass an unlocked last-admin count. | Role update and audit now run in a transaction with administrator rows locked in stable order. | PHP syntax validation; no concurrency integration test. | Implemented; MySQL concurrency unverified. |
| AUTH-03 | High | Invitation administration | Summary counters were global even when the invitation list was role/company scoped. | All summary counts now use the same scoped query as the listing. | PHP syntax validation and query-scope review. | Implemented; PHPUnit not run. |
| PRIV-01 | Medium | Audit logs and QA artifacts | Invitation and role logs included account addresses/names; QA scripts and older notes embedded account identifiers and machine-specific paths. | Removed unnecessary user details from audit text, redacted historical Markdown account/path references, changed demo data to synthetic values, aligned the local listing seeder to the synthetic seller, and moved browser QA credentials/browser paths to environment variables. | Search review of first-party text; Node syntax check. | Implemented for source text. Existing generated PDFs/screenshots still need release review. |
| CLI-01 | High | `admin:create` | Passwords could be passed in process arguments; confirmation defaulted to yes; invalid password validation occurred after partial role/verification mutation. | Removed the password option, made promotion opt-in by default, and validate before mutation. | Regression test added; PHP lint. | Implemented; PHPUnit not run. |
| DIAG-01 | High | System Health | The health page only checked connection/table count, so an outdated schema was not actionable. | It now checks company/business, product/order, payment, inventory, fulfilment, pricing tables/columns, and compares migration files with applied history. It reports a backup-first diagnostic without mutating schema. | In-process controller inspection against the active database returned current schema and no missing items. | Implemented and exercised read-only. |
| INSTALL-01 | High | Buyer installation and backup guides | Node 18 guidance conflicted with Vite 8; file permissions exposed SQLite too broadly; mail and first-owner setup were unclear; MySQL backup examples placed passwords in command arguments. | Corrected Node minimum, private SQLite and backup modes, interactive admin password setup, mail configuration, first Owner assignment, upgrade procedure, and currency warning. | Documentation review; no production host deployment. | Implemented; hosting/provider behavior unverified. |
| DATA-02 | High | Currency snapshots | The currency migration adds literal `USD` snapshots to existing rows. A legacy shop configured for another currency may have amounts mislabeled after upgrade. The old application allowed currency settings before snapshots existed. | Documented a stop-and-review gate. No data rewrite or corrective migration was made because historical currency cannot be inferred safely from amounts. | Source inspection; isolated upgrade test confirmed existing product snapshots default to `USD`. Active database has no product/listing/order rows. | Open; requires a reviewed currency-reconciliation policy before supporting affected legacy upgrades. |
| TEST-01 | High | PHPUnit and static analysis | PHPUnit 12 aborts before test discovery because `is_readable()` returns false for project files despite successful file reads. | Did not bypass the bootstrap issue or alter vendor/ACL/runtime. Used PHP lint, isolated migrations, and direct schema inspection instead. | 273 PHP files linted; 0 syntax errors. Full PHPUnit suite not run. | Open environment blocker. |
| COMPAT-01 | Medium | Production database | MySQL/MariaDB code paths and row-locking guarantees were not integration-tested; SQLite does not prove production concurrency safety. | Documentation now states the limitation. | No MySQL/MariaDB instance available in this audit. | Open validation item. |
| RELEASE-01 | Medium | Generated buyer PDFs | Existing PDFs were not regenerated or overwritten to preserve existing artifacts; their content predates the current safe guide source. | Guide source now uses interactive password prompts, no personal workstation paths, and explicit Owner setup. Review/rebuild PDFs and screenshots before distribution. | Source inspected; generated PDFs not modified. | Open release packaging item. |

## Database and migration findings

- Effective connection: SQLite, relative database `database/database.sqlite`; configuration cache was absent.
- Active file metadata at recheck: 798,720 bytes; timestamp unchanged from the previous inspection. No journal/WAL/SHM sidecars or PHP processes were present. The manual exact database-path handle search had no match.
- Active migration history: 24 migration files, all 24 recorded as applied across batches 1 and 2; 0 pending.
- Required company, branch, memberships, product/order, payment/refund, inventory/return, fulfilment, and pricing schema is present. Health diagnostics report `current` and no missing tables or columns.
- Active SQLite `integrity_check`: `ok`; foreign-key violations: 0.
- Active row counts: users 5; device sessions 10; companies 1; branches, memberships, categories, products, orders, invitations, payments/refunds, inventory/returns, fulfilment events, branch prices, promotions, and coupons are empty.
- No Owner membership exists. No account was created or changed.
- Existing November 13 and November 17 backups were not opened for writing, replaced, or deleted. No new backup was needed because this audit did not modify the active database.
- Migrations 10/15 intentionally establish the company model without assigning ownership; legacy staff membership roles may be retained as inactive/unresolved rather than guessed. Migration 21 adds stock-history baselines; migration 22 records fulfilment snapshots. Rollbacks contain drops/column removals and must not be used as a production recovery path.

## Isolated database validation

1. Fresh installation: ran all 24 migrations against process-local SQLite `:memory:` only. All required tables appeared, integrity was `ok`, foreign-key violations were 0, one installation company was created, and no Owner membership was created.
2. Existing installation upgrade: applied the first 13 migrations in memory, inserted synthetic legacy user/branch/category/product/order/order-item rows, then applied the remaining 11. All rows survived, stock stayed at 8, company attribution was backfilled, one opening inventory movement was created, integrity was `ok`, and foreign-key violations were 0.
3. Active database: no migration, seeder, reset, rollback, restore, or data cleanup was run in this audit.

## Validation actually performed

- `php artisan migrate:status`: 24 applied, 0 pending.
- In-process System Health controller inspection: database connected, schema current, 0 missing tables/columns, 0 pending migrations.
- Fresh and upgrade migration chains in isolated in-memory SQLite; both passed.
- PHP syntax: 401 first-party files checked, 0 syntax errors.
- Browser QA JavaScript syntax: 20 files checked, 0 syntax errors.
- `git diff --check`: passed after the final edits; only Git CRLF normalization notices were emitted.
- Pint project-wide check: failed because many existing files across the repository need formatting. No formatter was run to avoid rewriting unrelated in-progress files.
- PHPUnit: not run due the known Windows `is_readable()` bootstrap failure. PHPStan was not run because a no-cache option is unavailable and a repository cache write was not approved.
- Network-level HTTP and browser tests were not completed. Earlier in-process checks of `/`, `/home`, and `/listings` returned 200; they were not repeated during this audit.

## Release readiness

The current local database is healthy and the complete migration chain passes fresh and legacy-schema simulations in isolated memory. The code changes address several confirmed security, data-loss, privacy, and onboarding defects, but the product is **not yet release-ready**: the currency-snapshot upgrade hazard needs a reviewed policy; PHPUnit and production MySQL validation remain open; and old generated guides/screenshots need review before distribution.

Do not apply a currency data-reconciliation migration to a buyer database until the owner confirms how historical non-USD records should be interpreted. The active database contains no such records and was not modified by this audit.
