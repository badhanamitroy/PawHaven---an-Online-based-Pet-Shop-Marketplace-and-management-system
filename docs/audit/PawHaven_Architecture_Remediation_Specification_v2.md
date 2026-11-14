# PawHaven Architecture Remediation Specification v2

**Status:** Architecture proposal for owner review; implementation is not approved by this document.  
**Prepared:** 2026-11-11  
**Evidence scope:** Read-only inspection of `PH Web Version/WebApp` source and existing documentation. No database was changed or inspected during this revision.  
**Specification provenance:** A v1 document with the requested title was not located in the inspected documentation trees. This v2 therefore resolves the issues enumerated in the supplied remediation brief and uses existing audit documents and source as evidence; it does not claim a line-by-line textual comparison to a v1 file.

## 1. Executive Summary

PawHaven is a Laravel monolith containing a public pet marketplace, a centrally managed pet-products shop, customer and Seller workspaces, administrative operations, and a pending company/branch access layer. The approved target is a multi-company business architecture that preserves the independent marketplace Customer identity and legacy Seller-owned animal listings while adding company-authorized Owners, Branch Managers, and Sellers.

The architecture must maintain two independent authorization domains: platform operations and company business operations. Platform roles do not confer business membership. Conversely, company membership does not confer platform privilege. Shop Owners control only their own company; Branch Managers and Sellers have defined branch-scoped powers. Catalogue ownership/pricing, inventory, procurement, online orders, POS sales, payments, customer privacy, and animal/adoption lifecycles must not be collapsed into one role or state machine.

Recommended foundation:

1. Keep global `users` as the account identity and maintain explicit company membership.
2. Make a branch-assignment relation the canonical source of current operational branch scope, with effective-dated records preserving its history. Do not copy branch assignments into a second “current branch” field.
3. Keep global Customer identity, company-specific customer relationships, transaction branch association, and Seller attribution distinct.
4. Preserve current online order/payment behavior; introduce POS sale lifecycle separately and do not infer payment architecture from it.
5. Add company-owned inventory and procurement with append-only movement/financial history, approval limits, and transaction-safe concurrency.
6. Preserve legacy `PetListing` ownership and history; do not assume listings are company inventory.

Three owner decisions materially affect the design and must precede implementation: Seller assignment uniqueness (global versus per-company), company Owner cardinality, and the formal scope of exceptional platform intervention. The recommended default is one active Shop Owner per company and one active Seller branch per company; both remain explicit approval decisions rather than silently adopted rules. No default company or inferred legacy ownership is approved.

**Verdict:** **READY WITH OWNER DECISIONS PENDING** for architecture approval only. This is not implementation approval or a claim of production readiness.

## 2. Scope and Non-Goals

### In scope

- Corrected role and permission model.
- Multi-company and branch isolation.
- Membership and assignment source of truth.
- Customer identity, consent, and record visibility.
- Owner-controlled catalogue/pricing and company-owned inventory.
- Separate online order, POS sale, and payment lifecycles.
- Company procurement, supplier returns, and supplier financial history.
- Animal listing/adoption compatibility.
- Database integrity, security enforcement, tests, migration strategy, risks, and phased implementation proposal.
- Evidence-based description of inspected current implementation and conflicts with locked requirements.

### Non-goals and explicit deferrals

- Implementing any phase, migration, schema, policy, route, test, or configuration change.
- Executing migrations, seeders, database writes, or data cleanup.
- Payment gateways, payment collection/reconciliation redesign, refunds ledger, Seller payouts, platform commission/settlement, and supplier payment execution.
- Replacing or redesigning the approved Step 1B authentication/session architecture.
- Deciding unresolved business questions on behalf of the Owner.
- Claiming production database engine, production deployment readiness, or current implementation of proposed architecture.

Supplier invoices, returns, supplier balances/history, and their audit records are **in scope**. Only payment execution is deferred.

### Evidence and decision labels

- **VERIFIED CURRENT FACT** means directly observed in an inspected repository file or identified as such in an existing audit; the cited evidence does not imply production deployment.
- **LOCKED — OWNER APPROVED** means a business rule supplied as approved in this task.
- **PROPOSED — OWNER REVIEW REQUIRED** means a target technical design, not an implemented fact or approved business rule.
- **UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED** means implementation behavior must not be inferred until the specified owner decision is made.
- Sections 9–24 describe proposed target architecture and future requirements unless a statement is explicitly labeled as a verified current fact. Roadmap phases are proposals and are not authorized work.

## 3. Verified Current Architecture

Evidence here is limited to repository files/documentation inspected for this specification. File existence in the dirty worktree does not by itself prove a migration has been applied or a feature is operational.

### Application structure and business domains

- Existing architecture audit describes a Laravel server-rendered monolith using Blade, Livewire, Fortify, Eloquent, and no separate deployed API or repository layer: [PawHaven_Architecture_Audit.md](./PawHaven_Architecture_Audit.md), “Project structure and layers.”
- Public animal listings use `PetListing`, with `seller_id`, `listing_type`, price, status, category/breed, images, moderation actions, and saved-pet relations: [PetListing.php](../../app/Models/PetListing.php). Existing audit describes listing states and seller ownership in [FEATURES_AND_ROLES.md](../FEATURES_AND_ROLES.md).
- Product-shop entities are separate `Product`, `ProductCategory`, `Order`, and `OrderItem` models. `Product` has one `stock_quantity` and catalogue price fields; `OrderItem` snapshots product name, SKU, price, quantity, and subtotal, but no unit-cost snapshot is present in the inspected model: [Product.php](../../app/Models/Product.php), [Order.php](../../app/Models/Order.php), [OrderItem.php](../../app/Models/OrderItem.php).
- Existing online purchase request code validates one product and quantity, reserves stock, and creates the order and item inside a database transaction: `OrderRequestController::store` in [OrderRequestController.php](../../app/Http/Controllers/OrderRequestController.php). Current cancellation is transaction-wrapped and calls `OrderInventoryService::restoreReservedInventory`: [web.php](../../routes/web.php), [OrderInventoryService.php](../../app/Services/OrderInventoryService.php). Preserve this observed behavior; do not infer POS or cart behavior from it.
- The existing order state enum is `pending → processing → shipped → delivered`, with cancellation from pending/processing/shipped: [OrderStatus.php](../../app/Enums/OrderStatus.php). Payment is represented separately by `PaymentStatus` values `pending`, `paid`, and `refunded`: [PaymentStatus.php](../../app/Enums/PaymentStatus.php). The current implementation does not prove a new gateway or payment ledger exists.
- `Order` currently stores an optional `user_id`, contact/address snapshots, order totals, payment method/status, `seller_id`, optional `pet_listing_id`, and `inventory_reserved_at`: [Order.php](../../app/Models/Order.php). This supports neither a complete company-customer relationship model nor a dedicated POS sale model.

### Existing company/branch access proposal in source

- **VERIFIED CURRENT FACT:** The pending migration file [2026_11_10_000000_add_company_branch_business_access.php](../../database/migrations/2026_11_10_000000_add_company_branch_business_access.php) creates companies and `business_memberships`, adds nullable company/branch columns, backfills existing branches/categories/products/orders to a `PawHaven` company, and creates active memberships for legacy `staff` users. The repository audit says it had not been run at inspection time: [BUSINESS_MANAGEMENT_ACCESS.md](../BUSINESS_MANAGEMENT_ACCESS.md). The migration file is evidence of proposed source changes, not proof of an applied database state.
- `business_memberships` currently combines `user_id`, `company_id`, optional `branch_id`, one role string, and `is_active`; the migration defines ordinary foreign keys but not a uniqueness constraint for duplicate memberships or a cross-company branch constraint.
- `BusinessAccessService::applyScope` uses active membership rows to derive company/branch query visibility; `canManageCompany` treats `owner` and `manager` membership roles as managers. `hasBusinessAccess` grants Admin bypass and only admits Staff with membership: [BusinessAccessService.php](../../app/Services/BusinessAccessService.php).
- `HasBusinessScope` applies a global visibility scope only for Staff who are not Admin, and includes a default company/branch assignment hook: [HasBusinessScope.php](../../app/Models/Concerns/HasBusinessScope.php).
- `ProductPolicy` currently permits Admin or eligible Staff to create, permits record access to update, and reserves delete to Admin: [ProductPolicy.php](../../app/Policies/ProductPolicy.php). This conflicts with the approved Owner-only catalogue editing and does not encode the target business roles.
- `BusinessMembershipPolicy` allows a platform Admin broad membership-management access and permits certain company/branch roles to manage membership: [BusinessMembershipPolicy.php](../../app/Policies/BusinessMembershipPolicy.php). This is not the final approved business permission matrix.
- `OrderPolicy` uses Admin/Staff business scope but also grants Seller-specific order access using `seller_id`: [OrderPolicy.php](../../app/Policies/OrderPolicy.php). It is not yet a full Seller/Branch Manager/Owner business policy.
- `BusinessMembership`, `Company`, and `Branch` models exist in source: [BusinessMembership.php](../../app/Models/BusinessMembership.php), [Company.php](../../app/Models/Company.php), [Branch.php](../../app/Models/Branch.php). The submitted feature does not yet include separate assignment-history, stock ledger, transfer, procurement, POS, or company-customer relationship entities.

### Existing identity and session evidence

- `User` is the global authenticatable identity with one role string, methods for `admin`, `staff`, `seller`, and `customer`, marketplace listing/order relations, and device-session relation: [User.php](../../app/Models/User.php).
- The current role model is not the approved two-domain role model. `User::isAdmin()` and `User::isStaff()` are platform-role predicates; the pending company design must not reinterpret them as Shop Owner or business staff.
- A device-session middleware and service exist in source: [RestoreUserDeviceSession.php](../../app/Http/Middleware/RestoreUserDeviceSession.php), [UserDeviceSessionService.php](../../app/Services/UserDeviceSessionService.php). The exact Step 1B decisions below are supplied as approved and locked; this specification does not redesign or re-audit them.
- Existing access documentation describes a public published product catalogue, Seller-owned pet marketplace listings, and unchanged COD/payment behavior: [BUSINESS_MANAGEMENT_ACCESS.md](../BUSINESS_MANAGEMENT_ACCESS.md), [FEATURES_AND_ROLES.md](../FEATURES_AND_ROLES.md).

### Environment and evidence limitations

- A previous read-only audit observed an active local SQLite database and stated that production engine and production configuration were unverified: [PawHaven_Architecture_Audit.md](./PawHaven_Architecture_Audit.md). This specification did not inspect or modify the database.
- No production database engine, deployment topology, live identity-provider behavior, production traffic, production data ownership, or full policy coverage is asserted here.
- Repository modifications were already present before this task. They were not changed, staged, or reverted.

## 4. Current Architecture Risks

