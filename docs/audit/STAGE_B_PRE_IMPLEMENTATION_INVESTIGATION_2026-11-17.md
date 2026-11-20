# PawHeaven Stage B Pre-Implementation Investigation

**Date:** 2026-11-17  
**Scope:** Read-only investigation of currency integrity, first Owner provisioning, test infrastructure, MySQL/MariaDB compatibility, regression coverage, and PHP file-count discrepancy.

## 1. Executive summary

The active SQLite database is **D:\PawHaven\PH Web Version\WebApp\database\database.sqlite** (repository-relative path: database/database.sqlite). Laravel migration status currently reports all 24 migration files as applied, including the company/branch, currency/refund, inventory/returns, fulfilment, and pricing migrations. Read-only inspection found no product, listing, order, payment, refund, inventory, return, promotion, or coupon rows in this database, so no active monetary records were found to be affected by the historical currency defaults. This does not establish the condition of a buyer's upgraded database.

A confirmed upgrade hazard exists in **2026_11_20_000000_add_currency_snapshots_and_refund_transactions.php**: it adds USD as the default currency to existing products, pet listings, and orders. Earlier application code accepted arbitrary shop currency strings. If an older installation used a non-USD currency, existing integer minor-unit amounts can be labelled USD without conversion or evidence. The same database default can affect future inserts that omit currency. Payment transactions are a new table created with a USD default; refunds are also new and require an explicit currency. Current reports can sum multiple currency codes together and label the aggregate with one selected/current code.

First Owner setup is deliberately separate from company creation. The migration creates a neutral company without guessing which legacy user should own it. Current documented provisioning uses a Platform Admin account created through admin:create, then a verified customer is explicitly assigned the Owner membership through Business Management. This prevents an unauthenticated claim, but setup completion is inferred from whether an active Owner exists, and no Owner recovery or transfer workflow was found.

The PHPUnit bootstrap failure is reproducible at PHP's is_readable() boundary. PHP 8.5 reports false for existing files even though file_get_contents() succeeds. The evidence does not isolate whether the underlying cause is PHP's Windows filesystem/access check or a host filesystem/security interaction. PHPUnit was not run. No currently installed supported runtime with a verified working read check was established.

Static code review found transaction and lock usage across order, POS, stock, payments, refunds, coupons, returns, and fulfilment, but SQLite behavior does not prove MySQL/MariaDB row-lock guarantees. There are code-level lock-order inversions involving product and membership/company locks that need a consistent locking protocol and real-engine concurrency tests.

The current reproducible syntax scope is 401 PHP files under the nine first-party source/test roots, of which 2 are generated bootstrap/cache files and 399 are non-generated first-party files. PHP lint completed with zero syntax errors across all 401. The audit issue-register value of 273 has no documented reproducible scope; the audit validation value of 401 matches the current broad count, including the two cache files.

No source, application configuration, database, dependency, or existing report was changed during this investigation. This report is the only requested new artifact. No PHPUnit, browser, MySQL/MariaDB, or application workflow tests were run.

## 2. Confirmed facts versus hypotheses

### Confirmed from current source and read-only checks

- The active connection used by the application resolves to **database/database.sqlite**; the previous effective-configuration inspection found no configuration cache redirect. The current php artisan migrate:status --no-ansi command read this connection and reported 24 migrations, all Ran.
- Active migration history is 24 entries: 13 in batch 1 and 11 in batch 2. The latest is **2026_11_23_000000_create_pricing_and_promotion_tables**.
- Active database read-only inspection found 5 users, 10 device-session rows, and 1 company. Branches, memberships, products, pet listings, orders, order items, payment transactions, refunds, branch prices, promotions, coupons, redemptions, inventory movements, returns, and settings had zero rows. SQLite integrity returned ok; foreign-key check returned zero violations.
- The active database therefore has no current product/order financial history to reconcile. The 5 users, 10 sessions, and company row exist and were not modified.
- The company/branch migration intentionally does not assign legacy staff to business roles. The installation company exists without an active Owner membership.
- Migration 20 has literal USD defaults on upgraded products, listings, and orders. Historical settings code accepted arbitrary currency text, so non-USD legacy amounts are possible in buyer installations.
- Sales reporting and customer/admin dashboard code can aggregate amounts without grouping by currency.
- PHP 8.5.0 and PHPUnit 12.5.38 are installed locally; Composer declares PHP ^8.3. The older XAMPP PHP 8.0.30 mentioned in the audit is below the project requirement.
- PHP lint ran on 401 files under app, bootstrap, config, database, public, resources, routes, and tests; there were zero syntax errors. lang was absent. The two files in bootstrap/cache are generated cache artifacts and are reported separately below.
- Current Git status before writing this report showed 102 tracked changed entries, 79 untracked entries, and 0 staged entries. These are pre-existing changes; no Git operation that changes state was run.

