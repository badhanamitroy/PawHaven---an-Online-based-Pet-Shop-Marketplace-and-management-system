# Phase D1 — Linux CI Baseline Verification

**Date:** 2026-11-18
**Repository:** D:/PawHaven/PH Web Version/WebApp
**Branch / inspected HEAD:** master / 1dcaf456b78626469eb60455ab6bf48be944fc19
**Mode:** Read-only verification and implementation planning
**Test execution:** None in this phase

## 1. Executive summary

The existing Linux Actions workflow is configured for pushes to main and pull requests. It uses Ubuntu, PHP 8.3, Composer v2, and Node 22. It has one job and runs composer setup, then composer ci:check. The Composer script chain includes Pint, PHPStan, and PHPUnit through artisan test. A failing command is not marked as allowed to fail.

The workflow is not manually dispatchable, defines no MySQL/MariaDB service, has no explicit dependency-cache step, and provides no database-engine matrix or commerce concurrency harness. Its composer setup command runs artisan migrate --force in the workflow checkout before tests. Configuration and scripts are source evidence only; no Actions run result was available to inspect.

The local branch is master, with 102 tracked modifications, 81 untracked entries, and no staged changes at baseline. The current working tree cannot be tested by the hosted workflow without submitting a committed snapshot; this phase forbids Git-changing operations. The GitHub CLI gh is unavailable, so authenticated GitHub identity, workflow history, and a tested run SHA could not be established. The local HEAD SHA above is not asserted to have been tested.

PHPUnit configuration intends SQLite :memory: and sets APP_ENV=testing, but its environment variables are not forced. tests/TestCase.php contains no database identity guard. Numerous tests use RefreshDatabase; if process environment or cached Laravel configuration selects a non-test database, the suite could migrate/use it. The workflow does not force database variables at process level. Do not run composer setup or the suite against this working tree until a fail-closed guard and safe execution context are established.

Existing tests cover useful sequential pricing, payment/refund, inventory, POS, fulfilment, ownership authorization, invitation, and idempotency behavior. No inspected test proves MySQL 8.4 row-lock behavior or races in commerce operations. EmailOtpConcurrencyTest tests cache-lock/OTP behavior, not database transaction concurrency.

The approved product-owner decisions are recorded separately in docs/architecture/PRODUCT_OWNER_DECISIONS_2026-11-18.md. This report does not implement D2–D5 or modify prior reports.

## 2. Scope, evidence and limitations

### Inspected configuration and reference documents

- .github/workflows/tests.yml — complete workflow.
- composer.json — scripts and declared PHP/framework/dev-tool constraints.
- composer.lock — selected locked versions only.
- phpunit.xml — suites, bootstrap, and test environment declarations.
- tests/TestCase.php — base test setup and teardown.
- phpstan.neon — analysis paths and level.
- config/database.php — database default, SQLite fallback path, and URL configuration references.
- docs/audit/STAGE_B_PRE_IMPLEMENTATION_INVESTIGATION_2026-11-17.md.
- docs/audit/STAGE_C_ARCHITECTURE_DECISIONS_2026-11-17.md.
- The actual previous audit filename visible in the repository is docs/audit/COMMERCIAL_READINESS_AUDIT_2026-11-17.md; it was not modified.

The following test files were inspected through targeted source searches for suite use, methods, or currency/authorization behavior; not all were read line-by-line: tests/Feature/PaymentTransactionsTest.php, PaymentRefundsTest.php, PricingAndPromotionsTest.php, SalesReportingTest.php, CustomerDashboardTest.php, Admin/CustomerDirectoryTest.php, Admin/BusinessAccessIsolationTest.php, Admin/InvitationOnboardingTest.php, Admin/CreateAdminCommandTest.php, InventoryReconciliationTest.php, EmployeeSalesDeskTest.php, FulfilmentOperationsTest.php, ProductPurchaseTest.php, and Auth/EmailOtpConcurrencyTest.php.

No .env values, credentials, user/customer data, session values, tokens, or OTP values were inspected or reported. The active business database was not opened. No test, migration, seeder, browser, or database-server operation was run. This report makes no test-pass claim.

### Git baseline and preservation

- Before writing this report and the decision register: **102 modified tracked entries, 81 untracked entries, 0 staged entries**.
- Branch: master.
- Inspected local HEAD: 1dcaf456b78626469eb60455ab6bf48be944fc19.
- No reset, clean, stash, restore, rebase, stage, commit, or push operation was used.
- After document creation, the intended delta is two new untracked Markdown files only. Final status is reported in the completion message after rechecking it.