1. **Role boundary mismatch:** current `ProductPolicy` gives platform Admin and Staff product operations that the target reserves to company Owner (except specifically authorized platform intervention). Current role and business-role concepts are mixed.
2. **Admin business bypass:** `BusinessAccessService` treats Admin as broadly able to access company records; target platform authority must be platform-scoped, with only explicit audited intervention workflows crossing into company operations.
3. **Branch source ambiguity:** membership currently carries a nullable branch ID and has no separate assignment-history model. Adding `branch_assignments` without defining authority would create competing current-scope sources.
4. **No verified cross-company integrity:** single-column FKs do not guarantee that a membership, product, order, or assignment branch belongs to the same company.
5. **Current product stock is not a ledger:** one `stock_quantity` per product cannot represent company ownership, branch locations, reservations, movements, transfers, or audit history.
6. **No cost snapshot:** inspected `OrderItem` has price/quantity/subtotal snapshots but no verified cost-at-sale field; historical gross profit cannot be reliably reconstructed from current product cost.
7. **Customer data is under-modeled:** `users` is a global account, while order contact fields are snapshots; there is no verified company-specific customer relationship, consent ledger, branch association, or Seller-sale join.
8. **Animal and ordinary stock are distinct:** current `PetListing` is Seller-owned marketplace content; it is not a quantity-based inventory record or proof of company ownership.
9. **No separate POS/procurement subsystem:** no verified POS sale, purchase request/order, receiving, supplier invoice/return, or supplier balance models were found in the inspected model inventory.
10. **Product and order lifecycle conflation risk:** existing online order and payment statuses are distinct; a future POS “paid/completed” status must not silently redesign either.
11. **Legacy backfill risks:** the pending source migration applies a default company identity and assigns legacy Staff, which is not an approved general legacy-onboarding rule. Existing source/data ownership cannot safely be inferred merely to make nullable columns non-null.
12. **No assurance of production portability:** SQLite test behavior and the unknown production engine cannot establish support for partial unique indexes, composite FKs, locking, or concurrent approvals.

## 5. Locked Business Decisions

Each item below is **LOCKED — OWNER APPROVED** as supplied in this task. It is not reopened by the unresolved-decision register.

- Platform roles are Platform Admin and Platform Staff. Business roles are Shop Owner, Branch Manager, Seller, and Customer. Platform and business authorization are separate; no implicit privilege conversion or employee conversion is allowed.
- Shop Owner has highest authority within only their company and can manage catalogue, pricing, branches, employees, operations, procurement, reports, audit settings, and Seller operational work. Ownership transfer is explicit and auditable.
- Branch Manager has restricted authority only for assigned branch(es), may assign Sellers to their own branch, and cannot edit products/prices, manage other Branch Managers, access unrelated branches, change central settings, approve their own request, or exceed the approval limit.
- Seller is assigned to exactly one active branch at a time within the scope that remains an owner decision. Seller sells company-owned inventory, accesses only own-sale-associated customer data, cannot own/adjust/approve stock, and has no catalogue/employee authority. Seller gross profit is attribution only.
- Customer remains an independent marketplace identity, can use customer workflows, and is not owned by a company.
- Only Shop Owner can create/launch/edit catalogue products and control official pricing and discounts. Referenced products are archived/deactivated, not hard-deleted. Product variants are introduced only if justified by distinct SKU or branch stock needs.
- Inventory is company-owned, branch-located, ledgered, auditable, non-negative, and transaction-safe. Available quantity is On Hand minus Reserved. Seller cannot independently adjust or approve stock. Transfers include request, review/approval where applicable, dispatch, receiving, quantity verification, and completion. Preserve existing online reservation/cancellation restoration.
- Procurement is company inventory procurement. Seller and Branch Manager may request; only Branch Manager can approve within Owner-configured branch limit and never own requests; above limit goes to Owner; Seller cannot approve. Prevent/detect request splitting. Include invoices, partial receiving, discrepancies, returns, supplier balances/history. Supplier payment execution deferred.
- Online orders and POS have separate lifecycles and share company branch inventory appropriately. Preserve current online checkout/order/cancellation/payment behavior. POS needs branch-based multi-item sale, search, stock checks, Seller and branch attribution, completed sale, and printable receipt. New payment gateways, collection/reconciliation redesign, refunds ledger, payouts, commissions/settlements, and supplier payment are deferred.
- Seller/customer information is company- and branch-constrained as specified; private customer pet records default to private, and sharing is purpose-specific, shop-scoped, revocable, and auditable. Sensitive access is auditable.
- Reporting distinguishes revenue, COGS, gross profit, net profit, and attribution. Sale-time cost is snapshotted. Net profit requires expense inputs. Persistent/scheduled reports are required; mail only when configured, otherwise dashboard status/history.
- Preserve `PetListing`; ordinary inventory identity is not animal identity. Sale and adoption lifecycles are separate. Adoption is free; multiple applications may be pending, but only one approved/reserved applicant may hold the animal at once. Preserve legacy Seller compatibility and listing/application history.
- Step 1B authentication decisions: **Token rotation = restore; legacy remember cookies = reauth; password change on current device = fresh; IP policy = optional; device history access = user.** Do not redesign unless a concrete conflict is found; any such conflict must be separately documented and approved.

## 6. Platform Authorization

Platform Admin and Platform Staff are platform identities/roles, not company job titles. Platform role alone creates no `business_membership`, branch assignment, product ownership, inventory access, or Seller employment.

### Platform Admin

- May perform platform moderation and platform administration only within explicitly defined platform capabilities.
- Any company intervention must use a named, narrow intervention action (such as a legal/safety moderation workflow), require an applicable platform capability, record actor, reason, target company/branch/record, before/after state, and timestamp, and be auditable.
- Must not receive implicit Owner-level company operations or company-wide customer visibility.
- If an intervention needs company data, scope and purpose are explicitly authorized and limited; access is not inferred from `isAdmin()`.

### Platform Staff

- Platform Staff has no company access by default.
- A Platform Staff account may also act as Seller only through explicit company membership and active branch assignment. Platform Staff does not become a Seller by role mapping or migration.
- Platform Staff intervention is denied by default; only an explicitly delegated platform scope allows the specific platform action. No business membership or company operation follows from that delegation.

### Separate checks

Every request evaluates platform capability and business membership independently. A platform role cannot satisfy a company policy. A business role cannot satisfy a platform moderation policy. Business query scopes never use platform role as an implicit tenant bypass.

## 7. Business Authorization

### Business roles

- **Shop Owner:** company-wide authority for the Owner's own company, subject to ownership cardinality decision and explicit transfer.
- **Branch Manager:** limited to explicitly assigned branch(es); no company-wide access by default.
- **Seller:** operationally assigned to exactly one active branch at the approved scope; no stock ownership or approval authority.
- **Customer:** global marketplace account; not a business employee. Customer permissions concern their own records and explicit company transaction relationship.

### Enforcement semantics

- Membership is an authorization prerequisite, not a UI convenience. Membership and assignment status, company/branch status, role, and action-specific policy all must be active/current.
- Revocation/suspension takes effect for subsequent requests/jobs immediately after commit; in-flight business transactions must recheck access inside the transaction before committing sensitive writes.
- A query scope limits candidate records. **Query visibility is not action authorization.** Every write/export/action separately authorizes the actor and validates current state, target company/branch, requested fields, and permitted state transition.
- Owner decisions about staff and purchase approvals must be recorded with actor and scope. No role can elevate itself by changing a submitted `company_id`, `branch_id`, `seller_id`, or role field.

## 8. Corrected Permission Matrix

Legend: **ALLOW** = role capability, still subject to valid company/branch/resource state and authorization. **DENY** = not permitted. **CONDITIONAL** = exact condition is stated in the matrix or notes. “Platform intervention only” is a separately authorized and audited platform workflow; it does not grant ordinary business powers. Customer rows describe the Customer's own account/data unless a more specific scope is written.

| Operation | Platform Admin | Platform Staff | Shop Owner | Branch Manager | Seller | Customer |
|---|---|---|---|---|---|---|
| Product creation/launch | Platform moderation/approval only where explicitly authorized | Explicit delegated platform scope only | ALLOW, own company | DENY | DENY | DENY |
| Product catalogue editing | Platform moderation only where explicitly authorized | Explicit delegated platform scope only | ALLOW, own company | DENY | DENY | DENY |
| Official price/sale-price changes | Platform exceptional authority only where explicitly authorized | DENY unless explicit platform scope applies | ALLOW, own company | DENY | DENY | DENY |
| Discount management | Platform exceptional authority only where explicitly authorized | DENY unless explicit platform scope applies | ALLOW, own company | DENY | DENY | DENY |
| Browse public catalogue | ALLOW as public/platform user | ALLOW as public/platform user | ALLOW | ALLOW | ALLOW | ALLOW |
| View operational catalogue/SKU/availability | Platform intervention only where authorized | Explicit delegated scope only | ALLOW, own company | ALLOW, assigned branch and company | ALLOW, assigned branch and company | DENY for private stock details |
| Branch creation/activation/deactivation | Platform intervention only where authorized | DENY by default | ALLOW, own company | DENY | DENY | DENY |
| Seller recruitment | Platform approval only where authorized | DENY by default | ALLOW, own company | ALLOW, assigned branch only | DENY | DENY |
| Seller suspension/removal | Platform action only where authorized | DENY by default | ALLOW, own company | ALLOW, assigned branch only; cannot affect another branch | DENY | DENY |
| Seller branch assignment | Platform intervention only where authorized | DENY | ALLOW, own company | ALLOW to own branch only; no cross-branch transfer | DENY | DENY |
| Branch Manager recruitment/transfer/removal | Platform intervention only where authorized | DENY | ALLOW, own company | DENY | DENY | DENY |
| Company settings, business policy, audit settings | Platform intervention only where authorized | DENY by default | ALLOW, own company | DENY for central/company settings | DENY | DENY |
| Branch operational settings | Platform intervention only where authorized | DENY by default | ALLOW, own company | CONDITIONAL: assigned branch and Owner-permitted branch settings only | DENY | DENY |
| Stock receiving | Platform intervention only where authorized | DENY by default | ALLOW, own company | ALLOW, assigned branch | CONDITIONAL: explicitly assigned receiving responsibility at that branch and receipt | DENY |
| Stock adjustment | Platform intervention only where authorized | DENY by default | ALLOW, own company | CONDITIONAL: assigned branch, allowed adjustment reason/threshold, required review and audit | DENY; may report discrepancy only | DENY |
| Stock transfer | Platform intervention only where authorized | DENY by default | ALLOW, own company | CONDITIONAL: assigned source/destination branches, within policy, authorized request/review; no self-approval where approval is required | DENY; may report issue | DENY |
| POS sale | Platform intervention only where authorized | DENY by default | ALLOW, own company/branch | ALLOW, assigned branch | CONDITIONAL: active assignment to sale branch and permitted POS role | DENY |
| Purchase request creation | NOT APPLICABLE | DENY by default | ALLOW, own company | ALLOW, assigned branch | ALLOW, assigned branch | DENY |
| Purchase approval | NOT APPLICABLE | DENY | ALLOW, own company | CONDITIONAL: request belongs to assigned branch; total is within Owner-configured branch limit; actor is not request creator; no aggregation/splitting bypass | DENY | DENY |
| Supplier/invoice/return/balance access | Platform intervention only where authorized | DENY by default | ALLOW, own company | CONDITIONAL: assigned branch operational records needed for duties; financial/company-wide view only if Owner explicitly permits | DENY unless explicitly limited to own request/receiving actions | DENY |
| Seller customer access | Platform exceptional scope only where authorized | DENY by default | ALLOW within own company for legitimate business administration | CONDITIONAL: branch-related transaction/customer information necessary for assigned branch operation | CONDITIONAL: only customer records associated with Seller's own sales; minimum necessary fields | ALLOW own records |
| Customer account/profile/pet updates | Platform moderation only where authorized | DENY by default | DENY, except limited legitimate company relationship access | DENY, except limited branch operational view | DENY, except own-sale minimum necessary operational view | ALLOW own profile/pets and consent settings |
| Own order/request view | Platform intervention only where authorized | DENY by default | ALLOW own company records | CONDITIONAL: assigned branch records | CONDITIONAL: only own attributed operational records as permitted | ALLOW own orders |
| Own customer order cancellation | Platform intervention only where authorized | DENY by default | ALLOW only through authorized operational workflow | CONDITIONAL: assigned branch workflow and valid order transition | DENY unless existing Seller workflow expressly permits and separately authorizes | CONDITIONAL: own order and existing cancellation rules |
| Reports and exports | Platform-scoped reports only | Explicit delegated platform scope only | ALLOW, own company | CONDITIONAL: assigned branch reports only | CONDITIONAL: own performance/sales and assigned branch stock only | ALLOW own account/order data only |
| Audit log access | Platform audit within authorized platform scope | DENY by default | ALLOW, own company business audit | CONDITIONAL: assigned branch events only where needed | DENY, except own action receipt/history allowed by policy | DENY, except own privacy/account audit view if supported |
| Adoption application management | Platform moderation only where authorized | Explicit delegated moderation scope only | CONDITIONAL: company listing only, if listing is company-owned and Owner is authorized | CONDITIONAL: assigned branch and permitted workflow, if company listing | CONDITIONAL: only listing/application duties explicitly associated with legacy Seller-owned listing or authorized assignment | ALLOW own application and consent |
| Platform user/role administration | ALLOW within platform policy | DENY unless delegated | DENY | DENY | DENY | DENY |