### Hypotheses or unverified conditions

- Whether a specific buyer's legacy products, listings, or orders were actually priced in BDT, CAD, EUR, or another currency cannot be inferred from this local database, which has no such rows.
- Whether every configured MySQL or MariaDB version will behave correctly under the code's locking assumptions is unverified. No database server or multi-connection test was run.
- The exact OS/Windows filesystem or PHP runtime cause behind is_readable() returning false is not isolated by the available evidence.
- No browser/network request or end-to-end financial workflow was executed in this investigation.

## 3. Evidence and references

Primary references inspected include:

- Currency and pricing: CurrencyPolicy::current(), ::assertCanSet(), ::assertCanCreateIn(), and ::storedCurrencies() in app/Services/CurrencyPolicy.php; app/Services/PricingService.php; Product/PetListing creation hooks in app/Models/Product.php and app/Models/PetListing.php; app/Http/Controllers/OrderRequestController.php; app/Http/Controllers/Marketplace/ListingBrowseController.php; app/Services/EmployeeSalesDeskService.php.
- Monetary schema: database/migrations/2026_10_29_020100_create_pet_listings_and_related_tables.php; 2026_10_29_030100_create_shop_and_order_tables.php; 2026_11_19_000000_create_payment_transactions_table.php; 2026_11_20_000000_add_currency_snapshots_and_refund_transactions.php; 2026_11_23_000000_create_pricing_and_promotion_tables.php.
- Payments/refunds: app/Services/PaymentTransactionService.php; app/Services/PaymentRefundService.php; payment/refund models and controllers.
- Reporting and display: SalesReportingService::summary() and breakdown methods in app/Services/SalesReportingService.php; app/Http/Controllers/Admin/DashboardController.php; app/Http/Controllers/Admin/CustomerController.php; resources/views/admin/dashboard.blade.php; resources/views/admin/customers/index.blade.php; order, sales-desk receipt, email, and sales-report views.
- Business bootstrap and authorization: database/migrations/2026_11_10_000000_add_company_branch_business_access.php; database/migrations/2026_11_15_000000_add_business_integrity_guards.php; CreateAdminCommand::handle() in app/Console/Commands/CreateAdminCommand.php; BusinessAccessController membership store flow; BusinessAccessService::isInitialSetupAdmin(); BusinessMembershipService::assign(), ::assignForCompany(), and ::deactivate(); app/Policies/BusinessMembershipPolicy.php; routes/admin.php; docs/INSTALLATION.md.
- Test setup: phpunit.xml; tests/TestCase.php; vendor/phpunit/phpunit/src/TextUI/Configuration/BootstrapLoader.php bootstrap readability check; vendor/phpunit/phpunit/src/TextUI/Configuration/Xml/Loader.php environment parsing; vendor/phpunit/phpunit/src/Runner/Extension/PhpHandler.php environment override behavior; phpstan.neon.
- Current command checks: php artisan migrate:status --no-ansi (read-only); PHP lint on the stated file scope; php vendor/bin/phpstan analyse --help (help only). No analysis or tests were launched.

No .env values, credentials, account identifiers, cookies, tokens, or OTPs are included in this report.

## 4. DATA-02 currency impact analysis

### 4.1 Monetary storage and migration effects