The repository has substantial pre-existing work. It was left intact.

## 3. Exact Linux workflow configuration

### Triggers and permissions

.github/workflows/tests.yml defines workflow name tests.

- push trigger restricted to branch main.
- pull_request trigger with no branch filter.
- No workflow_dispatch, schedule, or other trigger is configured.
- Workflow permission is contents: read.
- One job, ci, runs on ubuntu-latest.

The local branch is master; a direct push there would not match the configured push trigger. Pull requests are a trigger, but creating a branch/PR is prohibited in this phase.

### Steps and toolchain

1. Checkout using actions/checkout pinned to 3d3c42e5aac5ba805825da76410c181273ba90b1 (commented v7.0.1), with persist-credentials: false.
2. Setup PHP using shivammathur/setup-php pinned to f3e473d116dcccaddc5834248c87452386958240 (commented v2): PHP 8.3, Composer v2, coverage: none, and no explicit PHP extension list.
3. Setup Node using actions/setup-node pinned to 820762786026740c76f36085b0efc47a31fe502 (commented v7.0.0): Node 22, no explicit Node dependency-cache option.
4. Run composer setup.
5. Run composer ci:check.

No explicit actions/cache step or database service container is present. No job matrix, coverage upload, MySQL/MariaDB version, PDO-driver declaration, database isolation level, or business concurrency job is configured.

### Actual Composer command chain

composer.json declares PHP ^8.3, Laravel ^13.17, PHPUnit ^12.5.23, Pint ^1.27, and Larastan ^3.9. Selected locked versions inspected in composer.lock: Laravel Framework 13.35.0, PHPUnit 12.5.38, Pint 1.32.1, Larastan 3.13.0, PHPStan 2.3.1.

composer setup executes, in order:

1. composer install
2. Copies .env.example only if .env does not exist.
3. artisan key:generate
4. artisan migrate --force
5. npm install
6. npm run build

composer ci:check disables Composer's process timeout and invokes the test Composer script. test executes:

1. artisan config:clear --ansi
2. pint --parallel --test
3. phpstan analyse
4. artisan test

Thus Pint, PHPStan, and PHPUnit are all in the configured check path. PHPUnit XML defines two suites, Unit (tests/Unit) and Feature (tests/Feature), with no suite exclusions. The Actions file has no continue-on-error, if: always() masking, or shell || true around these workflow commands; a nonzero command should fail its step/job and stop the next step.

The workflow does not explicitly name a database. config/database.php defaults to SQLite and its fallback path is database/database.sqlite. The runner's composer setup creates its own checkout environment and runs migrate --force. This is CI runner state, not evidence local setup is safe. The workflow does not process-level force DB_CONNECTION, DB_DATABASE, or DB_URL.

## 4. GitHub Actions execution evidence

gh is not installed/available in the current environment. Authentication status, repository identity through GitHub API, recent workflow runs, success/failure results, and run-associated commit SHAs could not be queried. No run result is claimed.

The local Git remote is origin for badhanamitroy/PawHaven; the local branch/HEAD do not establish that any Actions run tested this working tree. No GitHub workflow run URL, event, or run SHA was available for inspection. The workflow has no workflow_dispatch, so there is no manual dispatch path to describe.

The current uncommitted and untracked changes cannot be tested by a GitHub Actions checkout of local HEAD. An Actions run tests the commit/ref supplied by its event, not arbitrary uncommitted files on this machine. No branch, commit, or PR was created, and no workflow was triggered.

**Safest next option:** After explicit authorization for an appropriate Git-changing submission, have the existing workflow validate a reviewable committed snapshot and record the exact run/commit. Alternatively, use an already-authorized Linux CI mechanism that can receive an isolated source snapshot without altering this repository's Git state. No such mechanism was found or invoked in D1.

## 5. Test database safety assessment

### Intended behavior

phpunit.xml sets APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and empty DB_URL. These are ordinary <env> declarations without force="true". Existing process environment values may therefore take precedence. config/database.php resolves the connection using DB_CONNECTION, and the SQLite connection can fall back to database/database.sqlite when no database value is supplied. A non-empty database URL can also affect connection details.

composer test clears Laravel's config cache before Pint, PHPStan, and Artisan tests. This reduces stale-cache risk on that path but does not force PHPUnit XML values over process environment. Direct test execution or cached configuration may resolve differently. The workflow itself does not set test DB variables at process level.

tests/TestCase.php resets the SettingsService cache and provides a Fortify helper. It does not assert the test environment, inspect the resolved connection, reject a non-test host/database, or compare a resolved SQLite path with the active database path.

