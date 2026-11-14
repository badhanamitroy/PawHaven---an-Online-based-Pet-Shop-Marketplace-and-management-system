# PawHaven Security and Data-Integrity Audit

**Audit date:** 2026-11-07  
**Scope:** Static source tracing, route/policy review, local configuration review with secrets suppressed, read-only database inspection and existing feature tests. This was not a penetration test or production configuration audit.

## Controls verified

| Control | Evidence |
|---|---|
| Public registration privilege boundary | `CreateNewUser` does not permit self-registration as admin/staff; role is explicitly set to customer or seller. |
| Admin/staff route authorization | Admin group requires authentication, verified email and `staff_or_admin`; administrator-only routes add the `admin` middleware. |
| Seller access and data isolation | Seller group requires auth/verified/seller; seller listing queries use the current user's relation; listing and order policies compare owner IDs. |
| Customer order access | Customer dashboard queries the current user; cancellation goes through `OrderPolicy` and an order-state transition check. |
| Admin OTP | Dedicated admin password flow requires email OTP before login; OTP is hashed, expires in five minutes, limits verification attempts to five and applies a 60-second resend cooldown. Admin login rate-limits credentials. Auth test exercises the OTP challenge. |
| Passwords and ordinary sessions | User password uses Laravel's `hashed` cast. Local `.env` configures 12 bcrypt rounds. Login/OTP are not bypassed by this audit. |
| CSRF and input validation | Routes are in Laravel's web stack (default CSRF protection). Registration, order, admin settings and listing forms use server-side validation. |
| File upload validation | Product/brand assets validate image types and sizes; branding also validates dimensions. Seller listing images use image validation, maximum five images and 2 MB each. |
| Historical record safeguards | Product deletion is refused if order items reference it. Role changes are used rather than deleting historical users. The inspected schema has no account suspension/ban flag. |
| Database integrity snapshot | Read-only SQLite check found no foreign-key violations. No DB writes/migrations were run. |

## Findings and risks

### Medium — hidden/unpublished product can be ordered by a crafted request

`OrderRequestController::store` validates only that `product_id` exists, then loads it without checking `is_published` or stock. The public homepage only renders published products, but visibility is not re-enforced at the mutation boundary. A verified account can submit a request for an unpublished product if it knows an ID. Stock is neither checked nor reserved/decremented. Treat this as a business-authorization/inventory-integrity gap; fix and test server-side before treating orders as a confirmed checkout.

### Medium — order and order-item persistence is not atomic

The product and pet request paths create an `orders` row and then create its `order_items` row as separate writes without a surrounding transaction. Failure between writes can leave a partial order. Pet listing moderation does use a transaction. Preserve historical records and add failure-path tests before changing order persistence.

### Low/Medium — seller is accepted by the admin authentication flow but denied by admin authorization

`AdminAuthController` accepts `seller` as an allowed role and sends that account through OTP; after verification it redirects to `admin.dashboard`, where `staff_or_admin` returns 403. The feature test currently asserts that a seller can authenticate this way, so the contradiction is covered as intended behavior rather than caught as a defect. This is not evidence of seller access to protected admin actions, but it is a confusing role boundary and unnecessarily exposes the admin OTP flow to sellers. Decide whether this is an intentional seller-admin workflow before changing it.

### Medium — OTP helper creates a plain-code local cache entry

`EmailOtpService` stores the hashed OTP for verification and, only in `APP_ENV=local`, also stores a development OTP cache value. The current `.env` is local/debug and the local cache is database-backed. Do not deploy with `APP_ENV=local`; production cache/log access and retention should be verified. No OTP or credentials were read during this audit.

### Medium — production hardening is unverified

Current local config has `APP_DEBUG=true`; session cookie `secure` is environment-controlled and local mail is configured as SMTP. Production values, TLS/HTTPS, web-SAPI error display, mail transport, secret rotation, proxy handling, session lifetime and secure-cookie behavior were not available. Require `APP_DEBUG=false`, HTTPS-only session cookies and managed secrets in deployment verification; do not edit `.env` as part of this audit.

### Low/Medium — audit history is useful but incomplete as a platform-wide record

Admin mutations often record `AdminAuditLog` rows; listing moderation separately records `ListingModerationAction`. Seller request status/payment changes and some listing owner changes do not write an equivalent audit history. Admin role-change descriptions include target email in the audit description; limit access and establish retention rules. This is not currently an immutable audit log.

### Low — upload replacement and retention lifecycle needs failure-path review

Brand upload cleanup deletes an older app-managed branding file before settings are persisted. If a later settings write fails, the old setting may refer to a removed file; newly stored files can also become orphaned on later failure. This was not exercised. User files were not touched during this audit.

## Additional controls and limits

- OTP is not optional for the dedicated admin login path, but staff coverage in the active DB is absent (no staff user); automated tests use factory identities.
- `APP_DEBUG=true` is confirmed only for the local `.env`, not production. Do not copy this configuration to a public environment.
- Session and cache use the database locally; cookie security flags are environment-controlled. CSRF is inherited from web middleware.
- No separate payment provider, transaction ledger, user suspension/ban workflow, reported-content model, review system or seller application approval flow was found.
- No finding here establishes exploitability beyond the documented server-side code paths. Authorization, concurrency and production controls require focused test cases and production-like configuration review.

## Security regression gates before rebuild release

1. Verify customer/seller/staff/admin route matrix, direct URL access and cross-account order/listing ownership.
2. Test registration cannot self-assign admin/staff; verify OTP required, expiry, attempt ceiling, resend cooldown, cancellation and rate limits.
3. Test hidden/out-of-stock product requests and atomic order/item failure behavior.
4. Test listing/product upload MIME, size, malformed file and path handling without removing historical uploads.
5. Verify suspended/disabled accounts (if introduced) preserve orders, audit logs and listing history.
6. Run CSRF/session security checks with production-like HTTPS, `APP_DEBUG=false`, secure cookies and mail/cache drivers.

See the [functional matrix](PawHaven_Functional_Audit.md), [architecture/data audit](PawHaven_Architecture_Audit.md), and [roadmap](../design/PawHaven_Redesign_Roadmap.md).