| Entity/table | Monetary fields and currency source | Existing-row effect | Future-row behavior and risk |
|---|---|---|---|
| Products | products.price_minor, sale_price_minor, and migration-added currency | Migration 20 adds currency with USD default, so pre-existing rows receive USD even if their amounts were historically denominated otherwise. No conversion is performed. | The database default also supplies USD if an insert omits currency. Normal Eloquent Product creation assigns the CurrencyPolicy currency, but direct SQL/imports can hit the default. |
| Pet listings | pet_listings.price_minor and migration-added currency | Existing rows receive the same literal USD default. | Model creation normally sets CurrencyPolicy currency; omitted/direct writes can inherit USD. |
| Orders | orders.subtotal_minor, delivery_fee_minor, total_minor, and migration-added currency | Existing orders receive USD. Older orders may have been created under a non-USD shop setting. | New normal checkout/POS paths explicitly snapshot a code, but direct writes omitting it get the database default. |
| Order items | Existing unit_price_minor/subtotal_minor; later base/resolved price, discount, and pricing snapshot columns | Items do not have an independent canonical currency in the original schema; their currency is inherited from the parent order. Migration 23 adds pricing detail but does not repair an incorrectly labelled legacy order. | Normal checkout copies the parent/product pricing quote. A mismatch in parent currency contaminates receipts and derived totals. |
| Payment transactions | amount_minor, currency, status and reference fields | Table is created in migration 19, so it is new for the normal pre-transaction schema; no pre-existing payment rows are backfilled by that migration. | Column has USD database default. PaymentTransactionService explicitly copies the locked order currency and verification checks it against the order; ad hoc inserts can still receive USD. |
| Refund transactions | amount_minor, required currency, status/reason and event history | Created new by migration 20; no legacy refund rows are converted. | PaymentRefundService copies the payment currency and validates the order/payment/refund relationship. Currency is required without a USD default. |
| Branch product prices | Branch-specific price_minor and currency | Introduced in migration 23; no legacy table rows exist in a normal upgrade. | PricingService checks price currency against the order/cart currency. |
| Promotions and coupons | Discount/minimum/maximum minor-unit fields, currency, usage/redemption records | Introduced in migration 23; no historical promotion rows are converted. | PricingService filters by currency and snapshots redemption discount/currency. Missing code-level coverage exists for mixed-currency report boundaries. |
| Inventory movements and product returns | Quantities, movement/return state and references; no monetary valuation fields found | No inventory valuation amount is migrated. | Refund money is represented separately. Any future stock valuation/accounting feature needs its own explicit currency model rather than assuming product display currency. |
| Settings | settings stores shop.currency as installation/business configuration, not a historical per-record snapshot | It cannot establish the denomination of old amounts after a setting change. Active settings table is empty. | CurrencyPolicy falls back to USD where no valid setting is available; using this current setting for old rows is unsafe. |

The old product/order schema uses integer minor-unit values rather than exchange-rate conversion. Currency changes therefore cannot be repaired by changing a label unless authoritative evidence shows the intended denomination; converting amounts requires a separate approved FX policy and source rate.

### 4.2 Historical and current-currency assumptions

Historical business-setting code allowed a currency value with a general string length limit and uppercased it; the earlier settings view used free text. This demonstrates that a legacy deployment could have used non-USD values. The current CurrencyPolicy recognizes USD, BDT, CAD, and EUR, rejects unsupported changes, and prevents a currency change when stored product/listing/order codes differ. However, once legacy records have been stamped USD by the migration, storedCurrencies() cannot distinguish a true USD snapshot from an ambiguous default.

The active database has zero products, listings, orders, payments, refunds, pricing rows, and settings. Thus this local database has no discovered monetary records affected by the USD default. The 5 users, 10 sessions, and company row are not monetary records. No backup was inspected as part of this Stage B investigation.

### 4.3 Propagation, reports, and customer-facing documents

Normal current code paths largely propagate the snapshot consistently:

- Product and listing Eloquent creation hooks set currency through CurrencyPolicy.
- PricingService checks cart currency consistency, branch price currency, promotion/coupon currency, and signs the quote.
- Online checkout and POS create the order from the quote/product currency.
- PaymentTransactionService copies order currency; verification requires payment/order match.
- PaymentRefundService copies payment currency and validates refund, payment, and order currency.

The coverage gap is the database fallback and historic data, not the normal service path. Direct inserts, imports, or older application code can omit currency and receive USD. Order items inherit the parent order code rather than preserving a separate historical currency.

Confirmed aggregation risks:

- SalesReportingService::summary() chooses a single currency from the first selected order (or current setting) while summing order, payment, and refund amounts across the whole query. Breakdown calculations also need currency grouping.
- Dashboard order totals are summed and displayed with the current setting currency.
- Customer lifetime spend is summed without currency grouping and displayed with current shop currency.
- Consequently, if a business has records in multiple currencies, current reports can silently add unlike amounts and display the result using one code.
- Order/payment receipt views and order emails display the stored snapshot. If that snapshot was defaulted incorrectly, the output can present the wrong currency with no unresolved warning.
- No dedicated financial PDF/invoice/export implementation was identified in the inspected application routes/views. Existing HTML receipts and reports remain the relevant display boundary found.

### 4.4 Design comparison and recommendation

| Option | Safety and consequences |
|---|---|
| A. Stop affected upgrades | Safest against silent mislabelling but blocks all schema upgrades for buyers until a separate decision is made. It couples unrelated schema availability to financial reconciliation. |
| B. Upgrade schema and mark ambiguous history unresolved | Separates schema rollout from data interpretation. Requires explicit application/report behavior for unresolved records and a durable per-record status. Preferred as the base approach. |
| C. Require buyer mapping during migration | Can work for a single known historical currency but makes an automated deployment interactive, cannot safely represent mixed history with one answer, and risks operators choosing a guess merely to finish installation. |
| D. Record/group-level reconciliation | Necessary when currency changed over time or data has more than one denomination. Allows evidence-based mapping by record or a defined cohort while retaining exceptions. |

