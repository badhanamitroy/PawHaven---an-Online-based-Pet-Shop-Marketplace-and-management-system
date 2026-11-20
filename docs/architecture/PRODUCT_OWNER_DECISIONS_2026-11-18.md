# PawHeaven Product Owner Decisions — 2026-11-18

Status: **Approved product policy**
Scope: **Authoritative decisions for the D2–D5 workstreams**
Repository: `D:/PawHaven/PH Web Version/WebApp`

This register records the product-owner decisions supplied for Phase D1. It separates approved business policy from technical choices still requiring validation. It does not add product-owner decisions.

## Currency integrity

### D1 — Unknown historical currency

- **Approved policy:** Historical monetary records whose currency cannot be proven remain unresolved.
- **Architectural implications:** Currency identity and verification state must be represented separately. Ambiguous values must not enter verified financial totals.
- **Affected workstreams:** D2 schema/provenance; D3 mutation enforcement and reporting.
- **Required acceptance tests:** An ambiguous legacy record remains unresolved after upgrade, is presented in a separate unresolved group, and is not treated as a verified currency total.
- **Technical validation still open:** Per-table state representation, migration-20 default treatment, and report presentation contract.

### D2 — No inference or automatic historical conversion

- **Approved policy:** Never infer historical currency or automatically convert historical amounts.
- **Architectural implications:** Current settings, locale, geography, amount, product category, or current price cannot establish historical currency. Reconciliation changes resolution metadata only; it does not rewrite amounts.
- **Affected workstreams:** D2 provenance/reconciliation; D3 displays, reports, receipts, exports.
- **Required acceptance tests:** Changing configured sales currency leaves historical values unchanged; no inferred currency or exchange-rate conversion occurs.
- **Technical validation still open:** How to preserve source evidence and record an approved correction without mutating original evidence.

### D3 — Individual reconciliation approval

- **Approved policy:** Reconciliation of an individual record requires Company Owner approval.
- **Architectural implications:** The server authorizes approval against the record's company and records the actor and decision.
- **Affected workstreams:** D2 reconciliation authorization and audit; D3 visibility of resolution state.
- **Required acceptance tests:** An authorized Company Owner can approve an evidence-backed individual resolution; a user acting only with customer, employee, branch-manager, or Platform Admin authority cannot approve it.
- **Technical validation still open:** Evidence storage/reference format, reauthentication, and audit schema.

### D4 — Bulk reconciliation approval

- **Approved policy:** Bulk reconciliation requires dual approval.
- **Architectural implications:** A batch needs durable approval and progress state; processing cannot begin after only one approval. It must be retry-safe and preserve per-record results.
- **Affected workstreams:** D2 batch workflow; D3 reporting.
- **Required acceptance tests:** One approval cannot start processing; two approvals are recorded before processing; retry does not duplicate history or alter amounts.
- **Technical validation still open:** Whether approvals must be from distinct people, eligible approver roles, and batch continuation mechanics.

### D5 — Block payment and refund mutations for unresolved currency

- **Approved policy:** Normal payment and refund mutations are blocked until the relevant currency is verified.
- **Architectural implications:** Enforce the policy in services as well as forms; every route and internal caller follows the same rule.
- **Affected workstreams:** D3 checkout/POS, payment verification, refunds, reporting.
- **Required acceptance tests:** Each payment/refund mutation fails closed for unresolved records; after authorized resolution a valid mutation follows existing rules; no alternate route bypasses enforcement.
- **Technical validation still open:** The authoritative record states each mutation must check and consistent error behavior for UI, API, and CLI callers.

### D6 — One configured sales currency per installation

- **Approved policy:** PawHeaven supports one configured sales currency per installation for now.
- **Architectural implications:** New monetary records explicitly snapshot the configured currency. This does not permit relabeling history or aggregating different currencies.
- **Affected workstreams:** D2 future-writer behavior; D3 checkout, POS, pricing, reporting.
- **Required acceptance tests:** New product/order/payment/refund/pricing records use the configured currency; mismatches are rejected; existing snapshots remain unchanged.
- **Technical validation still open:** Complete inventory of monetary writers and whether any writer relies on a database default.

### D7 — Currency- and resolution-separated financial reports

- **Approved policy:** Financial reports separate currencies and resolution states.
- **Architectural implications:** Reports do not sum unlike currencies. Unresolved values are visibly separate and are not presented as verified currency totals.
- **Affected workstreams:** D2 status contract; D3 sales/customer dashboards, finance reports, receipts, exports.
- **Required acceptance tests:** Multi-currency fixtures produce separate totals; unresolved values are separately identified; no mixed-currency grand total is exposed.
- **Technical validation still open:** Shared grouping/filter contract and handling of historical receipts and exports.

## Ownership

### D8 — Consent and scoped initial-setup approval for first Owner

- **Approved policy:** First Owner provisioning requires verified customer consent and narrowly scoped initial-setup approval.
- **Architectural implications:** General installation/platform administration is not evidence of customer consent. Approval is limited to first-owner provisioning and closes atomically after completion.
- **Affected workstreams:** D4 setup state, consent, approval, first assignment.
- **Required acceptance tests:** An unverified or non-consenting user cannot become Owner; a valid candidate can complete the path once; unrelated Platform Admin authority does not grant ownership.
- **Technical validation still open:** Consent artifact, approval capability, setup-state schema, and transaction boundary.

### D9 — Offline Owner recovery