The conditional seller recruitment/removal permissions for Branch Managers are restricted to Sellers within the manager's own assigned branch. A Branch Manager cannot transfer a Seller to another branch or change the Seller's other company assignments.

No abbreviated permission or exception above grants platform roles automatic company operational access. Platform “approval” does not substitute for an Owner action where the approved business rules give the Owner catalogue or pricing authority.

## 9. Multi-Company Architecture

**PROPOSED — OWNER REVIEW REQUIRED.** This section defines the target tenant boundary; it is not a claim that the current application implements it completely.

### Proposed tenant boundary

Company is the commercial/business tenant. A company owns its catalogue, branch locations, stock ledger, procurement records, POS sales, company operational customer relationships, and business reports. Every company-owned operational record has a non-null company owner once it enters the new model. A branch is always a location of exactly one company.

Tenant boundary is established from the persisted resource and verified membership, not an untrusted request parameter. Company context for a request is resolved from the target record and the actor's current membership/assignment. A requested tenant ID is only a selection hint and is validated against that authorization.

### Public/global records and company records

- Global Customer `users` and public animal listings remain outside company ownership unless there is an explicit company relationship or an explicitly company-owned animal record.
- Product catalogue data is company-owned; public visibility of a published listing does not expose private stock movements, supplier data, cost, customer data, or branch operations.
- Historical sales, receipts, supplier records, and audit events retain their originating company/branch scope even if those entities later deactivate.
- Records do not transfer companies by editing `company_id`; any authorized business transfer is a distinct, audited process with history and explicit approval.

### Cross-company operations

No cross-company shared catalogue, inventory, customer account, supplier balance, employee assignment, or report access is assumed. A person may have separate memberships at multiple companies if permitted, with separate authorization contexts and history. No platform role implicitly joins tenants.

## 10. Company Ownership Model

### Compared models

| Model | Transfer and suspension | Orphan/last owner | Audit/security and approval conflict |
|---|---|---|---|
| Exactly one active Shop Owner | Transfer is a single explicit replacement transaction; cannot remove/suspend the only Owner without replacement or a controlled emergency state | Strongly enforceable last-owner protection; company can enter locked/ownerless recovery state only by explicit platform process | Clear accountability and least ambiguity; approval conflicts can be resolved by Owner or independent platform intervention |
| One primary Owner plus delegated co-owners | Primary transfer remains explicit; co-owner delegated powers must be enumerated | Primary can be protected while delegated co-owners retain limited continuity | More flexible but introduces authority hierarchy, delegation and dispute rules not present in approved rules |
| Multiple equal-authority Owners | Any Owner may act; transfer/removal needs group governance rules | Company can survive individual removal, but “last owner” means last of a quorum/group | Most ambiguous; competing changes and approval/self-approval conflicts require quorum, veto, and dispute rules |

### Recommendation and gate

**PROPOSED — OWNER REVIEW REQUIRED:** model exactly one active Shop Owner per company at a time. Make ownership transfer a serializable/audited transaction: verify current Owner, target user's verified identity and acceptance, create/activate successor, end the prior Owner's current authority, and prevent an ownerless interval except an explicit locked recovery process. No ownership may be transferred by ordinary employee assignment.

This is the simplest model consistent with singular “Shop Owner” responsibilities and explicit transfer language; it does not establish that one Owner is already an approved cardinality rule. Do not implement owner-count constraints until decision `OD-02` is approved. Until then, ownership creation/transfer lifecycle is blocked.

Regardless of selected cardinality, Owner suspension/removal must not orphan company records or erase history. Last-owner protection, recovery route, and any owner dispute procedure must be explicit and auditable.

## 11. Branch Architecture

**PROPOSED — OWNER REVIEW REQUIRED.**

- `branches.company_id` identifies exactly one owning company and is immutable in ordinary management operations.
- Branch names/codes are unique within a company (global uniqueness may be retained only if operationally justified); inactive branches cannot receive new stock, sales, assignments, or requests.
- Branch deactivation is a state change, not deletion. It is blocked or staged while active assignments, unresolved transfers, stock, open purchase orders, pending receipts, active reservations, or open sales workflows remain.
- Deactivated branches retain historical inventory movements, receipts, sales, customer associations, attribution, and reports.
- A Branch Manager may access only active assigned branches, and only the permitted operational views of those branches. Company-level settings remain Owner-only.
- All inventory, POS, receiving, transfer, and branch customer association records reference the branch of actual operation. Shared/global records do not gain branch visibility solely because a branch relationship is null.
- A branch transfer does not retcon historical sales or prior branch custody. Each event records source, destination, quantities, actor, approver, dispatch and receipt timestamps.

## 12. Membership and Assignment Model

### Alternatives

| Alternative | Current assignment lookup | History and one-active-branch rule | Owner/Manager scope, suspension/removal, duplicates and integrity | Laravel/query implications |
|---|---|---|---|---|
| **A. One authoritative membership table + separate assignment history** | Membership itself represents current role/scope; history is a separate event/snapshot table | Straightforward only if membership has canonical current branch state; history must be synchronized on every mutation. Seller uniqueness can be a unique filtered key if schema supports it. | Easy to accidentally diverge from history; null branch meaning must be exact; Owner company-wide scope and multi-branch Manager scope strain one-row representation. | Simple Eloquent lookup, but every branch change must atomically update membership and append history; policies can read current row, not history. |
| **B. Company membership for authorization + branch-assignment table for operational assignment** | Active company membership identifies business role/company; active assignment rows identify current branch scope. They are complementary, not competing current sources. | Effective-dated assignment rows (`effective_from`, `effective_to`) provide current lookup where `effective_to IS NULL`; prior rows are history. Seller current-branch uniqueness is enforced across active rows at approved global/per-company scope. | Company-wide Owner has membership and no branch assignment; Branch Manager has one or more branch assignment rows; Seller has exactly one within approved scope. Suspension on membership disables all its assignments immediately; removal closes assignment intervals and revokes membership. Unique company/user membership and active assignment constraints prevent duplicates. Branch/company consistency needs composite database constraint or transaction-safe validation. | Eloquent `BusinessMembership` parent and `BranchAssignment` child relations; policies require active membership plus applicable active assignment. Query scopes join/whereHas assignments; indexes and eager-loading are needed. Never authorize from UI selection alone. |
| **C. Unified assignment model with company-wide and branch-level assignment types** | A single assignment table stores scope type, nullable branch, role and status | One table can store effective history; current rows are `effective_to IS NULL`. Seller uniqueness depends on approved scope and filtered uniqueness. | Reduces table count but encodes role, company membership, branch scope and company-wide scope in one polymorphic record; combinations and null semantics create more constraints and awkward transitions. | Single Eloquent relation but more complex validation/check constraints and query predicates. Policies must distinguish company scope from branch scope on every query. |

### Recommendation

**PROPOSED — OWNER REVIEW REQUIRED:** adopt alternative B, with one canonical current company membership per `(user_id, company_id)` and a `branch_assignments` relation that is the sole source of current branch operational scope and assignment history.

Suggested semantics:

- `business_memberships`: `(id, user_id, company_id, business_role, status, created_by, timestamps)`. Unique `(user_id, company_id)` while active or, preferably, one row per user/company whose status is transitioned and whose role changes are separately audited. It answers: “Is this user a member of this company, in what business role, and is that membership active?”
- `branch_assignments`: `(id, business_membership_id, branch_id, status, effective_from, effective_to, assigned_by, ended_by, reason, timestamps)`. It answers: “Which branch scope is currently operational, and what was its history?”
- **Only** assignments with active status, `effective_from <= now`, and `effective_to IS NULL` grant branch scope. No `current_branch_id` cache or branch field on membership is authoritative.
- An Owner has company-wide scope from their active Owner membership; they do not need a branch assignment.
- A Branch Manager has one or more active assignments restricted to that company's branches.
- A Seller has exactly one active branch at the approved global/per-company scope; assignment cardinality is blocked pending `OD-01`.
- Suspending a membership disables access to all child assignments without deleting them. Removing a member ends assignments and changes membership state in one transaction; record remains for history.
- Reassignment closes prior assignment and inserts successor assignment in one transaction. Historical sales keep their sale-time Seller and branch IDs independently.
- Duplicate membership prevention: unique active user/company membership (or unique user/company row), and idempotent assignment command with locked membership row. Prevent duplicate active assignment to same branch.
- Cross-company branch assignment is rejected at service validation and enforced with composite constraints where supported; ordinary FKs on membership and branch separately are insufficient.

Current assignment lookup is always `business_memberships` with active company status **and** active `branch_assignments`; assignment history is the same assignment table's closed rows. Both write operations and their audit entries commit atomically. No duplicated assignment history table is needed.

### Assignment transfer, dates, and historical visibility

- Branch transfer is an Owner action or Branch Manager action limited to that Manager's own branch (a Manager cannot transfer to another branch). For Seller transfer, a company Owner approves and the service closes the old assignment and opens the new one atomically at an explicit effective time. Effective times cannot overlap for the same uniqueness scope.
- Authorization changes when the transfer commits; do not backdate access. Any scheduled future transfer is not active until its effective instant and is not accepted unless policy explicitly supports scheduled assignments.
- Sales and reports retain immutable sale-time Seller and branch attribution. A Seller's suspension ends new operational access but does not erase historical sales. Historical report visibility after suspension is **UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED** (`OD-04`); default technical proposal is limited read-only access to the Seller's own historical attribution, with customer PII redacted and subject to retention/privacy policy.
- Serialize competing assignment changes by locking membership and active assignment rows, checking uniqueness in the transaction, and relying on a database uniqueness constraint where supported. Retry only bounded serialization/deadlock errors; surface other failures.

## 13. Customer Identity and Privacy

**PROPOSED — OWNER REVIEW REQUIRED.**

### Identity and relationship separation

