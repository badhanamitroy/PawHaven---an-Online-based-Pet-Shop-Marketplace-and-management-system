# PawHeaven Stage C Architecture Decisions

**Date:** 2026-11-17
**Status:** Design only; no application, migration, database, configuration, dependency, or existing report changes made.
**Repository:** D:\PawHaven\PH Web Version\WebApp

## 1. Executive findings

Three material risks are confirmed by the current source:

1. Migration 20 assigns a literal USD currency default to products, pet listings, and orders. The migration's own comment says older amounts were USD, but earlier application settings accepted arbitrary currency codes. For a genuine non-USD installation, values can therefore be mislabeled without conversion. The code has no provenance that distinguishes migration-backfilled USD from a valid later USD snapshot when migration 20 is already applied.
2. Sales reporting, the admin dashboard, and customer spend totals can aggregate monetary rows without grouping by currency; some views label the result using one selected or current installation currency. These outputs can combine unlike amounts. Per-order payment and refund calculations generally validate currency relationships, but that does not repair legacy roots or protect cross-order reporting.
3. Several transaction paths acquire related rows in conflicting orders. Confirmed examples include business membership versus company locks, and product locks before authorization versus authorization before product. On MySQL/MariaDB these are concrete deadlock candidates. SQLite tests do not establish row-lock behavior.

First Owner provisioning is deliberately separated from neutral company creation. The current installation flow uses a console-provisioned Platform Admin to assign the Owner membership to a verified customer. It blocks ordinary customers from claiming directly and keeps Platform Admin business data access separate. The setup-complete state is nevertheless inferred from the active Owner row, with no durable one-time completion marker, transfer, or ownerless-company recovery workflow.

There is an existing suitable test runner path in .github/workflows/tests.yml: an Ubuntu GitHub Actions job uses PHP 8.3 and Composer. This is a plausible way to run tests without changing the affected Windows runtime, but this workflow was not executed during this design stage. Laravel 13 and PHPUnit 12.5 support PHP 8.3; that does not establish application-level MySQL/MariaDB support.

Recommended direction:

- Treat currency value and currency confidence as separate data. Keep amounts unchanged, add durable unresolved/verified state, record reconciliation as auditable events, and never total unresolved or mixed currencies.
- Keep installation administration, platform administration, and company ownership as separate authority domains. Add a durable per-company owner setup state and explicit, consented one-time claim, transfer, and offline recovery flows.
- Standardize lock order around company authorization scope and aggregate roots; use deterministic ordering for row sets; move authorization locks before order/product writes; keep unique idempotency constraints and retry full transactions safely.
- Use isolated CI databases and add MySQL/MariaDB integration and multi-connection concurrency jobs before advertising engine support.

The correct previous audit filename is docs/audit/COMMERCIAL_READINESS_AUDIT_2026-11-17.md. The alternate PAWHAVEN_COMMERCIAL_READINESS_AUDIT_2026-11-17.md path in the request is not present.

## 2. Confirmed facts and assumptions

### Confirmed from current repository

- composer.json requires PHP ^8.3 and Laravel ^13.17. phpunit.xml configures the SQLite :memory: test database; PHPUnit XML values are not forced over process environment by default.
- .github/workflows/tests.yml uses an Ubuntu runner, PHP 8.3, Composer, composer setup, and composer ci:check. The workflow is present; no result from this run was obtained here.
- config/database.php includes sqlite, mysql, and mariadb connection entries. docs/INSTALLATION.md lists SQLite 3.35+ and MySQL 8.0+, says MySQL still needs deployment-specific validation, and does not list MariaDB as a supported production target.
- Migration 20 adds currency CHAR(3) DEFAULT 'USD' to products, pet_listings, and orders. Its comment states the former amounts were USD. It adds new payment-refund tables; payment transactions are created in migration 19 with their own USD default.
- The Stage B investigation documented that the prior settings controller accepted arbitrary currency codes. Current CurrencyPolicy restricts new settings to USD, BDT, CAD, or EUR and prevents changing settings when stored codes differ, but cannot identify rows whose code was defaulted incorrectly.
- Migration 23 adds independent currency and/or amount fields for branch prices, promotions, coupons, discount redemptions, order totals, and order-item price/discount snapshots.
- Current code has model/service currency snapshots for ordinary new Product, PetListing, Order, payment, and refund writes. PricingService rejects mixed-currency carts and checks branch-price/promotion/coupon currency.
- The Admin dashboard and customer directory aggregate order minor units without grouping on currency. SalesReportingService exposes a single currency in its summary while its amount aggregates can span all selected order currencies. Payment totals and refund limits inside service operations are generally scoped to an individual order/payment.
- The installation guide already has a currency upgrade gate, docs/INSTALLATION.md around lines 241-243, telling buyers with existing monetary rows to stop before applying the USD-default migration when shop currency is not USD. This is an operational warning, not a schema representation of uncertainty, and it does not help an installation where migration 20 is already recorded as applied.
- Migration 15 enforces a single company per installation using a generated constant column and unique index, and BusinessAccessService::isInitialSetupAdmin() rejects the setup-admin path if company count is not exactly one. Multi-company-per-installation is therefore not a current supported topology.
- Current migration status was 24 applied in the previous read-only stage. No migrations or database writes were performed in Stage C.
- Current Git status before this document: 102 changed tracked entries, 80 untracked entries, 0 staged entries. The existing work was preserved.

### Assumptions and unverified items

- Actual non-USD legacy records may exist in buyer installations, but the local database inspected in Stage B has no monetary business rows; buyer data distribution is unknown.
- Production MySQL/MariaDB version behavior, trigger compatibility, DDL behavior, and concurrency outcomes are not verified by code inspection.
- The exact Windows/PHP cause of is_readable() failure is unresolved. Stage B evidence showed PHP 8.5.0 returned false for existing readable files while file_get_contents() worked; PHPUnit was not executed.
- The GitHub workflow is configured to run tests, but this review did not inspect a current successful CI run or execute tests.
- No line-level claim below implies runtime or browser verification.

## 3. Design A — Historical currency integrity and reconciliation

### 3.1 Money-bearing tables and fields