Many unit/feature test classes use Laravel's RefreshDatabase. That trait can migrate/reset the database selected by the test application's resolved connection. The inspected test tree had no direct migrate:fresh, migrate:refresh, or migrate:reset invocation; the risk comes from the configured connection and framework test trait. No custom test-database guard was found.

### Assessment and proposed later guard

The intended SQLite in-memory default is appropriate for isolated functional tests if actually selected. It is not fail-closed. It does not prove that local tests cannot select the active SQLite file, particularly with process-level DB variables or stale cached configuration. composer setup itself runs artisan migrate --force and must not be run in a buyer's working directory as generic test setup without first proving its resolved target.

A later guard should:

1. Force APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and empty DB_URL at the process/test-runner boundary.
2. Resolve and inspect the Laravel connection before test traits or migrations begin.
3. Fail closed unless SQLite is in-memory or a specifically named disposable database in a designated test-only directory is used.
4. Canonicalize any file-backed SQLite path and reject database/database.sqlite and known business-data paths.
5. Reject an unexpected DB URL/host unless a test explicitly opts into an isolated integration service.
6. Include a test proving that the guard rejects the active database configuration without connecting to or modifying that database.
7. Keep MySQL integration databases explicitly provisioned and isolated.

Guard placement must execute before RefreshDatabase initializes. Its exact bootstrap mechanism remains a technical implementation choice.

## 6. Existing test coverage inventory

These are source-level findings only. No listed test was executed. Tests under the configured PHPUnit directories should be discoverable if bootstrap and setup succeed; actual execution is unverified.

| Workstream | Existing files and representative methods | Existing source coverage | Missing acceptance coverage |
|---|---|---|---|
| Currency snapshots and policy | PaymentTransactionsTest::test_configured_currency_is_snapshotted_and_cannot_reinterpret_existing_amounts; PricingAndPromotionsTest::test_branch_currency_mismatch_is_rejected_and_discount_caps_are_respected | Configured-currency snapshot, stable amount when setting changes, branch-price mismatch. | Migration-20 existing-row provenance for both pending/applied upgrade states; unresolved state; individual/bulk approval; no amount change during resolution; unknown/mixed history. |
| Sales/financial reports | SalesReportingTest branch/company/employee scope and canceled-status cases; PaymentTransactionsTest::test_owner_can_verify_full_cash_payment_and_report_counts_only_verified_collections; PaymentRefundsTest::test_partial_refund_is_reserved_then_completed_by_a_different_manager_and_reported_net | Scope, verified collections, refund netting, status filters. | Currency grouping, resolved/unresolved split, no mixed total, unresolved values not presented as verified currency, historical receipt/export labeling. |
| Customer aggregate | CustomerDashboardTest personal timeline/cancellation/IDOR; Admin/CustomerDirectoryTest business directory scope. | Privacy and directory authorization. | Currency-aware totals, per-currency grouping, unresolved spend separately identified. |
| Payment/refund validation | PaymentTransactionsTest covers verification, partial payment, overpayment, repeated submission, reference uniqueness and branch scope; PaymentRefundsTest covers amount bounds, approval separation, status validation, duplicate request and scope. | Sequential rules, idempotency, audit/event paths and authorization. | Block all payment/refund mutations when currency is unresolved, including internal callers and verification/completion; simultaneous payment/refund races. |
| Owner assignment/authorization | BusinessAccessIsolationTest::test_only_initial_setup_can_assign_the_single_shop_owner; BusinessAccessIsolationTest::test_platform_admin_can_assign_the_first_owner_during_initial_setup_only; CreateAdminCommandTest administrator command cases. | Current one-owner and initial-setup authorization; selected platform/business boundary cases. | Verified customer consent, narrow durable approval, replay protection, two simultaneous first-owner claims, transfer, offline recovery, audit and lost-email recovery. |
| Invitation/onboarding | InvitationOnboardingTest covers invite authorization, expiry/revocation/consumption, role/branch tampering, and interrupted OTP recovery. | Employee/manager/seller invitation lifecycle and boundaries. | First business Owner is distinct; invitation coverage does not establish Owner consent or recovery. |
| Inventory/order | ProductPurchaseTest reservation/rollback/cancellation/idempotency; EmployeeSalesDeskTest POS scope/revalidation/rollback/idempotency; InventoryReconciliationTest return/review/restock constraints. | Sequential inventory invariants, rollback, idempotency. | Two-connection reservation/adjustment/return races, oversell prevention, deadlock/retry and MySQL 8.4 behavior. |
| Fulfilment/cancellation | FulfilmentOperationsTest transition order, duplicate transition, cancellation, role scope and buyer tracking. | Sequential state machine and authorization. | Concurrent transition/cancel/stock-release and lock-order interaction with payment/refund workflows. |
| Pricing/coupons | PricingAndPromotionsTest snapshots, stale quote, per-customer limits, rollback, branch-currency mismatch. | Sequential price freshness, coupon limits and transaction rollback. | Concurrent final/global coupon redemption and lock ordering with order/inventory; MySQL unique/range semantics. |
| Concurrency-specific | Auth/EmailOtpConcurrencyTest.php | Cache lock contention, OTP replay/consumption, delayed delivery. | This is not an SQL/database lock test and proves no commerce row-lock safety. |
| Test isolation | tests/TestCase.php, phpunit.xml, RefreshDatabase usage | PHPUnit intends in-memory SQLite; test classes commonly request database refresh. | Fail-closed pre-migration guard, forced process DB variables, proof the active SQLite path cannot be selected. |