1. **Global identity/account:** existing global `users` account (Customer role or independent marketplace identity), one identity reused across companies; authentication credentials and account-wide profile preferences are not copied into a company table.
2. **Company-customer relationship:** proposed `company_customers` joins `(company_id, user_id)` and stores only company-local operational metadata/status. Unique `(company_id, user_id)` prevents duplicate company identities. Company can access only its relationship and associated company transactions, not another company's notes/history.
3. **Branch transaction association:** each order or POS sale has its actual `company_id` and `branch_id` where applicable. A `company_customer_id` may link the transaction if the identity is authenticated/verified; guest checkout retains the existing necessary contact/address snapshot and is not silently merged into a global Customer without a verified matching process.
4. **Seller-attributed association:** sale/order records preserve `seller_id` at the time of sale. Seller customer visibility is derived from transactions attributed to that Seller and limited to operationally necessary fields; there is no broad Seller-to-customer assignment table granting unrelated access.
5. **Consent/privacy records:** proposed consent ledger stores global customer subject, company scope, purpose, consent status, policy/version, granted/revoked timestamp and proof metadata. A share is not implied by mere purchase. Access and consent changes are auditable.

### Required behavior

- The same Customer may purchase from multiple companies with one global identity and separate company relationship rows. No per-company account/password is created.
- Company purchase history is queried through company-scoped order/sale links; no cross-company customer history is exposed.
- Owner may access legitimate customer records within their own company. Branch Manager sees only transaction-linked customer information for assigned branch and duties. Seller sees only own-sale-associated necessary data. Global customer account settings remain Customer-controlled.
- Customer pet profiles remain private by default. Share grants are purpose-specific, limited to a named company, revocable, and checked at every access/export. Consent revocation affects future access; it does not rewrite lawful historical transaction evidence.
- Sensitive access, export, and consent use are auditable with actor, subject, company, purpose, resource, action, and timestamp. Avoid logging unnecessary sensitive payloads.
- Account closure/deletion follows applicable retention rules: anonymize or detach profile data where allowed, retain legally required transaction snapshots and immutable sale/accounting evidence, and preserve non-identifying historical integrity. Do not cascade-delete orders, sales, invoices, or ledger history when a user closes an account.
- Identity matching for existing guest orders must use verified evidence and an explicit, auditable process; do not merge on unverified email/phone similarity.

## 14. Catalogue and Pricing

**PROPOSED — OWNER REVIEW REQUIRED, subject to the locked catalogue and pricing rules.**

- Company Owner is the only ordinary business actor allowed to create/launch/edit product catalogue records, categories where company-managed, official prices, sale prices, and discounts. Branch Manager and Seller are read/search-only for catalogue/SKU and permitted availability information.
- Platform moderation is limited to explicit platform policy actions. It must not be implemented as general product/pricing CRUD or an implicit Admin bypass.
- Products have company scope. Branch-specific quantity and availability live in branch inventory, not duplicated product rows. Keep product details shared at company level unless an explicitly justified catalogue variation is approved.
- Product variants are not a foundation requirement. Add only where each variant has distinct SKU/identifiable stock or branch requirements; historical line items must snapshot variant/SKU description.
- Referenced products are archived/deactivated. Preserve stable product ID and snapshots; block hard-delete when referenced by order, POS sale, movement, procurement, or report history.
- Snapshot sale-time product name/SKU/variant, official unit price, applied discount, currency, unit cost, quantity, and line totals. Current price or cost changes never alter a historical sale.
- Catalogue validation checks company/category consistency and seller-provided IDs cannot alter company or price. Writes run through an Owner-authorized service and an audit record.

## 15. Company-Owned Inventory

**PROPOSED — OWNER REVIEW REQUIRED, subject to the locked inventory rules.**

### Proposed inventory quantities

Per `(company_id, branch_id, product_id[, variant_id])`, maintain stock ledger-backed quantity:

- **On Hand:** physically received and not dispatched/consumed stock, with adjustments only through approved movement types.
- **Reserved:** quantity committed to a valid online order or other approved hold and not yet released/fulfilled.
- **Available:** `On Hand - Reserved`; it must never be negative.

The stored balance is a transactional projection of immutable stock movements, not an independently editable count. A rebuild/reconciliation process compares projection to movements; corrections are new audited movements, not history edits.

### Movements and operations

- Every receipt, sale, reservation, release/cancellation restoration, transfer dispatch/receipt, approved adjustment, damage, expiry, return, or discrepancy resolution has a typed movement with company, branch, product/variant, signed quantity, reason/source reference, actor, time, and applicable approver.
- Online order reservation/cancellation restoration preserves existing behavior and is made idempotent. Cancellation, reservation release, and quantity updates occur in one transaction, locking affected rows and preventing double restoration.
- Seller may view assigned branch quantities, report damage/expiry/discrepancy/receiving issues, and confirm a receipt only when explicitly assigned. Seller cannot independently create an adjustment movement.
- Branch Manager may perform only allowed branch operations subject to Owner policy, limits, reasons, and review. Owner controls company-wide inventory rules.
- Transfers have request, approval where required, dispatch decrement/in-transit state, receiving confirmation, quantity verification, discrepancy resolution, and completion. In-transit quantity is not available at either branch; partial receipts and loss/damage are explicit.
- Negative on-hand and available quantities are prohibited. Concurrent sale/receive/transfer operations lock stock rows or use atomic conditional updates and verify affected-row counts.
- Inventory valuation is a report based on a declared costing method and complete cost records. Do not claim company valuation where opening balances/cost basis are not verified.

## 16. Sales and POS

**PROPOSED — OWNER REVIEW REQUIRED, subject to preserving the locked online/payment behavior.**

### A. Proposed POS sale lifecycle

`Draft → Completed` after each line, branch, Seller attribution, available quantity, and required completion data are validated and inventory is atomically committed; `Draft → Voided` only before completion. A completed sale is immutable. Corrections, returns, or voids after completion require explicit audited adjustment/return records; they do not delete/rewrite the completed sale. This is a proposed operational lifecycle, not payment processing.

At minimum: branch, company, Seller/actor attribution, sale timestamp, sale items and product/variant snapshots, quantity, official unit price/discount, unit-cost snapshot if verified, totals/currency, stock movement references, receipt number, and immutable completion audit. Multi-item sale is one transaction. Receipt printing is a presentation of persisted sale facts.

**Payment status is not a POS sale status.** POS sale completion does not introduce payment gateway, reconciliation, refund ledger, Seller payout, commission, or settlement behavior. Existing in-person/COD/payment behavior must be explicitly mapped during implementation without fabricating a new payment state.

### B. Existing payment lifecycle

Verified current `PaymentStatus` enum is `pending`, `paid`, and `refunded`. Existing checkout sets COD and pending; [FEATURES_AND_ROLES.md](../FEATURES_AND_ROLES.md) describes current delivered/COD behavior. Preserve this existing payment lifecycle and behavior. No new status, method, gateway, payment collection, reconciliation, or refund workflow is proposed in this remediation.

Any discovery of a concrete incompatibility between existing Step 1B or current payment behavior and a required sale flow must be raised for Owner approval before implementation; do not quietly change `PaymentStatus`.

### C. Online order lifecycle

Preserve current `OrderStatus` transition rules and checkout/reservation/cancellation-restoration semantics. Online orders remain online order records, not POS sales. Multi-item online cart support may be added later with backward-compatible one-item order support and item snapshots; no existing one-item order is reinterpreted.

### Inventory coordination

Online reservation and POS completion serialize on the same `(company, branch, product/variant)` stock balance. A reservation reduces Available by increasing Reserved (or equivalent ledgered commitment); completion/fulfillment consumes the reservation exactly once; cancellation restores it exactly once. POS sells only unreserved Available quantity. Transaction locks/conditional updates, idempotency keys, and ledger references prevent oversell, duplicate completion, or duplicate restoration.

The branch fulfilling each online order must be explicit and auditable. It must not be inferred from Seller identity or a default branch. Existing orders without a verified branch retain unknown/unassigned legacy scope until an approved mapping is made.

## 17. Procurement and Suppliers

**PROPOSED — OWNER REVIEW REQUIRED, subject to the locked procurement scope and deferrals.**

### In-scope architecture

- **Suppliers:** company-owned business records; stable identity, status, contact/legal attributes, terms/reference metadata. Do not assume a supplier is shared across companies.
- **Purchase requests:** requester, company, branch, reason, requested items/quantities/estimated amounts, current state, and approval history. Seller and Branch Manager can request for permitted branch; Owner can request company-wide.
- **Approval limits:** Owner configures branch-specific monetary limit, currency, and effective period. Branch Manager approval allowed only within limit, for assigned branch, when not the requester, and after transaction-time checks. Above limit routes to Owner. Seller has no approval permission.
- **Purchase orders and items:** issue from approved requests; record supplier, branch, currency, agreed quantity/price/tax/discount snapshots, and open/partial/received/closed/cancelled state as procurement state (not payment state). Avoid purchase-order creation outside approved authorization.
- **Request-splitting prevention:** aggregate pending/approved requests by company, branch, supplier, requester or related request, and time/defined procurement window against approval thresholds. Flag suspicious grouping and block obvious same-purpose threshold evasion where policy defines a grouping rule. Do not invent a universal time-window/business rule; Owner must define it before enforcement (`OD-05`).
- **Supplier invoices:** preserve supplier invoice identity, date, currency, lines/totals/tax, linked PO/receipts, discrepancy/status, and immutable revision/audit. This records an obligation/evidence, not a payment execution.
- **Partial goods receipts:** each receipt references PO lines, received quantities, actor, assigned responsibility, branch, lot/expiry if applicable, and discrepancies. Multiple receipts may fulfil one order; no automatic stock increase for unverified quantities.
- **Discrepancies:** record short/over/damaged/wrong goods, evidence, resolution, supplier claim/return reference, inventory movement consequences, and approval. Do not silently modify ordered quantity or invoice.
- **Supplier returns:** explicit return authorization, items/quantities, reason, dispatch/receipt confirmation and supplier credit/adjustment evidence, linked to original receipt/invoice/stock movement. Returns reduce stock only when physical custody changes, with a matching movement.
- **Supplier financial history/balance:** immutable supplier invoice/credit/return/adjustment records and a derived company-specific balance/history, with reconciliation provenance. Supplier balance must not be edited as an arbitrary number. It is an accounting position, not payment execution.
- **Audit:** request/approval/limit changes, PO revisions, invoice receipt, partial receiving, discrepancy/return resolution, supplier balance adjustments, and actor attribution are append-only audit events. Approval decisions re-check current membership, request state, self-approval, limit, and duplicate/splitting condition under row locks in a single transaction.

### Deferred

Supplier payment execution, bank/COD settlement, payment-provider integration, payment status redesign, remittance, and payment reconciliation are deferred. Supplier invoice obligations, credit/return history, and balances remain required in scope and must not be omitted on that basis.

## 18. Reporting and Financial Integrity

**PROPOSED — OWNER REVIEW REQUIRED, subject to the locked financial-integrity rules.**