| Table / model | Monetary values and currency representation | Reconciliation treatment |
|---|---|---|
| products / Product | price_minor, sale_price_minor, currency. Currency added by migration 20. | Product prices are current sellable values. Keep values intact; prevent publication/sale while unresolved. |
| pet_listings / PetListing | nullable price_minor, currency. Currency added by migration 20. | Keep values intact; hide price as unresolved and block paid checkout while unresolved. Free/adoption listings need explicit policy if price is null. |
| orders / Order | subtotal_minor, delivery_fee_minor, total_minor, currency; migration 23 also adds promotion_discount_minor, coupon_discount_minor, and pricing_snapshot JSON. | Order is the historical financial root. Unresolved order currency flows to its items, payment, refund, and redemption views. Preserve values and original snapshot bytes. |
| order_items / OrderItem | Existing price_minor, quantity, subtotal_minor; migration 23 adds base_price_minor, resolved_price_minor, promotion_discount_minor, coupon_discount_minor, plus promotion_id/coupon_id. No separate currency column. | Inherit the parent order currency. Preserve the original item amounts and snapshot. Do not infer from a linked product's current price. |
| payment_transactions / PaymentTransaction | amount_minor, currency, status. Migration 19 creates the table with currency default USD. | For existing rows at reconciliation cutover, follow linked order currency confidence; report unresolved where parent is unresolved or inconsistent. |
| payment_refund_transactions / PaymentRefundTransaction | amount_minor, currency, status and reason. Migration 20 creates this new table; currency is required, with no USD default. | Currency must match the payment and order. Do not approve or report a verified refund for an unresolved parent. |
| branch_product_prices / BranchProductPrice | price_minor, currency. Added in migration 23. | Independently stored current price; require a resolved product and matching currency before use. |
| promotions / Promotion | discount_value; minimum_purchase_minor; maximum_discount_minor; currency. For fixed discount_type, discount_value is an amount; for percent type, it is a percentage. | Reconcile/verify currency on money-valued campaigns; percentage itself is not a currency amount. |
| coupons / Coupon | discount_value; minimum_purchase_minor; maximum_discount_minor; currency. Same fixed-versus-percent distinction as Promotion. | Same as promotions; currency must match the applied cart/order. |
| discount_redemptions / DiscountRedemption | promotion_discount_minor, coupon_discount_minor, currency, plus order and campaign references. | Inherit the order currency and validate against the order snapshot. |
| inventory_movements / product_returns | Quantities, state, actor and references; no monetary valuation amount was found in the current schema. | Not part of this currency backfill. Refund amounts remain in payment/refund records. |

Original product/order monetary schema references are database/migrations/2026_10_29_020100_create_pet_listings_and_related_tables.php and database/migrations/2026_10_29_030100_create_shop_and_order_tables.php. Currency and refund additions are in database/migrations/2026_11_19_000000_create_payment_transactions_table.php and database/migrations/2026_11_20_000000_add_currency_snapshots_and_refund_transactions.php. Later pricing columns are in database/migrations/2026_11_23_000000_create_pricing_and_promotion_tables.php.

### 3.2 How migration 20 mislabels historical values

In migration 20, lines 11-20, the migration adds a non-null currency column with DEFAULT 'USD' to each pre-existing products, pet_listings, and orders table. Relational databases populate existing rows with that default when the column is added. There is no amount conversion and no check against the setting in effect when each record was created. Consequently:

- An existing 12,500 minor-unit BDT product can become a row with 12,500 and currency USD; the numeric value is preserved but the denomination is wrong.
- A database-wide current shop setting is not evidence that all historical products, listings, and orders shared one code. Settings could have changed over time.
- The same schema default will apply to future inserts that omit currency, including raw SQL/import paths. Current Eloquent hooks supply currency in normal flows, but the database default remains a silent fallback.
- Migration 19's payment_transactions.currency DEFAULT USD is on a newly created table, so normal migration history does not backfill older payment rows; it can still mislabel a future omitted/direct payment insert.
- Migration 20's refund table is new and requires an explicit currency, but its service must still enforce matching order/payment currency.
- Existing installations that already ran migration 20 cannot use the migration's presence or current currency column alone to distinguish a backfilled USD value from a legitimate later USD snapshot.

The migration must not be edited after it may have run. The current install guide's stop gate helps an operator who has not yet run migration 20, but a product design must handle both pending and already-applied installations.

### 3.3 Aggregations and comparisons at risk

A repository search for monetary SUM/sum calls found these cross-record/report boundaries:

| Location | Current behavior | Design correction |
|---|---|---|
| app/Services/SalesReportingService.php, summary() around lines 95-149 | Chooses one code using the first order's currency or current setting, then sums order totals, payment amounts, refunds and outstanding balances over the selected order set. | Query/return summaries grouped by currency and resolution state; never expose a single mixed-currency summary object. |
| SalesReportingService employeeBreakdown(), branchBreakdown(), and channel/attribution report methods around lines 159-224 | Aggregate order values by business dimension, without currency as a grouping key. Their views label totals with summary currency. | Add currency and resolution state to every group key; present separate currency rows. |
| app/Http/Controllers/Admin/DashboardController.php, index() around line 63; resources/views/admin/dashboard.blade.php around line 121 | Sums delivered order total_minor then labels it using current shop.currency. | Group by order currency and status; unresolved totals excluded from verified totals and separately counted. |
| app/Http/Controllers/Admin/CustomerController.php, index() around lines 38-44; resources/views/admin/customers/index.blade.php around line 90 | Sums customer order total_minor by user only, then labels with current shop.currency. | Return per-customer/per-currency spend; never one lifetime total over different codes. |
| Sales-report partials under resources/views/admin/sales-reports | Summary cards use one summary currency, while order/payment rows may show each snapshot currency. | All cards and breakdown rows must consume grouped data. Unresolved entries must display an unresolved state, not the stored candidate code. |

Per-order comparisons are different: PaymentTransactionService totalsForOrder()/record()/verify() and PaymentRefundService request()/approve()/reject() aggregate payments/refunds by order or payment and check currency agreement in the normal flow. PricingService::quote() rejects mixed-currency products in a cart and verifies branch price/campaign/coupon currencies. These are useful invariants to preserve, but each must reject unresolved source records too.

InventoryReportingService aggregates quantities only. Dashboard inventory count is stock quantity, not money. No financial inventory valuation field/query was identified.

Order/payment/receipt/email views display a stored code. A bad USD snapshot therefore can also misstate individual history even before aggregation. Mark unresolved history explicitly in customer, staff, receipt, export, and email boundaries. The current inspection found HTML views/receipts and reports; no dedicated financial PDF/export implementation was identified.

### 3.4 Resolution data model

The practical design separates three things: immutable amount history, recorded currency text, and authoritative currency resolution.

Proposed schema and lifecycle:

1. Add a resolution state to each independently priced root: product, pet_listing, order, payment transaction, branch product price, promotion, coupon, and discount redemption. Use an explicit enum-like value such as unresolved, verified. Do not make a database default silently imply verified. Related order_items and refunds inherit/validate against their parent chain; add their own state only if product requirements require standalone reporting independent of the parent.
2. Preserve current amount columns exactly. Keep the currency value that existed at cutover as recorded_currency_before_review for audit reference. Add resolved_currency as nullable or treat the existing currency column as authoritative only when state=verified. Consumers must never display the candidate code when state=unresolved.
3. Keep a normalized reconciliation batch and append-only event history. A batch records actor, timestamp, scope, reason, approval and completion state. Each event records entity/table and key, prior recorded code/state, resolved code, evidence category/reference, evidence hash or protected evidence pointer, approver, and superseding event ID. Do not put secrets or unnecessary customer documents in ordinary application logs.
4. Keep event history immutable through application permissions and service APIs. Corrections create a compensating/superseding event that points to the prior event, includes evidence and reason, and changes the current resolution projection in the same transaction. Do not delete the prior record/event.
5. For record-level mapping, require explicit IDs and validated entity relationships. For group mapping, require an explicit reviewed manifest/predicate, show a count and sample before commit, store the exact selected IDs or deterministic manifest/hash, and allow exceptions. A group label alone is insufficient.
6. Validate cross-record invariants: payment currency equals order currency; refund currency equals payment and order; redemption currency equals order; order items inherit order currency; branch price/promotion/coupon must match the applicable product/order currency.
7. Record currency correction does not change amount. FX conversion is out of scope for reconciliation. If ever approved, post a separate conversion/adjustment transaction with source and target codes, rate, source/date, rounding rule, actor and approver. Preserve the pre-conversion amount and record.
8. For strong referential integrity, favor a typed resolution table or typed association rows with foreign keys where practical. A polymorphic subject_type/subject_id audit ledger does not have a database-enforced FK; if chosen for minimal schema breadth, the reconciliation service must validate and lock the target, and tests must cover orphan prevention. This is a product/schema tradeoff, not an existing implementation.

A new status field is a schema requirement, but should be added by a new forward migration. The existing migration files remain immutable. The exact shape (columns on each table versus a resolution registry with type-specific constraints) should be settled with the migration test prototype before implementation.

### 3.5 Upgrade strategies and buyer scenarios

**New installation with no monetary history.** The schema installs with no legacy rows to classify. New products/listings/orders/payments/campaigns are created only through validated writers that set currency and resolution state explicitly. No reconciliation screen is required for an empty installation. A fresh-install test must prove no USD default is used as evidence of resolution.

**Existing installation with confirmed USD history.** After a verified backup, the schema upgrade preserves all amounts and recorded codes. The buyer supplies evidence and approves a whole-installation USD mapping only if the manifest shows the exact in-scope records and no known currency changes. The application records the event and marks those records verified USD. No amount changes.

**Existing installation with confirmed BDT history.** The buyer maps the specific records/group to BDT based on evidence. Amounts remain byte/value-identical; there is no conversion. New operations must use BDT only after the corresponding records/business setting are consistent. Current CurrencyPolicy permits BDT but only a single active installation currency at a time, so this remains a single-currency selling model, not a multi-currency checkout design.

**Installation with mixed historical currencies.** Buyer evidence is grouped by exact record cohorts or individual records; exceptions are explicit. Every report is grouped by currency and unresolved status. Product/listing checkout is blocked per unresolved record, and orders/payments/refunds cannot be represented as verified until parent currency is resolved. Never select one currency for the installation and overwrite all history.

**Incomplete/unavailable evidence.** Preserve amounts and recorded code, keep status unresolved, provide read-only historical views with a clear warning, exclude from verified financial reports, and do not sell an unresolved catalog item. Do not guess or convert. Product owner must decide whether an order with unresolved currency permits manual payment/refund operations; default safe recommendation is to require separate evidence/review before any financial mutation.

**Migration 20 pending.** Do not ask an automated migration to guess. Separate structural additions from reconciliation. Add resolution fields as unresolved for all money rows existing at cutover and keep their numeric values and pre-review code. Only records created after the application begins explicit currency writes can be marked verified. The installation guide's present stop gate should become a supported schema-upgrade-plus-review workflow, not the only protection.

**Migration 20 already applied.** Add the same resolution mechanism in a later migration. Because provenance is lost, current USD cannot be trusted. Conservatively mark pre-cutover records in the affected roots unresolved, including rows that may in fact have been valid USD; buyer evidence can verify them without changing amount/code. This extra review burden is the only safe generic behavior absent row-level provenance. Do not try to infer affected rows from current settings, timestamps, geography, locale, amount, category or a supposed migration-created value. Existing correctly snapshotted code and amounts remain unchanged; only confidence metadata changes pending review.

**Critical cutover detail:** all future writers must explicitly set currency and resolution status before the migration makes unresolved status operational. If any raw/import route continues to rely on USD defaults, its records must be unresolved by default and rejected from verified workflows. Remove defaults only in a later compatibility migration after every writer and import contract is explicit.

## 4. Design B — First Owner provisioning, recovery and transfer

### 4.1 Exact current fresh-install path

- The company/branch migration creates a neutral installation company and backfills company references. Migration comments say legacy staff roles do not distinguish Owner/Manager/Seller, so no staff member is guessed as Owner.
- Migration 15 changes ambiguous legacy memberships to inactive/unresolved and enforces unique active assignment and one active company-wide Owner. It also adds companies.installation_guard with constant value and uniqueness, restricting one company per installation.
- docs/INSTALLATION.md says the buyer runs php artisan admin:create, signs in through the verified Platform Admin path, then assigns the Owner role to a verified customer in Admin → Business Management. The Platform Admin does not thereby gain business access.
- routes/admin.php puts the business routes behind auth and verified middleware. BusinessAccessController::storeMembership validates company, user, role, target and Gate permission. BusinessMembershipService::assignForCompany locks and rechecks the company, validates a verified customer with no marketplace/business history for the initial case, then changes that user's legacy role to staff and creates the Owner membership.
- BusinessAccessService::isInitialSetupAdmin() is true only for a Platform Admin while there is no active Owner and company count is zero or exactly one. BusinessMembershipPolicy and BusinessAccessService retain the separation between platform admin and company membership.
- Current state is therefore a controlled admin assignment, not a public Owner claim. There is no durable setup-completed marker. If an ownerless condition is produced by an exceptional/manual change, the absence-based initial setup test can re-open the path.
- BusinessMembershipService::deactivate() rejects Owner removal. No Owner transfer/replacement/recovery operation was found.
- Existing password reset and two-factor routes cover account recovery where the user's registered email or configured second factor remains usable. They do not provide an Owner recovery process when the email is inaccessible.

### 4.2 Recommended lifecycle and authority model

Keep three separate roles:

- **Installation Administrator:** trusted operator who has console/server access and can provision the initial platform administrator or invoke a documented offline recovery command.
- **Platform Admin:** operates platform-level user and installation administration. Does not receive private company records or business Owner authority by default.
- **Company Owner:** receives company-wide business authority through an active Owner membership. This authority is explicit, company-scoped, and audited.

#### First Owner