**Recommendation:** combine B and D. Run a non-destructive schema upgrade while identifying ambiguous legacy money records as unresolved. Reconcile with buyer-supplied evidence at record or explicitly bounded group level. Never infer currency from current setting, locale, business location, amount, or product name. Do not convert amounts without separately approved exchange-rate evidence.

### 4.5 Proposed reconciliation model and lifecycle

1. Add explicit resolution state to each monetary root record that can be independently reported: product, listing, order, payment, refund, and any later monetary adjustment. State should distinguish unresolved from verified; no default should silently mark arbitrary database writes verified.
2. Keep the stored code unchanged until a buyer resolves it. Store resolved_currency or update the existing snapshot only after explicit confirmation. Preserve existing correctly snapshotted records unchanged.
3. Create a reconciliation batch with operator, time, reason, evidence description/reference, scope, and completion state; create per-record reconciliation items with prior code, proposed code, record identity, reviewer, and result. Group mappings must enumerate their exact record set and allow exceptions. Enforce related-code invariants, e.g. payment/refund/order currency agreement and order-item inheritance.
4. Record every resolution as an append-only audit event. Corrections supersede earlier events and require a reason/evidence; do not silently overwrite history. Reversal should use a compensating correction event. Currency-code correction itself does not change amount. Any monetary conversion is a distinct approved transaction with source code, target code, rate, rate date/source, rounding rule, and approver.
5. Reports and dashboards group verified values by currency. They never combine currencies into one grand total. Unresolved amounts are separated and labelled “currency unresolved”; they cannot be presented as a verified financial total.
6. Block unresolved products/listings from new checkout/POS sales until resolved. Existing orders remain viewable with an unresolved warning. Payment/refund verification and new refunds must not assume the installation currency for unresolved historic orders.
7. Remove/avoid USD DB defaults for newly created money records once application writers explicitly supply and validate currency. If a compatibility default must remain temporarily, mark omitted currency unresolved rather than verified.
8. A migration that can still observe all rows before the first currency backfill can mark the then-existing rows unresolved. For installations where migration 20 is already applied, the schema has no reliable provenance timestamp showing which rows were stamped by that migration versus created afterward. Those installations need an explicit buyer manifest/record-level review; an automatic classifier cannot be justified from current source.

**Product-owner decision required:** define acceptable evidence for mapping; whether financial actions on unresolved orders are fully blocked or limited to read-only history; and the approved FX conversion/accounting policy, if conversion is ever required.

## 5. First Owner provisioning and secure onboarding

### 5.1 Current behavior

- 2026_11_10_000000_add_company_branch_business_access.php creates a neutral company and backfills company references. Its comments explicitly avoid assigning legacy staff to Owner, Manager, or Seller membership.
- 2026_11_15_000000_add_business_integrity_guards.php deactivates ambiguous/unresolved legacy membership data and adds guards including one active Owner per company.
- docs/INSTALLATION.md documents creating a Platform Admin with admin:create, then signing into Business Management and explicitly assigning a verified customer as Owner. The Platform Admin is not granted the company’s private business access just by being an admin.
- BusinessAccessController exposes membership creation only behind authenticated/verified admin or business authorization. The setup admin can assign the first Owner while no active Owner exists.
- BusinessMembershipService locks the company and rechecks setup state, locks the target user, validates initial Owner eligibility, validates branch scope, and creates/activates the membership in a transaction. A target customer must be verified and satisfy checks that prevent converting an account with existing customer/seller business history into staff.
- The admin command requests passwords through a hidden secret prompt; it does not require putting the password in command-line arguments. Existing command tests cover create, promote, and invalid-password atomicity.
- No public unauthenticated Owner claim route was found. Ordinary users do not have the setup-admin policy needed to assign membership.
- A Platform Admin does not qualify as the initial target merely by holding the platform role; initial target validation is for an eligible verified customer or staff member. Platform Admin access remains separate from company business data.
- Setup is inferred from absence of an active Owner. There is no durable setup_completed_at or one-time bootstrap state found. Owner deactivation is refused by the normal membership service, but no owner replacement/transfer or ownerless-company recovery workflow was found.
- First-owner assignment has a company-row lock and database uniqueness guard, which should serialize competing claims on a production engine supporting the intended row locks. This is not demonstrated by SQLite or by existing tests.

### 5.2 Recommended minimal buyer lifecycle