### Regression coverage for prior audit fixes

Existing test files offer partial source-level coverage, not execution evidence:

- **DATA-01:** Payment test covers configured currency snapshot, not ambiguous historical rows or migration provenance.
- **SEC-01 / AUTH-01 / AUTH-02 / AUTH-03:** Admin/business access tests cover selected role/branch boundaries and initial Owner assignment. Missing: consent, durable replay prevention, transfer/recovery, and concurrent transitions.
- **CLI-01:** CreateAdminCommandTest covers new administrator creation, promotion, and invalid-password no-partial-promotion. It does not cover Owner lifecycle or every buyer-operational path for password exposure.
- **DIAG-01:** SystemHealthTest exists and SystemHealthController checks required schema. No result is claimed. Validate actionable missing/outdated-schema diagnostics without exposing secrets or business records.
- **INSTALL-01:** Installation documentation exists, but no fresh buyer install or upgrade was executed in this phase.
- Pint is configured with --test, so it checks formatting rather than rewriting files. No Pint command ran and no formatting-debt claim is made.

## 7. CI blockers and limitations

1. gh is unavailable, so authentication and workflow run history could not be inspected.
2. The workflow has no manual trigger.
3. The local branch is master; push trigger targets main.
4. Uncommitted working-tree changes cannot be represented by a normal Actions checkout of local HEAD.
5. No MySQL/MariaDB service or compatibility job is configured.
6. No fail-closed test DB guard exists.
7. Composer setup mutates its checkout via dependency install, key generation, migration, npm install, and build; this is disposable-runner setup, not safe local test preparation.
8. PHPUnit, Pint, PHPStan, browser checks, fresh install/upgrade checks, and MySQL concurrency tests were not run in D1.

## 8. D2–D5 implementation readiness

The approved policies are recorded in the companion register. This is planning, not approval to begin implementation.

### D2 — Currency schema and provenance

- **Scope:** Additive schema; explicit unresolved/resolved state; append-only reconciliation history; individual Owner approval; dual-approved bulk reconciliation; idempotent batch progress; explicit future-writer currency.
- **Likely files:** New migration(s); CurrencyPolicy; product/listing/order/payment/refund/pricing models and writers; focused tests.
- **Migration implications:** Never edit an applied migration. Migration 2026_11_20_000000_add_currency_snapshots_and_refund_transactions.php adds literal USD defaults to existing orders, pet_listings, and products, based on its comment that historic integer-minor values were USD. A forward migration must handle migration 20 pending and migration 20 already recorded. Preserve the resulting currency code as an unverified legacy/default value when evidence is absent; a default is not proof. Test fresh install and both upgrade states.
- **Acceptance:** Amount values stay identical; ambiguous records remain unresolved; no migration infers or converts; resolutions are audited, retry-safe, and amount-neutral; new writers explicitly snapshot configured currency.
- **Preservation/recovery:** Verify a buyer backup before schema rollout. Use additive migration and forward correction; do not rewrite amounts or original evidence. Exact schema remains open.

### D3 — Currency enforcement and reporting

- **Scope:** Block unresolved currency in checkout/POS and all payment/refund mutation services; partition dashboards, customer totals, sales reports, receipts, email documents, and exports by currency and resolution.
- **Likely files:** PricingService, PaymentTransactionService, PaymentRefundService, SalesReportingService, dashboard/customer controllers, affected views, tests.
- **Dependency:** D2 resolution-state contract.
- **Acceptance:** All mutating entry points fail closed; verified history is unchanged; reports do not mix currencies and identify unresolved records; documents do not label ambiguous values using current settings.
- **Preservation:** Historical records remain readable with explicit status. Do not mask failures by returning empty totals. Preserve verified single-currency behavior.