- **Revenue:** report gross sales and any subsequent returns/adjustments using explicitly defined sale/payment basis; do not call unpaid order totals cash received.
- **COGS:** sum historical verified sale-time unit cost × sold quantity, adjusted only through explicit returns/corrections. Unknown cost remains unknown, not zero or current catalogue cost.
- **Gross profit:** revenue basis minus verified COGS and explicit adjustments. Show excluded/unverified components.
- **Net profit:** gross profit minus captured expense inputs. Since expense capture is not established by this architecture, do not claim net profit until a separately approved expense model and completeness standard exist.
- **Seller attribution:** attribute line/sale according to the recorded Seller at transaction time. This is a performance metric, not payout, ownership, commission, or payroll entitlement.
- **Branch contribution:** associate sale, stock movement, procurement and return to actual branch; do not count transfers as sales/revenue.
- **Returns/adjustments:** preserve original sale and add linked return/adjustment entries; reports state gross, returns, net revenue, and adjustment policy separately.
- **Supplier balance:** show invoice/credit/return obligation history and derived balance by company. Do not report it as paid cash or as cash-flow reconciliation.
- **Inventory valuation:** report only where quantity and cost basis/costing method are reliable and documented. Reservations, in-transit stock, expired/damaged goods, and returns are separately represented.
- **Snapshots/history:** sale-time costs/prices and report-period calculation version are immutable. Scheduled report output includes generation time, company/branch scope, data-through timestamp, filters, and calculation version. Persistent report history records status, output location/retention, and failure.
- Email Owner only when mail is configured; otherwise persist a dashboard status/history and surface delivery unavailability.
- Seller views: own sales, units, revenue, verified cost, attributed gross profit, adjustments/returns, assigned branch stock. Branch Manager views only assigned branch sales, Seller performance, purchases, movements, low stock, receiving, returns, and reliable gross profit. Owner views own-company sales, comparisons, Seller/product performance, procurement/suppliers, reliable valuation, gross profit, adjustments, and alerts.

## 19. Animal and Adoption Architecture

**PROPOSED — OWNER REVIEW REQUIRED, subject to preserving the locked animal/adoption rules and legacy compatibility.**

### Ownership and identity

- Preserve the existing global marketplace `PetListing` and its legacy `seller_id`, listing type/status, images, moderation, and application/order compatibility.
- A `PetListing` is not presumed company-owned. Existing Seller-created listings remain Seller-owned marketplace content unless an explicit verified company relationship is added.
- Where a company owns/holds an animal, use a distinct animal identity record with company ownership, identity/provenance, welfare and custody history. A listing is a public/transactional representation of an animal, not the animal's stock quantity.
- Branch custody/location is a historical relationship/movement; current location is one authoritative current custody assignment. Custody movement records retain source, destination, actor, reason, and timestamps.
- Listing owner/publisher (legacy Seller or authorized company) is explicit and distinct from animal owner/custodian. Do not auto-convert marketplace Seller to employee or company.

### Separate lifecycles

- Animal sale: `Available → Reserved → Sold`. Reservation is exclusive and is released on valid cancellation/expiry; completed sale history remains.
- Adoption: `Available → Application Pending → Approved → Adopted`. Adoption is free. Multiple applications may be pending; at most one applicant may hold approved/reserved status. Approval transaction locks the animal and verifies no current approved holder; competing applications cannot both succeed.
- Sale reservation and adoption approval are mutually exclusive holds on an animal. Adoption must not use a paid order/payment state; sale must not be represented as adoption.
- Existing generic `PetListingStatus` draft/moderation/publish/pause/completed status remains a listing publication lifecycle and must not be conflated with animal sale/adoption lifecycle.

### Applications and access

- Adoption application belongs to a global Customer identity, a specific listing/animal, and its application history; company/branch access is added only where the listing is company-owned or a specific operational assignment justifies it.
- Applicants can view/manage their own application. Listing owner can review applications for that listing under privacy policy. Company Owner/assigned Branch Manager access is limited to company-owned animal workflows. Platform moderators see only explicit moderation scope. Legacy Seller can continue existing ownership-based listing/applicant workflows without being treated as an employee.
- Retain application decisions, reviewer, timestamp, reason, withdrawal, and current approved-holder uniqueness; account closure must not delete required animal/adoption history.

## 20. Security Threat Model

**PROPOSED — OWNER REVIEW REQUIRED.** These are future implementation requirements; they are not claims of complete current coverage.

| Threat | Required control |
|---|---|
| Cross-company object reference / forged tenant ID | Resolve scope from persisted record; service and policy check membership; scoped binding/query; validate all related IDs share company; test guessed IDs on read and write. |
| Cross-branch access | Require active assignment for every branch action; query filter and per-action policy; branch-context parameter is not authorization. |
| Seller customer-data scraping | Query customer associations from Seller's own attributed sales only; return minimum operational fields; authorize export separately; audit sensitive access. |
| Product or price escalation | Owner-only policy and service checks; immutable price/cost snapshots; validate category/product company; never trust submitted price, role, or company. |
| Branch Manager privilege escalation | Explicit operation policies, restricted fields, branch assignment checks, no product/pricing/manager-management powers, policy tests for hidden endpoints and Livewire. |
| Purchase approval bypass/self-approval | Lock request and limit rows; re-check current requester, actor, amount, branch, state, approval limit, no-self-approval, request aggregate/splitting signals; write approval and audit atomically. |
| Membership suspension/removal race | Membership/assignment state rechecked inside transaction and at job execution; revoke current assignment at commit; do not rely on cached/UI state. |
| Assignment transfer race | Lock membership and active assignment rows; one transaction closes old and inserts new; database uniqueness backstop; serialize/reject overlapping active periods. |
| Unauthorized exports and report leakage | Export policy with same company/branch/Seller scope as source rows; row-level filters inside export query; audit actor, scope, filters, outcome; signed/expiring downloads. |
| Wrong-company background job | Persist explicit company/resource identifiers; reload and authorize current membership/record at execution; no ambient global company context; idempotency and audit. Scheduled system jobs use narrowly defined service capability, not a user role. |
| Route-model binding bypass | Scoped binding or explicit policy after binding; reject mismatched company/branch relation; test nested resources and direct URLs. |
| Concurrent inventory oversell or duplicate receipt | Transactional stock locks/conditional update, non-negative checks, idempotency, ledger uniqueness; verify affected row counts. |
| Duplicate adoption approvals | Lock animal/application; enforce one approved active applicant through DB constraint where supported plus transactional policy. |
| Audit tampering or PII leakage | Append-only application interface, restricted audit read access, redact secrets/PII, include actor/scope/reason; database privileges/retention policy reviewed for deployment. |
| Platform privilege leaking to business | Independent authorization domains; no `isAdmin()` shortcut for company access; intervention workflow is explicit, scoped, logged, and tested. |
| Step 1B regression | Preserve approved token/session decisions exactly; add regression tests around business membership/login and device behavior without redesigning authentication. |

### Enforcement surfaces

- **Middleware:** authenticate and establish platform/business context, but do not replace policies.
- **Policies:** authoritative per-action checks for resource, membership, scope, state transition, and role capability.
- **Services/actions:** reauthorize at mutation boundary; validate cross-entity company consistency; transact and audit.
- **Model scopes:** defense-in-depth query visibility only. Avoid unsafe global scopes for system jobs/public data; provide explicit scoped query services and narrowly reviewed system contexts.
- **Controllers/Livewire:** authorize every action, including hidden buttons, bulk actions, uploads, downloads and component events.
- **Route-model binding:** scope nested records and still run policy authorization.
- **API endpoints:** apply the same policy/service boundary if introduced; no API exclusion from tenant rules.
- **Jobs/scheduled tasks:** carry explicit tenant/resource context; reload current state, verify scope/capability, audit system action, and fail visibly on invalid context.
- **Exports:** authorize independently and apply row-level filters before serialization.

## 21. Database Integrity

**PROPOSED — OWNER REVIEW REQUIRED.** Entity-relationship design below is conceptual and must be reconciled with the verified production engine and approved migration scope.

### Core integrity requirements

- **Branch company:** `branches.company_id` non-null, FK to company, branch key unique within company. For any record with both company and branch, require that pair to reference the same `(company_id, branch_id)` pair in `branches`.
- **Membership branch:** selected model stores branch on `branch_assignments`; require assignment membership company equals branch company. A FK from assignment to membership and separate FK to branch does not establish this equality.
- **Seller active assignment:** exactly one active branch at approved global/per-company scope. Prevent overlapping active/effective periods and duplicate active branch assignment; enforce with filtered/partial unique constraint where supported or carefully designed portable strategy plus serializable service/locking and validation.
- **Duplicate membership:** unique `(user_id, company_id)` for one membership record, or active-only uniqueness if historical membership records are separate. Do not rely on pre-insert `exists()` alone under concurrency.
- **Role/scope compatibility:** Owner has company scope and no branch assignment; Branch Manager may have multiple same-company branch assignments; Seller has the approved cardinality; Customer is not a business employee role. Use check constraints where expressible and validate in transaction services.
- **Lifecycle state:** membership, assignment, company, and branch have explicit active/suspended/removed/closed status and timestamps. Removal/deactivation does not delete history.
- **Historical retention:** sales, movements, approvals, assignments, audit and financial histories use restrictive deletion/archival and stable references; snapshots protect records if source catalogue/customer identity is later removed/anonymized.
- **Non-negative and monetary integrity:** integer minor units with currency, positive quantities where required, `reserved <= on_hand`, and unique/idempotent source movement references. Avoid floating-point financial arithmetic.
- **Adoption exclusivity:** one active approved applicant per animal/listing at a time, with transactional lock and a database uniqueness backstop when engine supports it.

### Portable Laravel versus database-specific controls

- Laravel validation/service checks are necessary for meaningful errors and domain rules, but alone are race-prone and cannot guarantee cross-company consistency under concurrent writes.
- Standard single-column foreign keys enforce referenced-row existence only. They do **not** ensure `assignment.company_id == branch.company_id`, nor that a record's branch belongs to its company.
- Composite foreign keys and check constraints can enforce more integrity but support, Laravel schema APIs, migration syntax, and SQLite behavior differ by database engine/version.
- Partial/filtered unique indexes are useful for “one active assignment” but are not portable in identical form across SQLite, MySQL/MariaDB, PostgreSQL, and SQL Server.
- Recommended implementation uses portable unique/FK constraints where reliable, transactional domain validation and row locking, and engine-specific constraints only after production engine/version is verified. Record each engine-specific constraint and provide equivalent SQLite test coverage or an integration test on the production engine.
- SQLite in tests may not reproduce production row-level locking, isolation behavior, filtered uniqueness, FK enforcement configuration, collation, JSON, or DDL behavior. Do not certify concurrency or production portability from SQLite-only tests.
- Production engine is **unverified**. No vendor-specific DDL should be selected until it is known and reviewed.

### Revised conceptual entity-relationship design

The following entities are proposed. “Foundation” means required early in the approved implementation roadmap; it does not authorize creation during this documentation task. Every company-owned row derives company scope from its parent or stores it explicitly where denormalized scope is needed for constraints/reporting. A duplicated company ID must be constrained to match the parent relationship.