1. Install and migrate after backup using the buyer's documented console workflow. No unauthenticated public installer or web claim endpoint grants owner authority.
2. Provision the Platform Admin through admin:create; the command already accepts the password through a hidden console prompt. Never pass credentials in process arguments, URLs, logs, or shell history.
3. The intended owner registers and verifies the account, then explicitly initiates an Owner claim for the installation company (or accepts a one-time, expiring Owner setup invitation bound to that company). This proves account consent and avoids granting a customer's identity without their action.
4. A Platform Admin approves the initial claim only after checking the buyer's intended ownership. Approval is explicit and the target remains a distinct customer account; do not silently turn the Platform Admin into an Owner.
5. In one transaction lock the company setup state, company, any applicable branch, target user, and relevant active membership rows in canonical order. Recheck unclaimed state, verified/eligible target, claim token expiry/one-use, and no active Owner. Create membership, append audit event and transition durable setup state to claimed/completed atomically.
6. A unique active-owner database constraint remains the final concurrent-claim guard. If two approvals race, one commits; the other returns a safe already-completed response after re-reading state. Do not leave a customer half-promoted to staff if membership creation fails.

A candidate using the currently registered verified customer account is the least disruptive path. An invitation-only flow may be preferable for buyers who want the administrator to designate the exact Owner before account registration; it must require recipient acceptance and expiry/revocation.

#### Durable state and replay protection

Add a company-scoped setup state with explicit states such as unclaimed, claimed, recovery_pending, and recovered. Store completed_at, actor, and transition reason/reference. Lock this row (or the company row if state remains there) before checking it. A unique active Owner index protects membership cardinality; the durable state prevents re-opening initial setup simply because an active membership disappears. The state and membership must commit together. All state-changing paths must update the same serialized state.

#### Transfer

- Require authenticated current Owner approval with recent password/MFA reauthentication, verified recipient consent, and an explicit company/branch scope.
- Lock company setup state, old/new membership rows ordered by ID, then target user rows ordered by ID.
- Inside a transaction deactivate/replace memberships and write a before/after audit event. Respect the current one-active-Owner constraint. Update the marker without an interval visible outside the transaction where the company has no Owner.
- Preserve the old membership and audit event as history; do not delete users or historical records.
- Use optimistic presentation and server-side state rechecks; no authorization decision relies on a hidden button.

#### Recovery when the Owner account/email is inaccessible

- Do not make a public “claim owner” route available just because an Owner cannot log in.
- First use normal password reset only when the account's email channel is controlled. If the email is inaccessible, require a trusted deployment operator to invoke an offline console recovery after verifying buyer/company control outside the app (for example, hosting account control plus purchase/support identity and an explicit record of the request).
- The command must require explicit company and target IDs, a hidden operator confirmation, a reason/reference, a verified recipient account, and recent identity verification. For a commercial hosted support recovery, require a second approver or documented support escalation. It must write an immutable event, invalidate pending Owner claims, notify old and new contacts where possible, and never expose or change the target password.
- If the business can prove only control of the server, the operator may create a new Platform Admin via console, but Owner recovery still requires a separately audited company-ownership verification step. Platform Admin status is not evidence of ownership.

#### Multiple companies and Platform Admin attempt

- Today a unique constant generated column in migration 15 enforces one company in an installation, and the initial setup check returns false for more than one company. There is no supported multi-company installation scenario to implement without a deliberate architecture decision.
- If product requirements later allow multiple companies, state and setup locks must be per company; each company gets its own candidate consent, completion state, owner uniqueness, audit history, recovery and transfer. One company's Owner cannot claim another company's business. Migration and policy changes must remove the single-installation guard only with explicit product approval and multi-company isolation tests.
- If a Platform Admin attempts to claim Owner through the current path, their role does not satisfy the initial target's isCustomer() eligibility; the system must reject rather than convert platform authority into business authority. The future claim flow should also explicitly forbid the current acting Platform Admin as target unless the product owner separately approves an account model that can hold both roles. Recommended policy is one identity per authority domain.

### 4.3 Required audit events

Record company ID, old/new membership ID and user IDs, operation type, actor, time, reason, approval method/reference, request/correlation ID, setup-state before/after, and whether an offline recovery path was used. Restrict read access to platform audit operators and the company's authorized business administrators as product policy dictates. Do not store passwords, reset tokens, OTP values, session IDs, or raw recovery documents in ordinary audit logs.

## 5. Design C — Database locking and concurrency

### 5.1 Current lock acquisition map

| Workflow and source | Current observed acquisition order | Keys / idempotency | Risk |
|---|---|---|---|
| Membership invite acceptance and assignment — BusinessMembershipService | Invitation assignment locks Company then Invitation. Normal assignment locks Company, target User, optional Branch, then active membership rows sorted by id. | Invitation status and active-membership uniqueness constraints. | Assignment order differs from POS/authorization helpers; Company row is a possible serialization point. |
| Membership deactivation — BusinessMembershipService::deactivate() | Company then BusinessMembership. | Owner deactivation refused; active-member uniqueness. | Opposes Fulfilment/payment/inventory authorization helpers that can lock membership before Company. |
| Initial Owner assignment — BusinessMembershipService::assignForCompany() | Company then target User, then optional Branch and active memberships ordered by id. | Unique active Owner guard in migration 15. | Durable marker absent; concurrency relies on Company lock and unique constraint. |
| Online checkout — OrderRequestController | Transaction first checks online submission key; locks product and company/branch context; creates order and item; nested InventoryMovementService reservation locks Order, OrderItem, Product. | online_submission_key unique; stock movement reference is idempotent. | Nested service adds locks after outer transaction already holds Product/scope. Document and normalize this path. |
| POS checkout — EmployeeSalesDeskService | Locks active Membership, Company, Branch, then Products in ascending ID. PricingService then locks product/branch-price/promotion/coupon rows; creates order/items; nested reserve locks Order, OrderItem, Product; optional payment follows. | pos_submission_key unique; payment submission key and coupon/order redemption constraints. | Membership->Company opposes Company->Membership. Nested reserve has order/item/product after product rows already locked. |
| Manual inventory adjustment — InventoryController and ProductController -> InventoryMovementService::adjustToQuantity() | Controller transaction locks Product; nested service rechecks authorization/active membership/company and locks Product again. | Unique manual reference key; movement rows record changes. | Product is held while service asks for Membership/Company, opposite of business-authorization-first paths. Nested transaction does not release controller-held locks. |
| Order reservation/release — InventoryMovementService | Reserve locks Order -> OrderItem -> Product. Release iterates OrderItems by id and locks/updates Products through nested movement logic. | Inventory movement reference key plus order reservation timestamp/state. | Must keep the Order aggregate lock and sorted item/product order; check multi-product same-order operations. |
| Payment recording/verification — PaymentTransactionService | Existing order lock then PaymentTransaction for verify; record path locks Order before summing/inserting transaction; authorization helper separately locks membership/company. | submission_key and reference_hash unique; amount bounded to order totals. | If a path locks Order/Payment before membership/company while another locks Company/Membership first, there is a lock-order inversion. |
| Refund request/completion — PaymentRefundService | Request: Order -> PaymentTransaction; completion/rejection: Order -> PaymentTransaction -> Refund row; sensitive authorization can lock membership/company through helper. | submission_key and order/payment/refund bounds. | Current order/payment locks can precede membership/company checks; reverse order to common scope-first pattern. |
| Return request/review/receipt — ProductReturnService | Request: Order -> OrderItem -> Product; review/receipt: Order -> ProductReturn -> OrderItem -> Product. Reviewer authorization locks Membership/Company/Branch as helper. | return submission keys and idempotent receive status; stock movement reference. | Child lock order differs between request and receive, and business membership locks may come after Order. |
| Fulfilment/cancellation — FulfilmentService | Transition locks Order then calls business authorization which reads/locks Membership -> Company -> Branch. Packing locks OrderItems ordered by id then Products. Buyer cancellation locks Order and inventory children. | unique fulfilment event submission_key; state transition rechecks. | Confirmed membership->company inversion versus membership deactivation's company->membership. Order is locked before authorization. |
| Pricing/coupons — PricingService | Locks Products by id, BranchProductPrice rows, applicable Promotions ordered priority/id, then Coupon; counts redemptions with locking query. | One discount_redemption per order, coupon code and usage limits. | Ensure usage-limit count is a current locking read on target DB/isolation. Product ordering is deterministic; promotion business editing and checkout row orders need audit. |
| Last Platform Admin role — Admin/UserController | Locks active Admin users ordered by id then target User. | Application guard protects last Admin. | Concurrent set membership changes/phantoms and locking an Admin set need two-session testing. |