### D4 — First Owner lifecycle

- **Scope:** Durable setup state; verified consent and narrow one-time approval; atomic first assignment; one active Owner; explicit transfer; offline operator recovery with proof/audit; documentation.
- **Likely files:** Business membership/access services, setup persistence, routes/controllers/policies, audit service/model, installation guide, focused tests.
- **Dependency:** Approved D8–D13 and source-verified current assignment in BusinessMembershipService and BusinessAccessService.
- **Acceptance:** No Owner without approved consent/setup path; marker closes atomically; races cannot create a second Owner; Platform Admin role alone cannot grant business ownership; transfer requires consent/reauthentication and audit; recovery is not publicly claimable and follows documented proof.
- **Preservation:** Do not manually change accounts/memberships. Conservatively initialize new state for existing memberships; recovery is audited and forward-only.

### D5 — Locking and concurrency

- **Scope:** Documented lock order and service-owned transactions for membership, inventory, orders, POS, payments/refunds, returns, fulfilment, and coupon redemption; preserve idempotency; bounded deadlock retry.
- **Likely files:** BusinessMembershipService, BusinessAccessService, InventoryMovementService, EmployeeSalesDeskService, PaymentTransactionService, PaymentRefundService, ProductReturnService, FulfilmentService, PricingService, relevant controllers and concurrency tests.
- **Dependency:** Fail-closed test DB guard and isolated MySQL 8.4 integration environment. MariaDB 11.4 remains a candidate until tested.
- **Acceptance:** Separate DB connections with controlled barriers prove no oversell, duplicate collection/refund, double restock, duplicate coupon redemption, duplicate Owner, or invalid transition; retries are bounded and do not repeat external effects; sequential behavior remains.
- **Preservation:** Retain unique constraints and idempotency keys. Keep external effects outside retried transactions. Implement in small workflow-specific changes.

## 9. Recommended database and concurrency test plan

### Engine and installation matrix

1. **SQLite in-memory:** Fast functional/unit coverage only after a fail-closed test guard; it does not prove MySQL row locking.
2. **MySQL 8.4 LTS:** First production target. Test fresh migration, supported upgrade fixture, full feature suite, FK/unique/generated-column/trigger behavior, and multi-connection concurrency.
3. **MariaDB 11.4:** Candidate only. Run the same migration, feature, constraint, and concurrency matrix before any support claim.
4. Keep all test databases disposable and uniquely named; never use active database/database.sqlite.

These are product target decisions, not evidence that repository compatibility has passed.

### Concurrency scenarios

Use independent connections/processes with controlled barriers; record engine/version, isolation level, SQLSTATE, and final invariant values. Correctness assertions should not depend on timing-only sleeps.

- Competing online/POS checkout for last inventory units.
- Reservation versus cancellation/release; return approval versus stock adjustment.
- Duplicate submission-key replay and payload-key reuse.
- Simultaneous partial payments and verification.
- Competing refund requests/completions against remaining verified balance.
- Final coupon/global usage slot and per-customer limit.
- Concurrent fulfilment transition/cancellation and stock release.
- Two first-Owner claims; Owner transfer versus membership deactivation.
- Last Platform Admin guard where applicable.
- Inject a deadlock/retryable failure and prove bounded retry, no repeated external effects, and stable audit/idempotency records.

Run these on MySQL 8.4 before claiming concurrency correctness; run required compatibility cases on MariaDB 11.4 before advertising it.

## 10. Recommended next action

Review this baseline and the approved decision register. Before D2, authorize a bounded change set and resolve engineering details that materially affect schema or approval semantics. First establish and validate the fail-closed test database guard on an isolated Linux CI path; then implement D2 as a separate additive migration/reconciliation workstream. Do not combine D2, D4, and D5 into one deployment.

To test current modifications on hosted Actions, obtain an authorized mechanism for an exact source snapshot and record the commit/run identity. Current D1 restrictions prohibit producing that snapshot through Git operations.

## 11. Git status after documentation

The final read-only Git status check is reported in the completion message. Expected additions from this phase are limited to:

- docs/architecture/PRODUCT_OWNER_DECISIONS_2026-11-18.md
- docs/audit/PHASE_D1_LINUX_CI_BASELINE_2026-11-18.md

Earlier tracked, staged, and untracked work must remain preserved.