| Entity | Purpose | Ownership scope and primary relationships | Important integrity constraints | Target phase |
|---|---|---|---|---|
| `companies` | Tenant and business settings owner | Root business entity; has branches, memberships, catalogue, stock, sales, procurement and customer relationships | Active/deactivated state; immutable identity; no implicit default company; last-Owner protection per OD-02 | Foundation |
| `branches` | Physical/operational stock and sales location | Exactly one company; parent of assignments, branch inventory, movements, POS sales, receipts and branch-scoped orders | Company FK; unique `(company_id, code)`; deactivation guard for unresolved activity; composite company/branch consistency | Foundation |
| `users` | Global authenticated identity for platform, Seller, and Customer accounts | Global; linked to company memberships, customer relationships, orders, listings and audit actors | Unique account identity; role domain separation; account closure preserves required snapshots/history | Existing global identity; additive extension only |
| `business_memberships` | Authorizes a user in a company with business role/status | One user and one company; parent of branch assignments | Unique `(user_id, company_id)`; explicit active/suspended/removed state; only business roles; Owner cardinality gated by OD-02 | Foundation |
| `branch_assignments` (also assignment history) | Sole source of current branch operational scope and past assignments | Child of membership; references one branch of same company; effective-dated | Same-company constraint; no duplicate/overlapping active assignment; Seller cardinality gated by OD-01; close old/open new atomically | Foundation |
| `products` | Company-owned catalogue identity/details | One company; category; optional variants; referenced by stock, sales and procurement | Owner-only writes; immutable referenced IDs; archive instead of delete; tenant-consistent category | Foundation |
| `product_categories` | Company catalogue classification | One company; has products | Unique slug/name policy within company; referenced categories retained/archived; company consistency | Foundation |
| `product_variants` (conditional) | Distinct SKU/stock identity only when required | Child of product; may be key for inventory, sale and PO lines | Unique SKU in company; create only if distinct SKU or branch-stock behavior justifies it | Later, only if justified |
| `branch_inventory` | Transactional current On Hand/Reserved projection per stock-keeping unit and branch | Company + branch + product/variant; derived from stock movements | Unique stock key; branch/company consistency; `0 <= Reserved <= On Hand`; projection reconciles to ledger | Foundation |
| `stock_movements` | Append-only source of stock changes | Company and branch; product/variant; source order, sale, transfer, receipt, return or adjustment | Immutable movement; idempotent source reference; authorized reason/actor; cannot create negative stock | Foundation |
| `stock_transfers` and transfer items/events | Request, approve, dispatch, receive, verify and complete inter-branch transfers | One company, source/destination branches, items, requester/reviewer/receivers | Both branches belong to company; no unapproved self-approval; in-transit and partial quantities reconcile to movements | Later phase 3 |
| `orders` | Existing online order lifecycle | Global Customer optional; company/branch when verified; has items, payment fields, reservations, Seller attribution where valid | Preserve existing status/payment semantics; no guessed branch; immutable customer/product snapshots; idempotent reservation/cancel | Foundation compatibility |
| `order_items` | Online order line snapshots | Child of order; optional product/variant FK plus snapshots | Positive quantity; price/discount/cost snapshots; archive-safe FK; company consistency | Foundation compatibility |
| `pos_sales` | Separate completed physical POS transaction | Company + branch; Seller/actor; Customer relationship optional; sale items and stock movements | Separate POS and payment states; immutable completion; one atomic multi-item transaction; unique receipt/idempotency | Later phase 5 |
| `pos_sale_items` | Immutable POS line and attribution/financial snapshots | Child of POS sale; optional product/variant FK | Positive quantity; unit price/cost snapshot and currency; product/company consistency; corrections via linked records | Later phase 5 |
| Global Customer identity | Customer account and profile, represented by global `users` identity as applicable | Global, not company-owned; may relate to many companies | One reusable account; no duplicate credentials per company; closure/anonymization per OD-07 | Existing / foundation compatibility |
| `company_customers` | Company-specific relationship and operational status | Unique company + global customer; related to that company's orders/sales | Unique `(company_id, user_id)`; no cross-tenant history; do not make Customer an employee | Foundation privacy |
| Transaction/customer association | Branch and Seller link to a customer for a specific sale/order | On order/POS sale, tied to company, branch, optional company-customer and Seller-at-time | Seller access derives only from own attributed transaction; preserve guest contact snapshot; verify identity before linking | Foundation compatibility |
| `customer_consents` | Purpose-specific company-scoped sharing and revocation history | Global customer + company + purpose/version; optional pet profile/share subject | Append-only grant/revoke history; no implicit consent; scoped, revocable and audited | Foundation privacy |
| `suppliers` | Company-local supplier identity | One company; related to requests, POs, invoices, receipts, returns and balance entries | Unique company supplier key; deactivation preserves history; no cross-company sharing by assumption | Later phase 6 |
| `purchase_requests` | Requisition and approval workflow | Company + branch/requester; request items and approval events | Self-approval prevented; active branch scope; currency/limit checks; split grouping policy OD-05 | Later phase 6 |
| `purchase_orders` | Authorized supplier commitment derived from approval | Company, supplier, branch; items, invoices and receipts | Link to approved request/approval; immutable issued snapshots; edits versioned/audited | Later phase 6 |
| `purchase_order_items` | Ordered quantity/price snapshot | Child of PO; optional product/variant | Positive quantity; currency and monetary precision; ordered/received/returned reconciliation | Later phase 6 |
| `supplier_invoices` | Supplier invoice evidence/obligation, not payment | Company + supplier; links PO, items and goods receipts | Unique supplier invoice identity within supplier; immutable revisions; currency/amount and discrepancy audit | Later phase 6 |
| `goods_receipts` and receipt items | Full/partial physical receiving record | Company + branch + PO; assigned receiver; stock movements | Received quantity never exceeds policy; partial receipts supported; discrepancy resolution; stock change linked exactly once | Later phase 6 |
| `supplier_returns` and items | Return/dispute of received goods | Company + supplier; linked receipt, invoice, PO and stock movement | Return authorization and quantities bounded by received/unreturned amount; stock movement at custody change | Later phase 6 |
| Supplier balance/history ledger | Derived supplier obligation/credit history from invoices, credits, returns and adjustments | Company + supplier; source-document links | Append-only entries; derived balance reproducible; never treated as payment execution; correction through new entry | Later phase 6 |
| Animal identity | Distinct individual animal record where company-owned/held | Company-owned only when verified; linked to custody and optional listings | No implicit ownership from legacy listing; distinct from product quantity; lifecycle/history retained | Later phase 7 |
| `PetListing` / animal listing association | Public listing and compatibility layer for sale/adoption/rescue | Existing global Seller-owned listing or explicitly linked company-owned animal | Preserve legacy Seller owner/type/status; company association only when verified; listing state separate from animal lifecycle | Existing compatibility; later additive link |
| Animal custody history | Track current branch/location and past custody | Animal; branch/company where applicable; actor and custody events | One canonical current custody; branch must belong to company; append-only transitions | Later phase 7 |
| `adoption_applications` | Applicant history and review lifecycle | Global Customer + listing/animal; company relationship only when relevant listing is company-owned | Multiple pending permitted; only one active approved holder per animal; free adoption; transactional concurrency guard | Existing compatibility / later enhancement |
| `audit_logs` / approval events | Sensitive business/platform action evidence | Actor, company/branch/resource, action, reason, before/after summary | Append-only interface; least-privilege reads; no secrets/unnecessary PII; platform vs business scope explicit | Foundation |
| Reporting snapshots/history | Persist generated/scheduled report scope, version and delivery status | Company/branch and report period; source-data watermark | Immutable snapshot/version; role-scoped output; retention/exports governed by OD-07; no unsupported net profit | Later phase 8 |

No separate assignment-history table is recommended: closed rows in `branch_assignments` are the history. No duplicated company-customer identity or supplier balance “current value” is authoritative; those are links/derived views over the global identity and financial history respectively.

## 22. Legacy Data and Migration Strategy

No migration is run or authorized by this specification. No default company is silently created. The pending company migration [2026_11_10_000000_add_company_branch_business_access.php](../../database/migrations/2026_11_10_000000_add_company_branch_business_access.php) proposes default-company backfill and Staff memberships; those behaviors require explicit correction/approval before any execution.

| Stage | Preconditions and data affected | Read/write behavior | Validation and approval | Recovery |
|---|---|---|---|---|
| 1. Read-only inventory | Approved scope, protected verified backup, production-like read-only access. Enumerate users, roles, branches, products/categories, orders/items, listings, audit, stock, identities, foreign references and orphan/ambiguous ownership. | Read-only queries/reports only; no database or files changed. | Reconcile counts, relationships, null/unmapped records, time ranges, business owner evidence. Owner approves inventory and data-classification report. | No data recovery needed; discard report if inaccurate and repeat read-only. |
| 2. Identify records without verified company | Stage 1 inventory. Mark each candidate record with evidence and uncertainty. | Read-only classification; unresolved rows remain unassigned, quarantined from new company operations, not deleted or guessed. | No inferred company from app name, global settings, Seller role, email domain, or current UI visibility. Owner/business evidence review. | Preserve source rows; remove only the classification artifact if invalid. |
| 3. Explicit company onboarding | Verified company legal/operational identity, branch list, contacts, policies, owner candidate, company timezone/currency decisions if required. | Create new company through approved controlled onboarding in a later implementation phase; do not create implicit PawHaven/default company. | Owner/legal authority verifies each company and its branches; record evidence/approver. | Disable onboarding record, preserve audit; forward correction or explicit cancellation, no cascade deletion. |
| 4. Owner verification | Onboarded company and proof of authority/identity. | No role conversion from platform Admin/Staff or marketplace Seller. Proposed Owner membership only after verification and acceptance. | Independent confirmation; test no ownerless or duplicate ownership under selected model; explicit Owner approval. | Revoke unconfirmed membership and return company to onboarding/locked state, preserving audit. |
| 5. Verified record mapping | Company/branch mapping evidence for each legacy product, category, order, pet listing, inventory-like quantity, audit event and supplier record. | Stage mapping in a reversible, reviewable mapping set; no destructive row moves or default assignment. Existing IDs/data remain intact. | Reconcile counts and totals by record type; ambiguous history remains unassigned; Owner approves mapping per company. | Remove/replace mapping entries; do not rewrite source data until a separately approved cutover. |
| 6. Explicit employee assignment | Verified user identity and company/role/branch invitation/acceptance. | Create membership/assignment only after explicit approval and acceptance. No automatic Staff/Seller conversion. | Confirm no duplicate identities, cross-company/branch mismatch, Seller branch cardinality and role boundaries. Owner authorizes each initial assignment. | Revoke membership/close assignment with audit; account remains global and historical records remain. |
| 7. Branch integrity verification | Verified branch/company relationships and assignments. | Validate imported/mapped data; block invalid branch operations. No guessing missing branch from Seller/order. | Foreign-key/consistency, stock reconciliation, branch totals and assignment checks; Owner signs branch roster. | Keep invalid rows in legacy/quarantine state, allow read-only legacy view under restricted access; repair mappings forward. |
| 8. Compatibility period | Additive schema and dual-read/controlled compatibility code approved and tested; backup/restore rehearsal. | Existing marketplace/online order paths continue; new company path is feature-gated. Dual-write only if transactionally safe and a source of truth is defined; never make two authoritative copies. | Compare old/new read results, stock conservation, order/payment behavior, privacy, authorization and audit; Owner approves entry/exit. | Disable new path/feature flag and restore old reads; forward-repair additive data. No rollback that discards accepted transactions. |
| 9. Controlled cutover | Completed compatibility gates, approved user notices/support, tested backup, monitored rollback/recovery plan. | Route a defined company cohort to new operations; freeze/serialize incompatible legacy writes as needed. No global silent cutover. | Owner and operational approver authorize cohort/time; reconcile totals, open orders/reservations, stock, pending procurement and reports. | Pause writes to new workflows, revert routing only if data ownership is preserved; reconcile dual-written events before resume. |
| 10. Post-cutover validation | Cutover event log, monitoring, named operators and rollback window. | Read/monitor, then apply approved forward repairs; no destructive cleanup during validation. | Compare counts, movements, stock, orders/payments, assignment access, customer privacy, supplier history, report totals and audit completeness. Owner accepts each company cohort. | Pause affected capability, restore from verified backup only under approved procedure, replay idempotent events, forward-fix. Preserve forensic/audit data. |