1. Install dependencies from the product release and run the documented schema upgrade after a verified backup. No public installation endpoint should create privileged access.
2. Run admin:create from the buyer-controlled server console. Keep password entry hidden and interactive; never supply passwords in shell arguments, logs, or URLs.
3. Register the intended Owner through normal email verification, then have the Platform Admin explicitly select that verified account and assign the company Owner membership.
4. Atomically record initial ownership and a durable installation/company setup-complete marker. Bootstrap must lock a single state row, check that setup has never completed, revalidate account/company eligibility, and be unavailable after completion.
5. Require a dedicated audited ownership transfer for an existing company. Reauthenticate the current Owner, verify the target, update both memberships atomically, and preserve a history event. If the company has no active Owner, use an offline console recovery action with explicit operator confirmation, reason, and audit record; it must not become a web claim path.
6. For legacy companies without an Owner, show a clear Platform Admin setup state and allow only the controlled assignment workflow. Do not invent or automatically promote an account.

**Risks and decisions:** decide who may invoke ownerless recovery, required evidence/approval, whether more than one Owner is allowed (current guard indicates one active Owner), and whether the install-wide marker is per company or per installation. Existing code has no safe transfer/recovery semantics to preserve.

## 6. PHPUnit and regression validation infrastructure

### 6.1 Findings

- Runtime is PHP 8.5.0 with PHPUnit 12.5.38. Composer requires PHP ^8.3; the available XAMPP PHP 8.0.30 is not compatible with the project requirement.
- PHPUnit XML uses vendor/autoload.php as bootstrap and configures a SQLite :memory: test connection, empty DB URL, array cache/mail/session, and sync queue. PHPUnit's XML environment loader does not force XML values over existing process environment values by default. The current process had no APP_ENV, DB_CONNECTION, DB_DATABASE, or DB_URL keys set; a future shell/CI override could still affect isolation.
- Tests/TestCase resets SettingsService's cache; feature suites use RefreshDatabase. The XML configuration intends per-process in-memory SQLite, but because bootstrap stops first, isolation has not been proven through an executed suite.
- PHPUnit's BootstrapLoader checks is_readable($filename) and throws before including the bootstrap. A PHP 8.5 read-only probe reported file_exists and realpath success and is_readable=false for PHPUnit/project/bootstrap/autoload/database files, while file_get_contents() succeeded. The same mismatch was observed for runtime support files. open_basedir and disable_functions were empty.
- This evidence narrows the failure to the PHP is_readable() result on the current Windows environment, rather than a missing project file or PHPUnit-specific target. It does not prove whether the cause is PHP runtime/Windows API behavior, filesystem access semantics, or another host security layer.
- The PHPUnit run remains blocked. The bootstrap was not bypassed, vendor was not changed, and ACL/PHP settings were not changed.
- PHPStan configuration includes application/bootstrap/config/database/routes at level 7, with no explicit temporary directory. phpstan analyse --help showed no no-cache/no-write option; PHPStan analysis was not run.

### 6.2 Safe options and minimum authorization

A safe path is to use an already-installed PHP 8.3+ runtime for which the same file checks succeed, if the buyer/developer environment already has one. No such runtime was verified. Otherwise, the minimum next approval is to investigate and repair the current PHP/Windows filesystem-check behavior, which may require a runtime or system-level change explicitly forbidden in this read-only stage. Do not choose PHP 8.0.30, patch PHPUnit/vendor, bypass the is_readable() guard, or change ACLs as an unapproved workaround.

Before tests are re-enabled, add/verify a fail-fast guard that refuses to run if the resolved test connection is the active database/database.sqlite; force all test environment values in the runner/CI rather than trusting overridable XML defaults. Use isolated in-memory SQLite for unit tests and unique disposable MySQL/MariaDB databases for engine tests.

### 6.3 Test priorities when a supported runner exists

1. Install/upgrade migration tests against disposable databases, with schema diff and preserved fixture counts.
2. Currency upgrade fixtures for USD and non-USD histories, unresolved reporting, mapping validation, receipt warning, and mixed-currency prevention.
3. First Owner bootstrap, replay rejection, two-contender race, Admin isolation, transfer, and ownerless recovery.
4. Company/branch authorization and membership revocation under concurrent order/stock/refund actions.
5. Payment/refund bounds, duplicate idempotency keys, partial payment, cancellation, inventory restock, and coupons under concurrent requests.
6. Buyer installation/configuration preflight and actionable missing-schema diagnostics.
7. Existing authentication, marketplace, careers, recruitment, POS, fulfilment, pricing, promotion, and reports suites.

## 7. MySQL/MariaDB production compatibility risk matrix

Static inspection only. No database server was installed or started, and no concurrency guarantee is claimed.