- **Approved policy:** Owner recovery requires an offline installation-operator procedure with documented proof and an audit trail.
- **Architectural implications:** Recovery is not an unauthenticated public claim route or undocumented database edit. The procedure must cover an inaccessible Owner email.
- **Affected workstreams:** D4 operator procedure, recovery operation, audit.
- **Required acceptance tests:** No public self-service takeover exists; recovery requires documented operator evidence; the operation records actor, target, justification/evidence reference, and time; prior authority remains traceable.
- **Technical validation still open:** Proof standard, operator authentication, command safeguards, evidence retention, and notification.

### D10 — One active Owner per company

- **Approved policy:** Each company has one active Owner.
- **Architectural implications:** Enforce the invariant under concurrent assignments and transfers, not only in UI logic.
- **Affected workstreams:** D4 provisioning/transfer; D5 constraints and race tests.
- **Required acceptance tests:** A second active Owner is rejected at application and database layers; concurrent claims/transfers cannot leave two active Owners.
- **Technical validation still open:** Atomic transition and supported-engine constraint behavior.

### D11 — Explicit, reauthenticated, audited Owner transfer

- **Approved policy:** Owner transfer requires explicit consent, reauthentication, and auditing.
- **Architectural implications:** Transfer is a distinct lifecycle operation, not a generic membership-role edit. The transition is atomic and history-preserving.
- **Affected workstreams:** D4 transfer service, authorization, UI/operational flow.
- **Required acceptance tests:** Missing consent or reauthentication fails; successful transfer records the parties and actor; failure leaves prior ownership intact; replay is safely rejected or idempotent.
- **Technical validation still open:** Consent capture, notification, reauthentication window, audit event schema.

### D12 — Platform Admin and Company Owner authority remain separate

- **Approved policy:** Platform Admin authority does not imply Company Owner authority.
- **Architectural implications:** Platform role does not grant private company data or business actions. Owner provisioning uses the approved process.
- **Affected workstreams:** D4 authorization/setup; D3 financial controls.
- **Required acceptance tests:** A Platform Admin without an Owner membership is denied company-private operations; platform role alone cannot create or claim Owner status.
- **Technical validation still open:** Whether a verified customer who also has Platform Admin role may separately apply through the same consent and approval process.

### D13 — One company per installation

- **Approved policy:** One company per installation remains the supported topology.
- **Architectural implications:** Setup state and bootstrap are installation-scoped; company creation preserves the existing one-company invariant.
- **Affected workstreams:** D4 setup marker/company creation; D5 uniqueness/concurrency validation.
- **Required acceptance tests:** A second company cannot be created by UI, command, or concurrent requests; setup state remains bound to the only company.
- **Technical validation still open:** Migration handling for any legacy installation already containing multiple companies.

## Database support

### D14 — MySQL 8.4 LTS is the first production compatibility target

- **Approved policy:** MySQL 8.4 LTS is the first production database compatibility target.
- **Architectural implications:** PawHeaven-specific migrations and concurrency paths must be tested on MySQL 8.4; framework support alone is insufficient.
- **Affected workstreams:** D5 CI/database matrix; installation and release documentation.
- **Required acceptance tests:** Fresh install, supported upgrade, constraints, business feature suite, and concurrency tests pass on MySQL 8.4 before compatibility is advertised.
- **Technical validation still open:** Exact server image/patch level, CI service configuration, PHP PDO driver, isolation level, and evidence required for support.

### D15 — MariaDB 11.4 is a compatibility candidate only

- **Approved policy:** MariaDB 11.4 is a candidate, not an advertised supported engine until it passes testing.
- **Architectural implications:** Do not claim support from migration branches or framework compatibility alone.
- **Affected workstreams:** D5 compatibility matrix and release documentation.
- **Required acceptance tests:** Run the same migration, feature, constraint, and concurrency matrix on MariaDB 11.4 before changing its status.
- **Technical validation still open:** Compatibility results and whether to promote the candidate to supported status.

## Testing

### D16 — Prefer the existing Linux GitHub Actions workflow

- **Approved policy:** Use the existing Linux GitHub Actions workflow as the preferred test environment.
- **Architectural implications:** Every result identifies the exact committed revision tested. A passing run on HEAD does not validate uncommitted working-tree changes.
- **Affected workstreams:** D1 baseline; D2–D5 regressions and engine tests.
- **Required acceptance tests:** Record workflow URL/run ID, event, commit SHA, job result, and database engine for each reported CI result.
- **Technical validation still open:** Hosted run availability and an authorized way to submit exact source changes for CI.

### D17 — No Windows runtime investigation in this phase

- **Approved policy:** Do not modify the Windows PHP runtime or investigate alternative local runtimes at this stage.
- **Architectural implications:** The existing Windows PHPUnit blocker remains outside this phase; Linux CI is the preferred validation path.
- **Affected workstreams:** D1 CI baseline and later regression validation.
- **Required acceptance tests:** No runtime, PHP configuration, PATH, ACL, or local dependency changes are made; any test claim cites an executed Linux run or separately authorized path.
- **Technical validation still open:** None for the policy; actual availability/results of the Linux workflow remain to be verified.

## Boundaries

This register does not approve a specific reconciliation schema, evidence-storage format, first-owner claim token, command interface, lock order, deadlock retry count, or MariaDB support claim. These remain engineering choices for validation and may not weaken the approved policies above.