Key code references: OrderRequestController transaction at 82-209; EmployeeSalesDeskService at 92-230; InventoryMovementService reserve at 44-95 and adjust at 119-180; ProductController at 265-274; InventoryController at 78-85; FulfilmentService at 47-103, 410-470; PaymentTransactionService at 80-171 and 281-351; PaymentRefundService at 49-106 and 122-220; ProductReturnService at 30-210 and its authorization helpers; PricingService at 57-176 and 291-301; BusinessMembershipService at 34-240; Admin/UserController at 57-75.

### 5.2 Proposed global lock convention

Use one lock protocol documented near the transaction services and reviewed as part of every multi-row write:

1. **Resolve identities without locks.** Read immutable IDs needed to identify the company, branch, membership, order, payment, product set, and target. Treat this as preliminary only.
2. **Business authorization scope first:** lock Company rows by ascending company ID when lifecycle/active state is relevant; then Branch rows by ascending ID; then BusinessMembership rows by ascending ID; then User rows by ascending ID when roles/account state are being changed. Re-read and verify relationships and active state after locking. Any operation that changes membership, branch, or company state locks the same rows in the same order.
3. **Order aggregate root:** for an existing order, lock Order before mutable order children. Re-read and revalidate buyer/company/branch/status/currency after the lock.
4. **Order children:** lock OrderItem rows by ascending primary key; then PaymentTransaction rows by ascending ID; then Refund/Return rows in a documented fixed table order and ascending ID; then Product rows by ascending product ID; then append-only event and movement rows. A concrete table-child order should be shared by all services; no service may acquire a higher-ranked class after a lower-ranked one.
5. **Checkout pricing:** after scope, lock Products sorted by ID, BranchProductPrice rows in product/branch order, Promotions by priority then ID, and Coupon by ID. Check stock/price/usage under those locks. Create a new order and items after the relevant input records are locked. The new order is not visible to competing transactions before commit; when the inventory service reacquires the just-created order it must follow the documented checkout exception and not call into a path that can deadlock against an existing order.
6. **Skip unnecessary locks.** Read-only records and unrelated scopes do not need locks. For business operations that depend on Company/Branch active status, acquire that scope before the order/product lock. Buyer-only operations may lock the Order root and then its children, but must not later acquire Company/Membership in reverse order. If authorization needs membership, move it before Order.
7. **Deterministic multi-row locking:** sort every ID set before locking; never rely on unspecified query order. Add/verify supporting indexes so lock predicates scan narrow ranges.
8. **Authorization must be checked after lock acquisition.** A preliminary gate is useful for early rejection but cannot replace rechecking the locked membership/company/branch, role, ownership, status, and row relationships.

This protocol preserves current business rules while changing acquisition order. It can increase contention if every operation locks the Company row. For ordinary operations, the minimum rows needed to serialize deactivation/authorization may be Company state only when it is mutable, and the exact membership/branch row; measure throughput after correctness. Owner setup and transfer deliberately serialize on the company setup row.

### 5.3 Inconsistent paths to fix first

- Company -> Membership in BusinessMembershipService::deactivate() conflicts with Membership -> Company in FulfilmentService::authorizeBusinessAction(), PaymentTransactionService membership helper, PaymentRefundService::lockedMembership(), ProductReturnService authorization helpers, and inventory authorization. Choose Company -> Branch -> Membership and move helpers to it.
- Product -> authorization/company in ProductController and InventoryController conflicts with the service authorization-first path. Make InventoryMovementService own the transaction and lock/auth sequence; controllers should pass target product IDs and never pre-lock the product.
- Order -> Membership/Company in fulfilment, payment/refund, and return authorization conflicts with scope-first business operations. Read required IDs before the transaction, lock scope rows first, then lock and revalidate Order.
- ProductReturnService::request() locks order item then product, while receive locks return then item then product. Select one canonical child order and use it for request, review, and receipt.
- Online and POS checkout call inventory reservation inside an existing transaction after creating order/item and after product stock was locked. Audit nested calls explicitly, centralize checkout in one service boundary, and ensure the nested helper does not create a second contradictory lock plan.
- Locking a query by a set of existing Admin rows does not necessarily serialize a concurrent insert/promote affecting the set. Consider a single durable role-guard row or another constraint-backed serialization point for last-Admin invariant; do not rely only on current-row-set locking.

### 5.4 Transactions, retries and idempotency