| Workflow | Transaction/lock strategy observed | Keys/invariants | MySQL/MariaDB concerns and required validation |
|---|---|---|---|
| Online checkout | OrderRequestController transaction locks products/company/branch and persists order, item, inventory reservation, fulfilment event, and redemption; retry attempts are configured. | Unique online submission key; stock and order-state checks. | Lock order versus POS/cancellation paths; engine isolation and retries; simultaneous final-unit purchase. |
| POS | EmployeeSalesDeskService transaction locks membership, company, branch, products ordered by id, then writes order/items, reservations, fulfilment, coupon and optional payment. | Unique POS submission key; quote and stock validation. | Consistent membership/company/product lock order; online/POS contention; deadlock retry behavior. |
| Inventory adjust/reserve/release | InventoryMovementService locks order/item/product for order effects; adjustments lock product and use unique reference keys; conditional stock bounds and movement records are in transactions. | Unique movement reference; reserved/available stock bounds. | **Confirmed static lock-order inversion:** ProductController and InventoryController lock product before calling adjustToQuantity; service authorization locks membership/company before product. Other workflows also vary whether order, membership, company, or product is first. InnoDB can deadlock these transactions. Retry coverage is not a global lock protocol. |
| Returns/restock | ProductReturnService locks order/return/item/product and uses idempotent state transitions; inventory movement rows record stock receipt. | Return/reference uniqueness and state checks. | Competing return receipt/cancellation/fulfilment; standardize lock order and assert no duplicate restock under two connections. |
| Payment create/verify | PaymentTransactionService uses transactions; verify locks order then payment and checks currency/amount; recording locks order and bounds total received. | Submission key and reference hash uniqueness; payment/order currency invariant. | Concurrent partial payments and verification; unique violation behavior; isolation of aggregate sum under concurrent writes. |
| Refund request/complete | PaymentRefundService locks order/payment for request and order/payment/refund for completion; bounds refunds against verified payment and prior refunds. | Unique request submission key and bounded total. | Concurrent approvals and partial refunds; serial order/payment/refund lock discipline; retry/deadlock handling. |
| Coupon/promotion redemption | PricingService locks product IDs, branch prices, active promotion/coupon; redemption usage is counted in checkout path. | Unique redemption per order; coupon limits and per-customer checks. | Verify locking read/current-read semantics and coupon-row serialization under configured isolation; concurrent last-use and per-customer limit tests. |
| Fulfilment/cancellation | FulfilmentService locks order and authorizes company/branch membership; item/product locks are ordered by id in packing paths; events use submission idempotency. | Unique event/submission idempotency and guarded state transitions. | Concurrent pack/ship/deliver/cancel/return transitions and lock interaction with stock/payment operations. |
| Last Platform Admin | UserController locks the current Admin set ordered by id before changing target role. | Application guard preserves at least one Admin. | Concurrent demotion and Admin promotion/create may involve a changing row set/phantom; test two-connection invariant and database constraints/serialization. |
| Membership/review actions | BusinessMembershipService deactivation locks company then membership; other sensitive workflows often lock membership then company. | Unique active assignment/Owner guards and application authorization. | **Confirmed static lock-order inversion:** membership/company order differs across operations. Concurrent revocation, refund, stock review, and ownership changes can deadlock or observe stale authorization unless rechecked after lock acquisition. |

Migration 15 contains driver-specific SQLite, MySQL/MariaDB, PostgreSQL, and SQL Server paths for generated columns/indexes/triggers. The branch code is source evidence of intent, not a successful engine compatibility test. Validate DDL syntax, generated-column/index support, trigger behavior, schema metadata APIs, JSON types, FK/cascade behavior, transaction semantics, collation/unique constraints, and rollback of partially failed DDL against each advertised engine/version.

### MySQL/MariaDB test plan

- Choose exact MySQL and MariaDB versions only after checking Laravel 13's supported matrix; do not advertise an engine/version without CI evidence.
- Run fresh install and 13-applied-to-24 upgrade fixtures on isolated InnoDB schemas; assert schema, constraints, triggers, migration table, and retained fixture rows.
- Use at least two independent connections/processes and barriers to race: final stock sale across online/POS; coupon last redemption; partial payments and verification; refund requests/approval; first Owner bootstrap; last Admin demotion versus promotion; membership revocation during sensitive operation; fulfilment/cancellation/return restock.
- Assert invariants and side effects: no negative/duplicated stock, no duplicate orders/events/refunds, no overpayment/over-refund, no double redemption/restock, no unauthorized action after revocation, and at least one active Admin/Owner under the defined rules.
- Capture deadlock/retry behavior, timeouts, and user-visible failure paths. Use unique disposable databases per job, never the active SQLite file.