### Compatibility and rollback

- Prefer additive schemas, nullable staging references, backfills with explicit mapping tables, idempotent forward repair, and deprecation windows.
- A migration `down()` is not a safe recovery strategy for destructive data changes: dropping a newly populated table/column or collapsing assignment/movement history destroys post-migration writes. A successful `down()` execution does not restore lost data or business consistency.
- For irreversible or lossy changes, do not promise rollback. Use a new forward migration, audited compensation, or verified restore-and-replay procedure with explicit approval.
- Backups must be tested, isolated, access-controlled, and include database plus uploaded assets and relevant configuration/secret recovery plan. Do not test by overwriting a live database.
- No legacy `PetListing`, user role, branch, product, order, customer identity, staff role, or stock ownership may be converted without verified mapping and approval.

## 23. Regression and Security Test Matrix

| Area | Required tests and acceptance evidence |
|---|---|
| Platform/business separation | Admin/Staff without membership denied company reads/writes; Owner without platform role denied platform administration; explicit platform intervention only reaches authorized target and creates audit. |
| Permission matrix | Table-driven allow/deny tests for every role and operation; explicitly test product create/edit/price denied to Branch Manager/Seller, Owner limited to own company, Staff not auto-assigned. |
| Tenant/branch isolation | Cross-company and cross-branch guessed IDs for pages, nested binding, exports, Livewire actions and jobs return denial/no data; same-company valid reads work. |
| Membership lifecycle | Duplicate membership prevention, suspension immediate effect, removal/history retention, Owner transfer, assignment effective dates, no overlapping active Seller assignment, concurrency races. |
| Customer privacy | Customer reuse across companies; no cross-company history; Seller own-sale-only; Branch Manager branch-only; consent purpose/scope/revocation; account closure preserves required historical snapshots; export denial. |
| Catalogue and history | Owner-only CRUD/prices; no hard-delete for referenced product; sale snapshots unchanged after catalogue price/cost updates; categories/product/variant cross-company rejected. |
| Inventory | Available = on hand - reserved; negative prevention; concurrent order/POS oversell test on production engine; cancellation restoration exactly once; transfer dispatch/partial receive/discrepancy conservation; authorized adjustments only. |
| Online regression | Existing one-item checkout works unchanged; existing order status transitions/payment statuses preserved; reservation and cancellation restoration preserved under failures and retry; no new payment status/provider. |
| POS | Multi-item same-branch sale, SKU/name search, price/cost snapshots, attribution, receipt, invalid/out-of-stock rejection, rollback atomicity, void/correction policy and idempotency. Sale lifecycle does not mutate payment model. |
| Procurement | Seller/Manager requests; Seller denied approval; Manager within limit/no self-approval; above-limit Owner approval; branch-limit update race; split-detection cases; partial receipt, invoices, discrepancies, returns and supplier balance history; no payment execution. |
| Animals/adoption | Legacy Seller listing compatibility; unowned/global listings not auto-company-owned; distinct animal vs stock; sale/adoption separation; multiple pending applications; concurrent approval permits only one holder; free adoption; history retained. |
| Reporting | Revenue/COGS/gross profit separated; unknown cost not treated as zero; historical profit immutable; no net profit absent expense inputs; branch/Seller attribution; return adjustments; supplier balance not marked as payment. |
| Auditing | All sensitive membership, price, stock, transfer, procurement, consent, customer view/export, ownership and intervention events include actor/scope/time/reason and avoid secret/PII overcollection. |
| Background/scheduled work | Job reloads current company/membership/record state, wrong-company context denied, revocation respected, duplicate execution idempotent, email configured/unconfigured outcomes visible. |
| Database integrity | Cross-company branch references rejected; FK/unique/check constraints exercised; migration tests on verified production engine; SQLite and production-engine differences documented. |
| Step 1B | Regression tests preserve: token rotation=restore; legacy remember cookies=reauth; password change on current device=fresh; IP optional; device history access=user. No redesign without documented concrete conflict and Owner approval. |
| Migration compatibility | Stage fixtures include ambiguous/unmapped legacy rows, no-default-company proof, counts/reconciliation, repeated idempotent mapping, forward repair and recovery rehearsal. |

Tests must prove measured behavior, not merely route response shape. At least one concurrency/integrity suite must run on the verified production database engine before production release; SQLite-only results are insufficient for lock/filtered-index certification.

## 24. Revised Implementation Roadmap

**Architecture approval is separate from implementation approval. No phase is started by this document.** Each phase requires its own Owner authorization, protected backup/recovery plan, and evidence-based acceptance.

| Phase / dependencies | Deliverables and database impact | Compatibility, tests and acceptance criteria | Recovery and Owner gate |
|---|---|---|---|
| **0. Decision and evidence gate** — prerequisite to all implementation | Approve unresolved decisions; verify production engine/version, deployment and data ownership; finalize entity/state/retention policies. No schema change. | Current behavior baseline: online order/payment/cancellation, legacy listings, Step 1B; approved permission matrix and data inventory. | Stop without changes. Owner signs decisions and phase scope. |
| **1. Identity, tenant foundation, membership/assignment** — Phase 0 | Additive companies, membership, branch assignments/history, company/branch consistency constraints, status/audit; no implicit default company or role conversion. | Backward-compatible legacy reads; test all role and scope boundaries, branch transfer, concurrency, deactivation, cross-company denial. | Additive forward repair; feature flag and restricted legacy behavior. Owner approves company onboarding/mapping before writes. |
| **2. Verified onboarding and legacy compatibility** — Phase 1 plus staged inventory and mappings | Explicit onboarding UI/process, Owner verification, record mapping review, explicit employee assignment; only approved records gain company scope. | Reconcile every mapped row and open transaction; unmapped records remain quarantined/legacy; no guessed branch/company. | Undo mapping metadata/disable feature without deleting source rows. Owner approves each company cohort. |
| **3. Owner-controlled catalogue and branch inventory ledger** — Phase 1; product/branch mapping for each cohort | Company-owned product/category semantics, branch inventory balances and movements, reservation integration, stock policies and audit. Additive snapshots/constraints. | Existing catalogue and online reservation/cancellation remain behaviorally equivalent; no oversell, nonnegative balance, only Owner catalogue/pricing, Seller no adjustments. | Feature-flag to legacy path only before ledger transactions; after writes use reconciliation/forward repair, not destructive rollback. Owner approves stock opening balances and costing basis. |
| **4. Online sales compatibility and customer relationship/privacy** — Phases 1–3 | Company customer relation/consent records, branch fulfilment assignment, order snapshots, multi-item cart only if separately approved. No payment redesign. | Existing single-item checkout/payment/order transitions retained; cross-company customer privacy; guest/order identity matching verified; opt-in multi-item compatibility. | Disable new checkout features while preserving accepted order/history; forward repair. Owner/privacy approval before using historical identity joins. |
| **5. POS sale workflow** — Phases 1 and 3; decisions on sale/receipt/stock rules | POS sale/items, immutable snapshots, receipt, stock movements and attribution; no payment gateway or new payment state. | Branch inventory concurrency, multi-item atomicity, Seller attribution, receipt correctness, POS state separate from payment; existing online regression passes. | Stop POS entry and reconcile completed sale/movement pairs; never delete completed sales. Owner approves operational policy and acceptance. |
| **6. Procurement and supplier financial history** — Phases 1 and 3; approval-limit and splitting rule decisions | Suppliers, requests/approvals, POs/items, invoices, partial receipts, discrepancies, returns, supplier balance/history and audits. Payment execution remains out. | Self-approval/threshold/splitting tests, concurrent approvals, receiving-to-stock reconciliation, return/invoice traceability, supplier history immutable. | Pause new procurement approvals, retain records and resume via forward repair. Owner approves limits, currency, split rule, accounting treatment. |
| **7. Animal/adoption company-aware compatibility** — Phase 1; verified ownership/custody mappings | Optional animal identity/custody and listing/application associations; existing `PetListing` remains compatible. | Legacy listings unchanged; no automatic company ownership; concurrency allows one approved applicant, free adoption, distinct sale/adoption states. | Disable new company animal flows; preserve legacy listing and application history. Owner approves custody/animal mapping and access policy. |
| **8. Reporting, scheduled delivery and exports** — relevant prior phases plus verified cost/expense policy | Persistent report snapshots/status/history, scheduled generation, authorized exports, calculation versioning. | Scope tests for every role; historical totals reconcile; no net profit without expense inputs; email only when configured. | Pause generation/export, retain reports and source records; regenerate corrected versions with provenance. Owner approves definitions/retention. |
| **9. Controlled cutover and post-cutover verification** — all cohort-specific phases accepted | Incremental cohort activation, monitoring, operational runbook, recovery rehearsal; no blanket legacy conversion. | Acceptance matrix all green; reconciliation and access reviews; verified production engine concurrency tests; backup restore drill. | Pause cohort, route to compatible path only if safe, otherwise stop writes and forward-repair. Explicit Owner go/no-go per cohort. |

No phase includes implementing deferred payment gateways, Seller payouts, commissions/settlement, supplier payment execution, or an authentication redesign. A later proposal is required for those items.

## 25. Risk Register

| ID | Risk | Likelihood / impact | Mitigation / gate |
|---|---|---|---|
| R-01 | Owner cardinality unresolved; incompatible membership constraints could be built | Medium / High | Resolve `OD-02` before membership migration or Owner policy implementation. |
| R-02 | Seller branch uniqueness scope changes legal/operational behavior | Medium / High | Resolve `OD-01`; no assignment constraint or employee UX implementation until approved. |
| R-03 | Existing records have ambiguous company/branch ownership | High / High | Stage 1 read-only inventory; no default company/backfill; quarantine until evidence and Owner approval. |
| R-04 | Current platform Admin bypass conflicts with separate authorization domains | High / Critical | Replace only in separately approved implementation; explicit intervention workflow and cross-role tests. |
| R-05 | Cross-company branch mismatch passes ordinary FKs | Medium / High | Composite constraints where supported, service validation, transaction, engine-specific tests. |
| R-06 | Inventory data cannot be reconciled to physical stock/cost | High / High | Opening stocktaking and Owner sign-off; avoid valuation/profit claims until verified. |
| R-07 | Multi-engine portability/locking is unknown | Medium / High | Verify production engine/version before DDL; run constraint/concurrency tests there. |
| R-08 | New POS status is misused as payment status | Medium / High | Independent state machines, preserve current enum and payment behavior, no payment redesign scope. |
| R-09 | Procurement split threshold rule ambiguous | Medium / High | Resolve `OD-05` before approval enforcement; audit flags before blocking if policy requires calibration. |
| R-10 | Seller historical report retention could expose PII after suspension | Medium / Medium | Resolve `OD-04`; default technical proposal limits to own history and redacts customer PII. |
| R-11 | Animal listings are mistakenly converted to company-owned inventory | Medium / High | Preserve legacy ownership; verify per-animal mapping, separate animal identity and stock. |
| R-12 | Destructive down migrations lose new movements/financial history | Medium / Critical | Additive forward-repair design, tested backup/restore, no assumption `down()` is rollback. |
| R-13 | Role/assignment status cached in background jobs allows post-revocation action | Medium / High | Reauthorize at execution; explicit tenant context, idempotency, audit. |
| R-14 | Reported gross/net profit misleading because cost/expenses incomplete | High / High | Sale-time verified cost snapshot, show unknowns, prohibit net profit without expense inputs. |

