# Business management access and isolation

## Scope

This additive slice covers company and branch ownership of shop records, staff assignments, and server-side access boundaries for inventory, catalog, and order operations. It does not add payment processing, a payment provider, or changes to payment collection. Existing COD/payment-status behavior remains as it was.

## Access rules

- Administrators retain global access.
- Staff must have an active business membership to reach shop operations.
- Memberships are scoped to one company and either all of that company's branches (`branch_id = null`) or the explicitly assigned branches.
- A company-wide membership sees shared records and branch records in its company. A branch-only membership sees only records explicitly assigned to that branch; unassigned/shared records are not exposed to branch-only staff.
- Company-wide owners and managers can manage company details, branches, and staff assignments. Branch-scoped managers receive operational access only and cannot manage company-wide settings or assignments through the current UI.
- Product and order model queries, implicit route binding, policy checks, and shop routes enforce the same company/branch boundaries. Category selection is checked against the actor's visible company data.
- Existing staff accounts are given a staff-level membership in the default PawHaven company by the additive migration to preserve existing single-company operations. Staff promoted after the migration must be explicitly assigned before accessing shop operations.

## Schema and legacy data

`2026_11_10_000000_add_company_branch_business_access.php` creates a companies table and staff-membership table, adds nullable company/branch identifiers to existing shop records and audit logs, and backfills existing branches, categories, products, and orders to a default PawHaven company. Legacy rows remain company-wide (`branch_id = null`) because their historical branch cannot be inferred safely. Pre-existing audit events remain unassigned/global because their company cannot be inferred safely; staff queries do not expose those events. Foreign keys restrict deletion of companies or branches still referenced by business records.

The migration has **not** been run in this workspace. Before applying it, review the generated migration against a verified backup and a disposable staging copy. Do not use a production or local developer database as a test target.

## Local demo setup

The demo seeder is intentionally **not** invoked by the normal `DatabaseSeeder`. To opt in on a local-only installation after the schema has been applied:

```shell
php artisan db:seed --class=BusinessDemoSeeder
```

The seeder checks `APP_ENV=local`, creates a demo company and branch, and assigns the first existing staff account if one exists. It does not create login credentials. It writes nothing outside the local environment.

## Known gaps and rollout cautions

- Store settings (such as currency, delivery configuration, and feature flags) remain application-wide rather than company-specific.
- Existing product and order records are backfilled to the default company with no branch; branch-only staff will not see them until an administrator assigns them to a branch. No historical branch assignment is guessed.
- Customer-created shop orders are company-wide by default. A company-wide member can process them; branch-only fulfilment requires an explicit order-to-branch assignment workflow, which is not included here.
- Product creation can infer a branch for a single-branch membership. Staff assigned to multiple companies or branches need an explicit context-selection workflow before ambiguous writes can be made.
- The public published catalog remains public under the existing single-store storefront model; staff-specific company/branch filters apply to operational views and actions.
- This work does not establish production readiness. Review membership grants, migration/backfill behavior, deployment sequencing, backups, logs, and the remaining branch-fulfilment gaps before a real rollout.