## 8. Regression coverage gaps for recent audit fixes

Tests below were inspected but not executed due the bootstrap failure.

| Audit item | Existing coverage found | Missing regression cases |
|---|---|---|
| DATA-01 demo-account seeder safety | DemoAccountsSeederSafetyTest rejects demo setup outside local environment. | Safe local success with an isolated database; reject file/non-SQLite targets; missing/invalid password leaves no partial accounts; conflicting identity does not rewrite an existing account; assert no deletion or unrelated changes. |
| SEC-01 account deletion protection | ProfileUpdate tests prevent selected admin/staff self-deletion and listing deletion with orders. | Membership history, seller-order attribution, and other business references block deletion; direct endpoint variants and complete relation matrix. |
| AUTH-01 sensitive company/branch actions | Inventory, payment/refund, and return tests cover ordinary operations and branch scoping. | Authorization recheck after membership/branch revocation; wrong-company direct IDs; two-connection race between authorization revocation and stock/refund/return mutation. |
| AUTH-02 last Platform Admin | User/admin tests cover ordinary role and access rules. | Concurrent last-Admin demotions; simultaneous promotion/create versus demotion; rollback/constraint behavior after a failed role change. |
| AUTH-03 invitation onboarding | InvitationOnboarding tests cover authentication/onboarding flow. | Assert resulting company, branch, role, active state and counts; replay/expiry/concurrent acceptance and cross-company invitation attempts. |
| CLI-01 admin command | CreateAdminCommandTest covers creation, promotion, invalid password without partial promotion. | Confirmation/cancellation behavior, no-password-in-argv invocation, password never echoed/logged, collision and failure atomicity. |
| DIAG-01 System Health | SystemHealthTest covers four authentication/access cases. | Missing table/column and migration-history diagnosis, read-only behavior, actionable output and redaction, unauthorized access. |
| INSTALL-01 install guide | docs/INSTALLATION.md documents setup. | Automated fresh install/upgrade preflight, supported-host PHP extension/storage/database checks, owner onboarding, migration failure guidance, and backup/restore walkthrough verification. |
| DATA-02 currency | Payment/Pricing tests cover current currency snapshots and currency mismatch paths. | Legacy USD-default ambiguity, non-USD upgrade, unresolved status, record/group mapping audit, mixed-currency report prevention, receipt warnings, and no conversion. |
| Owner bootstrap | BusinessAccessIsolation tests cover setup-admin assignment and Admin isolation. | Concurrent claim, one-time setup marker/replay, owner transfer/recovery, active-owner uniqueness under production DB. |

Existing suites are evidence of intended behavior only; no result is claimed from them in this investigation.

## 9. PHP syntax count discrepancy

Reproducible command scope: recursively count files ending in .php under app, bootstrap, config, database, lang if present, public, resources, routes, and tests. Exclude vendor, node_modules, storage, and compiled build output.

| Directory | Current PHP file count |
|---|---:|
| app | 159 |
| bootstrap | 4 |
| config | 12 |
| database | 35 |
| lang | 0 (directory absent) |
| public | 1 |
| resources | 125 |
| routes | 5 |
| tests | 60 |
| **Total in these roots** | **401** |

Two of these files are generated cache files under bootstrap/cache; therefore 399 are non-generated first-party source/test files and 2 are generated cache artifacts. The non-extension Laravel root script artisan is not included. vendor is excluded.

PHP 8.5.0 php -l ran on all 401 scoped files and reported zero syntax errors. The audit validation summary's 401 is reproducible under this broad scope. The issue-register value of 273 cannot be reconciled to a documented file list or scope in the report; it is not the current count under the stated scope. It may represent a narrower earlier selection, but that is only a hypothesis.

## 10. Implementation dependency order

1. **Product decisions:** approve the unresolved-currency behavior, evidence standard, FX/conversion boundary, first-owner recovery authority, and supported production database/version matrix.
2. **Currency migration design:** handle both databases where migration 20 is pending and installations where it has already run; make ambiguity detectable without changing existing amounts or silently treating defaults as verified. Add migration/upgrade fixtures first.
3. **Currency workflow and reports:** explicit currency writes, reconciliation audit trail, reporting by currency, unresolved visibility/action restrictions, and receipt/email warnings. Preserve existing verified snapshots.
4. **Owner lifecycle:** durable one-time bootstrap state, explicit console/customer assignment, transfer and recovery rules, audit history, and concurrency-safe implementation.
5. **Test runner isolation:** restore a supported PHP test path with no vendor/ACL bypass and an active-database guard; prove an isolated test database before running regression suites.
6. **Database lock protocol:** define a consistent resource lock order and reauthorization point; fix confirmed inversions in a focused change; add production-engine concurrency tests.
7. **Buyer installation/upgrade docs and preflight:** document supported runtimes/engines, backups, migration sequencing, first Owner setup/recovery, currency reconciliation, and actionable schema diagnostics.
8. **Release validation:** fresh install, upgrade fixture, regression suites, MySQL/MariaDB concurrency suite, and HTTP/browser workflows, each reported separately by evidence type.