## 26. Locked Decision Register

All entries are **LOCKED — OWNER APPROVED** based on the provided brief:

| ID | Locked decision |
|---|---|
| LD-01 | Platform roles and business roles are distinct; no implicit membership or role conversion. |
| LD-02 | Owner authority is limited to own company; ownership transfer is explicit and auditable. |
| LD-03 | Branch Manager powers are restricted to assigned branch(es); cannot edit catalogue/pricing/manage Branch Managers/approve own request/exceed limit. |
| LD-04 | Seller is assigned to exactly one active branch at the approved scope; no catalogue, stock ownership/independent adjustment, purchase approval, or employee management. |
| LD-05 | Customer remains a global independent marketplace identity with own-profile and privacy control. |
| LD-06 | Only Owner creates/launches/edits catalogue products and controls official prices/discounts; referenced products are archived/deactivated. |
| LD-07 | Inventory is company-owned, branch-level, movement-audited, non-negative, transaction-safe; transfer workflow and existing online reservation/cancellation restoration are preserved. |
| LD-08 | Procurement approvals, branch monetary limits, no self-approval, no Seller approval, split prevention, invoices, partial receiving, discrepancies, returns, supplier balances/history included; supplier payment execution deferred. |
| LD-09 | POS and online sale lifecycles separate; existing online/payment behavior preserved; all listed payment integrations/redesign/payout/commission scope deferred. |
| LD-10 | Seller/Branch Manager/Owner customer access follows exact approved sale/branch/company boundaries; consent is purpose-specific, shop-scoped, revocable and audited. |
| LD-11 | Revenue, COGS, gross profit, net profit and Seller attribution remain distinct; sale cost snapshots are immutable; no unsupported net-profit claims. |
| LD-12 | `PetListing` compatibility retained; animal identity separated from quantity stock; free adoption allows multiple pending but one approved holder; legacy ownership/history preserved. |
| LD-13 | Step 1B: token rotation=restore; legacy remember cookies=reauth; password change on current device=fresh; IP policy=optional; device history access=user. Do not redesign absent concrete conflict and separate approval. |

## 27. Unresolved Owner Decision Register

These are business decisions and are not silently resolved by this technical proposal.

### OD-01 — Seller branch assignment uniqueness scope

**UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED**

- **Exact question:** Does “exactly ONE active branch at a time” mean one branch globally across PawHaven, or one branch per company (so a person explicitly employed by multiple companies may hold one branch assignment in each)?
- **Options:** (A) One active branch globally; (B) one active branch per company; (C) another explicitly defined scope.
- **Recommendation:** B is operationally consistent with independent company employment and company tenancy, but this behavior cannot be safely inferred.
- **Business impact:** Determines whether legitimate multi-company work is allowed and whether a cross-company transfer ends another job.
- **Security impact:** Defines scope for Seller customer, inventory and reporting access; global uniqueness reduces simultaneous exposure but may exceed business need.
- **Data impact:** Determines unique active assignment key `(user_id)` versus `(company_id, user_id)` and transfer/history constraints.
- **Implementation impact:** Blocks assignment schema constraints, transfer workflow, Seller onboarding and role tests.
- **Blocks implementation:** Yes, for Seller assignment foundation.

### OD-02 — Company Owner cardinality

**UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED**

- **Exact question:** Must every company have exactly one active Shop Owner, one primary Owner with explicitly delegated co-owners, or multiple equal-authority Owners?
- **Options:** (A) Exactly one active Owner; (B) one primary plus delegated co-owners with explicit permission set; (C) multiple equal Owners with quorum/dispute/transfer rules.
- **Recommendation:** A for clear accountability and last-owner protection; this remains a proposal, not a locked business rule.
- **Business impact:** Affects company governance, operational continuity, disputes, approval conflicts and transfer authority.
- **Security impact:** Determines who can reset settings, approve purchases, access customer records, remove members or initiate ownership transfer.
- **Data impact:** Membership cardinality, owner transfer/audit schema, recovery state and last-owner constraints.
- **Implementation impact:** Blocks company onboarding, Owner authorization and ownership-transfer workflows.
- **Blocks implementation:** Yes, for Owner lifecycle and company onboarding; other explicitly non-owner foundations may be designed but should not encode owner cardinality.

### OD-03 — Exceptional platform intervention workflow

**UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED**

- **Exact question:** Which specific platform Admin/Staff interventions may cross into a company's data or operations, under what reason, approval, scope, notification and audit controls?
- **Options:** (A) platform moderation/legally required intervention only, Admin role named and reason required; (B) separate just-in-time capability with approval and time-bound access; (C) no cross-company intervention except emergency procedure defined by Owner.
- **Recommendation:** A for moderation with explicit capabilities, plus a separate tightly controlled emergency process if required. No generic tenant bypass.
- **Business impact:** Impacts support, dispute and safety response.
- **Security impact:** Critical least-privilege and insider-access boundary.
- **Data impact:** Audit schema, access reason, target scope and retention.
- **Implementation impact:** Blocks platform intervention endpoints and any cross-company staff-support tooling; ordinary tenant isolation can proceed with all cross-company access denied.
- **Blocks implementation:** Yes for intervention capability; no for default-deny company boundaries.

### OD-04 — Suspended Seller historical report visibility

**UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED**

- **Exact question:** After Seller suspension/removal, may the person view historical own-sales performance, and if so for how long and with what customer fields?
- **Options:** (A) no business report access after suspension; (B) read-only own-history metrics with customer PII redacted; (C) time-limited own-history access under a defined retention/privacy rule.
- **Recommendation:** B or C, with no customer PII, is a reasonable privacy-preserving employment record, but requires retention/business policy.
- **Business impact:** Affects performance review, dispute and termination workflows.
- **Security impact:** Retained access creates residual account exposure; revoking immediately limits misuse.
- **Data impact:** Requires immutable attribution and report visibility/retention policy.
- **Implementation impact:** Blocks suspended-Seller report UX/policy; must not block preserving sale history.
- **Blocks implementation:** No for transactional history; yes for post-suspension report access.

### OD-05 — Purchase-request splitting rule

**UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED**

- **Exact question:** What makes multiple purchase requests one procurement purpose for threshold aggregation (supplier, branch, requester, related requisition, time window, category, or approved purchase plan), and should suspicious splits be blocked or routed for review?
- **Options:** (A) fixed Owner-approved time window and grouping keys with hard block; (B) detect/flag related requests and require Owner approval; (C) Owner-defined procurement plan/PO threshold as aggregation unit.
- **Recommendation:** C with automatic flagging of related requests; exact thresholds/window need business input.
- **Business impact:** Balances branch autonomy against approval evasion and procurement delays.
- **Security impact:** Prevents approval-limit bypass and collusion.
- **Data impact:** Requires request relationship/grouping metadata and audit evidence.
- **Implementation impact:** Blocks reliable split prevention/threshold enforcement until policy is defined.
- **Blocks implementation:** Yes, for approval-threshold enforcement; request creation and non-approval records can be staged without falsely claiming bypass prevention.

### OD-06 — Company/branch currency and accounting basis

**UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED**

- **Exact question:** Can company branches use different currencies, and what currency/tax/rounding basis governs approval limits, supplier invoices, sale snapshots and consolidated reporting?
- **Options:** (A) one company currency; (B) branch currencies with explicit conversion rate/source/time; (C) separate currencies only for supplier documents but a company base currency for reporting.
- **Recommendation:** A for initial foundation unless an actual multi-currency requirement is confirmed.
- **Business impact:** Changes pricing, procurement approvals, reports and accounting.
- **Security impact:** Incorrect currency conversion can bypass approval amounts or misstate reports.
- **Data impact:** Currency codes, exchange rate snapshots, totals and migration mapping.
- **Implementation impact:** Blocks monetary thresholds and consolidated financial reports until decided.
- **Blocks implementation:** Yes for cross-currency procurement/reporting; no for non-financial membership foundation.

### OD-07 — Historical account closure and business retention policy

**UNRESOLVED — EXPLICIT OWNER DECISION REQUIRED**

- **Exact question:** Which customer, order, animal/adoption, supplier invoice/return, audit and report records must be retained, anonymized or deleted on user/company closure, and for what duration?
- **Options:** (A) legally required schedule by record class; (B) fixed Owner-defined retention schedule with anonymization; (C) jurisdiction-specific schedule selected per deployment with a minimum operational baseline.
- **Recommendation:** C with legal review before release; a universal duration cannot be inferred.
- **Business impact:** Affects privacy requests, support, accounting evidence and business closure.
- **Security impact:** Longer retention increases breach exposure; deletion may destroy required evidence.
- **Data impact:** Anonymization keys, snapshots, retention jobs and referential integrity.
- **Implementation impact:** Blocks deletion/closure automation and long-term retention claims.
- **Blocks implementation:** Yes for automated data erasure and retention jobs; no for additive records with no deletion behavior.

## 28. Acceptance Criteria

Architecture v2 is ready for architecture approval when the Owner confirms that:

1. Sections 5 and 26 accurately capture locked decisions without reopening them.
2. Permission matrix section 8 is the sole business permission baseline and platform intervention remains scoped.
3. OD-01 through OD-07 are answered or explicitly deferred with impacted capabilities gated.
4. Membership/assignment design has one unambiguous current source and a transactionally synchronized history.
5. Customer identity remains global and company-specific history/consent is isolated.
6. Procurement includes supplier invoices, partial receipts, returns and supplier balance/history; supplier payment execution alone is deferred.
7. POS, online orders and payment have separate state and persistence boundaries; existing statuses/behavior are preserved.
8. Animal/listing ownership and legacy Seller compatibility are explicit; no automatic company conversion occurs.
9. The migration plan contains no default company, guessed assignment, destructive cleanup, or execution during architecture work.
10. Database integrity claims distinguish service validation, ordinary foreign keys, composite constraints, SQLite behavior and the unverified production engine.
11. Security covers all request/job/export surfaces and retains “query visibility is not action authorization.”
12. Roadmap phases, dependencies, tests, recovery, and Owner gates are approved separately from implementation approval.
13. Implementation remains unapproved until a distinct, explicit Owner authorization names phase, scope, environment and recovery plan.

## 29. Final Recommendation

Approve this v2 as an architecture proposal only after resolving or formally gating the owner decisions identified above. The immediate design direction is:

- Company membership establishes company role; effective-dated branch assignment is the canonical operational branch source.
- Global Customer identity is reused across companies; company relationship, branch transaction, Seller attribution and purpose-specific consent remain distinct.
- One active Owner per company and one active Seller branch per company are recommended starting points, but require explicit Owner approval.
- Product ownership/pricing remains Owner-only. Company stock is branch-ledgered and inventory is not Seller-owned.
- Online order, POS sale and payment are independent lifecycles. Existing checkout/reservation/cancellation/payment behavior remains protected.
- Procurement includes supplier financial history, invoices, partial receipts and returns; only payment execution is deferred.
- Existing animal listings remain compatible and are never assumed company-owned.
- No legacy/default-company backfill or implementation begins until verified mapping, owner decisions and phase authorization are complete.

**Final verdict: READY WITH OWNER DECISIONS PENDING.**

This verdict refers solely to the completeness of the architecture proposal for Owner review. It does **not** mean implementation is approved, migration is safe to execute, or the application is production-ready. Stop here pending explicit Owner review.