- Keep state transitions, stock movements, redemption, order/payment/refund records, and audit events within one DB transaction.
- Retry only recognized deadlock/serialization failures by rerunning the entire transaction. Laravel DB::transaction attempts may be used where configured, but inspect every workflow; current code applies retries inconsistently. Do not retry validation, authorization, currency, or unique-submission conflicts as though they were transient.
- No mail, webhooks, external payment calls, file deletion, or other non-transactional side effect should execute inside a retryable callback. Dispatch after commit and use an outbox/idempotency key when delivery must be guaranteed.
- Database unique keys remain the final arbiter for simultaneous duplicate submissions. On duplicate key, re-read the existing idempotency row and compare the stored payload hash; return the original result only for an identical operation. Otherwise return a conflict.
- Preserve/check keys already present: online_submission_key, pos_submission_key, payment/refund submission keys, payment reference_hash, fulfilment submission_key, inventory reference_key, product-return submission keys, and unique discount_redemptions.order_id.
- Bound retries and provide safe user-facing failure for exhausted deadlocks/timeouts. Record diagnostic correlation/operation metadata without logging credentials, payment references/secrets, OTPs, or private payloads.

MySQL's official InnoDB documentation describes opposite-order row locking as a deadlock cause, recommends consistent table/row order and short transactions, and says applications must retry a transaction selected as the deadlock victim. MariaDB documentation says SELECT FOR UPDATE applies inside a transaction/autocommit-off context. These vendor guarantees support the proposed conventions; they do not prove PawHeaven's implementation currently meets them.

### 5.5 Concurrency test matrix

Run these on the production-engine CI job with independent connections/processes and a deterministic barrier between lock/read/write stages:

| Race | Assertions |
|---|---|
| Two Owner claims for one company | Exactly one claim/membership succeeds; setup marker and audit agree; loser sees a safe already-completed result; no orphan staff promotion. |
| Membership revocation versus fulfilment/inventory/refund/return | An operation either commits while authorization was valid under the agreed lock order or rejects after revalidation; no mutation after revocation wins. |
| Online checkout versus POS for the final stock | No negative available/reserved count, duplicate reservation, or oversell; each accepted order has one movement per line. |
| Cancellation versus packing/shipping/return receipt | Exactly one valid state transition; stock release/receipt occurs at most once. |
| Coupon global and per-customer last redemption | Limits are not exceeded; failed order creates no redemption or stock/payment side effect. |
| Simultaneous partial payments/verification | Sum cannot exceed order due; duplicate form key produces one transaction; order payment status and events remain consistent. |
| Refund approvals on one payment/order | Total successful refunds never exceed verified collections/order bounds; a repeated completion is idempotent. |
| Last Platform Admin demotion versus another demotion/promotion | At least one active Platform Admin remains under the chosen invariant and concurrent changes are serialized or rejected. |
| Branch/company disable versus sensitive branch action | No action commits against a stale inactive/cross-company branch assignment. |
| Competing inventory adjustments and order reservations | Inventory movements reconcile exactly to product stock/reserved counts, with idempotent retry behavior. |

## 6. Design D — Database support and test strategy

### 6.1 Compatibility evidence and support statement

Current product documentation advertises SQLite 3.35+ and MySQL 8.0+ only, with MySQL explicitly requiring deployment-specific validation. Laravel 13's database documentation lists broader framework support (MySQL 5.7+ and MariaDB 10.3+); those are framework minimums, not PawHeaven's verified compatibility floor. The project-specific migrations and concurrency paths have not been exercised across engines.

Recommendation, explicitly as **proposed test targets, not verified support**:

- Keep SQLite 3.35+ as the local development/small single-server option documented today; do not claim it proves concurrent production correctness.
- Make MySQL 8.4 LTS the first production-engine CI target because it is an Oracle-designated LTS series.
- Add MariaDB 11.4 LTS as a second compatibility candidate. MariaDB is configured and migration 15 has explicit driver logic, but docs do not currently advertise it and no integration run proves it.
- Consider MariaDB 11.8 LTS as an additional compatibility candidate only if buyer/hosting demand justifies a second MariaDB version. Do not advertise any MariaDB version until migration and application suites pass.
- Do not advertise Laravel's old framework minimums (MySQL 5.7/MariaDB 10.3) as commercial support: those versions are not the chosen current production matrix and have not been tested against the app.