## 11. Acceptance criteria for implementation tasks

| Task | Acceptance criteria |
|---|---|
| Currency legacy classification | No legacy amount is silently relabelled as verified USD; pending and already-applied migration-20 installations both have an explicit, auditable way to identify ambiguous records; correctly snapshotted rows remain unchanged. |
| Currency reconciliation | Buyer can map exact records or explicit groups with evidence/reason; invalid/mismatched relationships are rejected; every change is audited and corrections are compensating events; no amount conversion occurs without a separately approved rate policy. |
| Currency reports/documents | Financial totals are grouped by currency; no mixed-currency grand total is shown; unresolved history is visibly segregated; receipts/emails cannot misstate unresolved history as a verified currency. |
| First Owner bootstrap | No unauthenticated web claim; no password in command arguments/logs; the transaction is one-time and replay-resistant; target eligibility is rechecked; concurrent claims leave exactly the permitted Owner state; Platform Admin remains isolated from private business records. |
| Owner transfer/recovery | Explicit permissions and evidence are enforced; actions are atomic, audited, reversible by compensating history, and covered for ownerless legacy companies. |
| PHPUnit/test isolation | Supported PHP runtime passes the bootstrap read check without bypass; tests fail fast if the active SQLite path is selected; tests run against disposable DBs only; actual suite results are recorded. |
| Lock protocol and MySQL/MariaDB | Resource order and authorization recheck are documented and consistent; migration and feature suites pass on each advertised engine/version; concurrency invariants above pass under multiple connections with retries/deadlocks characterized. |
| Installation/upgrade | A clean buyer environment can follow documented preflight, fresh install, upgrade, backup, and Owner setup without developer-specific paths; upgrade retains fixture business data; schema mismatch diagnostics identify the required migration state without changing schema automatically. |

## 12. Product-owner decisions and implementation blockers

- Which sources of evidence are acceptable to verify historical currency (previous settings exports, invoices, accounting ledgers, customer records), and who is authorized to approve the mapping?
- Should unresolved historical orders block all new payment/refund actions or permit specific operations with an explicit warning and manual approval?
- Is FX conversion in scope at all? If yes, define authority, rate source/date, rounding, accounting adjustments, and correction policy.
- Who can recover an ownerless company, under what offline verification and audit requirements? Should ownership transfer require current-Owner approval or a documented recovery override?
- Is there exactly one active Owner per company, as the current schema guard implies, or should multiple Owners be supported?
- Which MySQL/MariaDB versions and hosting profiles are contractual Vibe96 product targets?
- Is the product owner willing to authorize investigation of the Windows/PHP filesystem check or provide an already-installed working PHP 8.3+ runtime? No environment change was made here.

## 13. What could not be verified

- No PHPUnit suite was run; the known bootstrap is_readable() abort remains.
- No PHPStan analysis was run; only command help/configuration were inspected.
- No MySQL/MariaDB installation, migration, or concurrency test was run.
- No HTTP/browser smoke test or checkout/POS/payment workflow was run in this investigation.
- The local active DB has no monetary business rows; currency ambiguity in a populated buyer database cannot be measured from it.
- Migration 20 does not preserve row-level provenance for installations where it has already been applied; existing current USD codes cannot alone distinguish old backfills from later correct records.
- The exact Windows/PHP subsystem producing the is_readable() mismatch is unproven.
- The historical 273-file syntax scope has no exact file list/documented counting rule and cannot be reconstructed from the current report.
- No financial PDF/export feature was identified in the inspected application paths; any external integration or uninspected deployment customization remains outside this evidence.

## 14. Read-only validation and Git state

- php artisan migrate:status --no-ansi: 24 migrations, all reported Ran; no migration was run.
- Read-only SQLite inspection: active file integrity ok, foreign-key violation count 0, key table counts as reported above.
- php -l: 401 scoped files, zero syntax errors.
- php vendor/bin/phpstan analyse --help: help only; no analysis started.
- PHPUnit, seeders, migration application, database writes, browser tests, and server startup were not performed.
- Pre-report Git status: 102 changed tracked entries, 79 untracked entries, 0 staged entries. Existing entries were preserved. This report is a new untracked file; no staging, commit, push, cleanup, reset, stash, restore, or rebase was performed.