The version statements above are grounded in official documentation: Laravel 13 [database support](https://laravel.com/framework/docs/13.x/database) and [PHP requirement](https://laravel.com/framework/docs/13.x/releases); PHPUnit 12 [PHP requirement](https://docs.phpunit.de/en/12.5/installation.html) and [supported-version policy](https://phpunit.de/supported-versions.html); MySQL [8.4 LTS model](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html); MariaDB [maintenance schedule](https://mariadb.org/about/). MySQL concurrency guidance: [InnoDB deadlocks](https://dev.mysql.com/doc/refman/8.4/en/innodb-deadlocks.html), [handling deadlocks](https://dev.mysql.com/doc/refman/8.4/en/innodb-deadlocks-handling.html), and [transaction error handling](https://dev.mysql.com/doc/refman/8.4/en/innodb-error-handling.html). MariaDB locking guidance: [SELECT FOR UPDATE](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update).

### 6.2 Schema and SQL compatibility risks to validate

- Migration 15 branches on SQLite, MySQL, MariaDB, PostgreSQL, and SQL Server. It uses driver-specific generated/computed columns, unique indexes, composite foreign keys and native triggers. The existence of these branches is implementation intent, not pass evidence.
- MySQL/MariaDB use a generated active_owner_company_id/active_assignment_user_id pattern so one-nullable-value unique indexes emulate filtered unique indexes. Validate generated-column expressions and index behavior on exact supported versions.
- Trigger DDL uses MySQL/MariaDB CREATE TRIGGER ... BEGIN ... SIGNAL SQLSTATE; test syntax, delimiter handling by the driver, message/exception translation, and migration retry after partial success.
- Migration 15 performs schema operations and row backfill in one migration. MySQL/MariaDB DDL can commit independently of the surrounding migration transaction; if a later trigger/index statement fails, a partially applied schema may remain. Recovery instructions must inspect actual schema, keep backup, and use a forward corrective migration rather than blindly rolling back.
- Validate unique nullable generated columns, composite FKs, Schema::hasIndex/hasForeignKey behavior, trigger names/cleanup, foreign-key cascades, JSON snapshot fields, unsigned integer ranges, and collations/case sensitivity for codes and identifiers.
- Any monetary total uses integer minor units with unsigned integer/bigint fields. Define overflow limits for cumulative sums and use a sufficiently wide result type; do not use binary floating point for stored/accounting arithmetic.
- Test behavior under the database's intended isolation level. InnoDB may set range/gap locks based on indexes and query plans; MariaDB and MySQL version/driver differences can alter lock scope. Require indexes on equality/locking predicates and inspect EXPLAIN in the engine job.
- SQLite test behavior is materially different: it does not provide the same row-level SELECT FOR UPDATE serialization as InnoDB. Treat SQLite tests as functional/logic evidence only.

### 6.3 PHPUnit environment recovery

- composer.json's PHP ^8.3 and phpunit/phpunit ^12.5.23 align with installed PHPUnit 12.5.38 and Laravel 13's runtime floor. PHP 8.5 is also in the Laravel 13 supported range. The historical XAMPP PHP 8.0 runtime is unsuitable.
- Stage B reproduced PHP's is_readable() false result despite existing paths and successful file_get_contents(); the failing guard is PHPUnit's BootstrapLoader check. The exact Windows subsystem cause remains unproven, so do not bypass the guard, edit vendor, change ACL/PHP settings, or weaken permissions.
- There is already a Linux CI job at .github/workflows/tests.yml using PHP 8.3 and an isolated CI workspace. A safe next verification is to run/review that job on a proposed branch/PR or use an already-available Linux/WSL environment with the project lockfile; neither requires altering the user's Windows runtime. This stage did not execute it.
- Harden the runner before sensitive DB tests: force APP_ENV=testing and DB_CONNECTION/DB_DATABASE/DB_URL at process level, resolve the connection after Laravel boot, and fail fast unless it is an in-memory or uniquely named disposable database. Explicitly reject the active database/database.sqlite and any non-test DB host/schema.
- phpunit.xml currently intends SQLite :memory:, but its XML env values are not force-overrides. Do not rely on that alone. Existing composer test script clears configuration, runs Pint, PHPStan, then Artisan tests; do not use it against a live user environment until DB guard and filesystem bootstrap are verified.
- Start with unit and isolated SQLite feature suites; run migration integration on a disposable SQLite file; then MySQL/MariaDB service containers and separate-process races. Keep the local active SQLite database outside all test configuration.
- Existing tests include BusinessAccessIsolationTest, CreateAdminCommandTest, InvitationOnboardingTest, InventoryReconciliationTest, PaymentTransactionsTest, PaymentRefundsTest, FulfilmentOperationsTest, PricingAndPromotionsTest and SalesReportingTest. They are source evidence only here; none was executed.

## 7. Open product-owner decisions

1. Currency evidence: what can buyers cite (historic settings export, invoice, accounting ledger), who approves mapping, and may one reviewer approve an entire group?
2. Unresolved financial records: should new payment/refund operations be blocked outright, or allow a documented manual procedure after separate review? Recommended default is no financial mutation until verified.
3. Audit evidence retention: store a protected evidence attachment, a secure external locator/hash, or both? Define retention and access policy to avoid collecting unnecessary customer financial data.
4. Future multi-currency: is PawHeaven strictly one currency per installation for new sales, as CurrencyPolicy currently enforces, or should a later product design support multiple currencies per company/order?
5. First Owner consent and authority: require candidate-initiated claim plus Platform Admin approval, or a one-time signed invitation created by the installation operator? Who approves when the Platform Admin is unavailable?
6. Owner recovery: which proof is sufficient when the original owner email is inaccessible? Should a second buyer-side approver be mandatory? Who can operate the offline recovery command?
7. Owner count: migration 15 enforces one active Owner per company. Confirm that this remains a business rule before designing transfer/schema changes.
8. Company topology: current schema restricts each installation to one company. Approve or reject a future multi-company installation requirement before changing the guard and setup state scope.
9. Database product support: authorize MySQL 8.4 LTS as the first CI production engine and MariaDB 11.4/11.8 as candidates? Commercial claims should follow test evidence, not framework-wide support.
10. Local Windows testing: use hosted GitHub Actions/Linux CI as the accepted workflow while the local is_readable() issue remains, or authorize separate environment investigation later?

## 8. Phased implementation roadmap

| Phase | Scope and likely files | Dependencies / migration | Security and data preservation | Regression / acceptance | Recovery |
|---|---|---|---|---|---|
| C1. Test isolation and CI baseline | .github/workflows/tests.yml, phpunit.xml, tests/TestCase.php, test bootstrap/support docs. Avoid vendor changes. | None; create disposable DB guard before feature work. | Ensure active SQLite path is rejected by test harness; process-level forced test vars. | Existing GitHub CI green; test DB identity asserted; no active DB access; record bootstrap environment separately. | CI changes can be reverted independently; no production database touched. |
| C2. Currency schema/provenance | New migration(s), Product/PetListing/Order/OrderItem/payment/pricing models, CurrencyPolicy and reconciliation services. Never edit migration 20. | C1 preferred; migration supports 20 pending and 20 applied. | Preserve all amount columns/codes; legacy rows become unresolved; remove default reliance only after explicit writer audit. Back up every buyer DB before deployment. | Fresh and old-upgrade fixtures for USD, BDT, mixed, and unknown histories; values identical before/after; no inferred or converted values; reconciliation event verifies exact mapping. | Roll forward with additive migration; do not rollback/rebuild tables to undo resolution. Preserve append-only reconciliation events and backup. |
| C3. Currency enforcement and presentation | PricingService, checkout/POS services, SalesReportingService, DashboardController, CustomerController, sales/customer/dashboard/receipt/email views and relevant tests. | C2 state contract. | Block unresolved new sales; restrict report and document display; no financial mixed sum; preserve read-only history. | Tests assert each currency group separately; unresolved excluded/flagged; item/payment/refund relationships reject mismatches; one-currency screens remain behaviorally equivalent. | Feature flags may hide new reconciliation UI, but enforcement must fail closed; code rollback must not remove C2 data. |
| C4. Owner state and initial claim | Company/setup model, new migration/state table, BusinessAccessController, BusinessMembershipService/Policy, routes/admin.php, installation docs, CLI command and audit event model. | Product owner confirms one Owner, consent model, recovery authority. | One-time atomic state; keep Platform Admin separate; no password exposure; no auto-promotion. | Fresh install, candidate consent, invalid/unverified target, replay, two concurrent claims, Platform Admin target rejection and access isolation. | Reversible only through explicit recovery/transfer events; no membership/user deletion. Migration additive and marker seeded from current Owner state conservatively. |
| C5. Owner transfer and offline recovery | Dedicated owner lifecycle service/command/controller, policy, audit views/docs, tests. | C4 state machine; product owner supplies proof and approval standard. | No public claim endpoint, require reauthentication/verification, audit old/new actor, notify where possible. | Transfer success/failure atomicity; inaccessible-email scenario; lost/revoked sessions; multiple company scenario remains rejected until topology decision. | Offline recovery can restore authority without changing user password; preserve prior membership/event and server backup. |
| C6. Lock protocol refactor | BusinessMembershipService, BusinessAccessService, InventoryMovementService, OrderRequestController, EmployeeSalesDeskService, PaymentTransactionService, PaymentRefundService, ProductReturnService, FulfilmentService, Inventory/Product controllers, PricingService, role controller. | C1 safe concurrency test runner; document common lock ordering. | Move authorization checks under canonical locks; preserve idempotency and authorization outcome; avoid external effects inside retry callbacks. | Deterministic multi-connection matrix; no duplicate stock/payment/refund effects; bounded retries; existing sequential tests pass. | Each workflow refactor should be atomic and small; keep idempotency keys, DB constraints, and verified backup before production rollout. |
| C7. Engine compatibility and release support | Migration/CI matrix, docs/INSTALLATION.md, deployment/upgrade docs, monitoring. | C1 and C6; product owner selects engine targets. | Isolated engine databases; no user/business data fixtures in logs. | Fresh/upgrades pass on declared MySQL/MariaDB versions; migrations/triggers/indexes verified; concurrency suite passes; published support table names exact versions. | Keep SQLite local option; do not switch buyer DB engine in place as a migration; document engine-specific backup/restore and tested upgrade paths. |

Do not combine C2, C4, and C6 into one refactor or migration release. Currency and Owner semantics need product decisions; lock-order changes need production-engine races; each should be separately reviewable and deployable.

## 9. Risks and unresolved questions

- For an installation that already ran migration 20, source data cannot prove which USD rows were historically backfilled. Conservative unresolved marking protects integrity but creates buyer reconciliation work and may flag legitimate records.
- If new currency status is added to every monetary table, schema complexity and migration portability increase. A generic polymorphic ledger is simpler but lacks ordinary foreign-key enforcement. Prototype migration and integrity tests before choosing.
- Resolution state alone does not prove that evidence is true. Define who may approve, whether dual approval is needed, and what evidence retention is allowed.
- The app currently enforces one current currency for new records, not full multi-currency marketplace checkout. Historical mixed-currency support should not be mistaken for multi-currency sales support.
- A durable Owner marker must handle the existing one-company invariant now and a future multi-company topology only if explicitly approved.
- Owner recovery by the machine operator depends on an external proof-of-ownership process; the application cannot determine legal company ownership from account/email data alone.
- Locking Company rows before each business operation could serialize unrelated company activity. The final row set should be minimal while retaining canonical order and revocation guarantees.
- Unique constraints and row locks differ by engine/version; current source-level fallbacks do not establish compatibility.
- Current GitHub Actions uses PHP 8.3/Linux, but without a run result it is only a configured path, not proven test execution success.
- PHPUnit remains unexecuted locally because of the Windows is_readable() bootstrap failure. No new test results, database integration results, concurrency results, or browser results are claimed here.

## 10. Exact source inventory inspected

### Audit, setup and test baseline

- docs/audit/STAGE_B_PRE_IMPLEMENTATION_INVESTIGATION_2026-11-17.md
- docs/audit/COMMERCIAL_READINESS_AUDIT_2026-11-17.md
- docs/INSTALLATION.md
- composer.json
- composer.lock (selective dependency metadata)
- config/database.php
- phpunit.xml
- phpstan.neon
- .github/workflows/tests.yml

### Currency, models, reports, pricing and migrations

- database/migrations/2026_10_29_020100_create_pet_listings_and_related_tables.php
- database/migrations/2026_10_29_030100_create_shop_and_order_tables.php
- database/migrations/2026_11_19_000000_create_payment_transactions_table.php
- database/migrations/2026_11_20_000000_add_currency_snapshots_and_refund_transactions.php
- database/migrations/2026_11_23_000000_create_pricing_and_promotion_tables.php
- app/Models/Product.php, PetListing.php, Order.php, OrderItem.php, PaymentTransaction.php, PaymentRefundTransaction.php, BranchProductPrice.php, Promotion.php, Coupon.php, DiscountRedemption.php
- app/Services/CurrencyPolicy.php, PricingService.php, SalesReportingService.php, InventoryReportingService.php
- app/Services/PaymentTransactionService.php, PaymentRefundService.php
- app/Http/Controllers/OrderRequestController.php
- app/Http/Controllers/Marketplace/ListingBrowseController.php
- app/Http/Controllers/Admin/DashboardController.php, CustomerController.php, SalesReportController.php
- resources/views/admin/dashboard.blade.php, resources/views/admin/customers/index.blade.php, resources/views/admin/sales-reports/*, resources/views/admin/orders/*, resources/views/admin/sales-desk/*, resources/views/emails/orders/*

### Owner provisioning and locking paths

- database/migrations/2026_11_10_000000_add_company_branch_business_access.php
- database/migrations/2026_11_15_000000_add_business_integrity_guards.php
- app/Console/Commands/CreateAdminCommand.php
- app/Models/BusinessMembership.php, Company.php, Branch.php, User.php, Invitation.php
- app/Policies/BusinessMembershipPolicy.php
- app/Services/BusinessAccessService.php, BusinessMembershipService.php, InvitationService.php
- app/Http/Controllers/Admin/BusinessAccessController.php
- app/Http/Controllers/Admin/InventoryController.php, ProductController.php, UserController.php
- app/Http/Controllers/Admin/PaymentTransactionController.php, FulfilmentController.php, PricingManagementController.php
- app/Http/Controllers/PaymentRefundController.php, ProductReturnController.php
- app/Services/EmployeeSalesDeskService.php, InventoryMovementService.php, ProductReturnService.php, FulfilmentService.php
- routes/admin.php, routes/web.php, routes/seller.php, routes/settings.php
- app/Providers/FortifyServiceProvider.php, config/fortify.php

### Test coverage inspected

- tests/TestCase.php
- tests/Feature/Admin/BusinessAccessIsolationTest.php
- tests/Feature/Admin/CreateAdminCommandTest.php
- tests/Feature/Admin/InvitationOnboardingTest.php
- tests/Feature/Admin/SystemHealthTest.php
- tests/Feature/Auth/PasswordResetTest.php
- tests/Feature/Auth/EmailOtpConcurrencyTest.php
- tests/Feature/InventoryReconciliationTest.php
- tests/Feature/EmployeeSalesDeskTest.php
- tests/Feature/PaymentTransactionsTest.php
- tests/Feature/PaymentRefundsTest.php
- tests/Feature/FulfilmentOperationsTest.php
- tests/Feature/PricingAndPromotionsTest.php
- tests/Feature/SalesReportingTest.php
- tests/Feature/ProductPurchaseTest.php
- tests/Feature/EndToEndIntegrationJourneysTest.php

No .env values, credentials, user identifiers, private customer data, session values, tokens, payment secrets, or OTPs were accessed for this report.

## 11. Read-only activity and Git status

- No source files, migrations, config, dependencies, environment files, existing reports, or database files were changed.
- No migrations, seeders, PHPUnit, PHPStan analysis, database server, browser workflow, or MySQL/MariaDB test was run.
- No Git-changing command was used.
- Pre-document Git status: 102 changed tracked entries, 80 untracked entries, 0 staged entries.
- The requested design document is the only new file from this stage.
