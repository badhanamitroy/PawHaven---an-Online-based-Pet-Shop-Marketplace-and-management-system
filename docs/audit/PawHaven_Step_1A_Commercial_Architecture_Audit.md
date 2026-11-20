# PawHaven Step 1A — Commercial Architecture Audit

**Inspection date:** 2026-11-08  
**Project:** `PH Web Version/WebApp`  
**Framework observed:** Laravel 13.35.0, PHP 8.5.0  
**Status:** Inspection and backup completed. Application changes were stopped before implementation because the requested per-device logout guarantee cannot be met safely with Laravel's current single-token remember-me design without a larger authentication change.

## 1. Current commercial architecture

PawHaven is a self-hosted Laravel monolith using Blade, Livewire, Fortify, Eloquent, and a single `users` table with role values. The public homepage, marketplace, customer dashboard, admin operations, seller portal, email templates, and settings are all part of this application. The root route currently renders the public homepage for both guests and authenticated users; it does not dispatch authenticated users to role-specific dashboards.

Observed application domains:

- Customer, Seller, Staff, and Admin authentication and role-gated dashboards.
- Public pet sale/adoption/rescue listings and Seller listing management.
- A centrally managed pet-products shop and order requests.
- Admin moderation, brand/homepage settings, order operations, and role management.
- Email verification, password reset, email OTP for the dedicated Admin/Staff login, optional Fortify two-factor authentication, and passkeys.

The application is a hybrid of a single-store product shop and a Seller-owned pet-listing marketplace. It is not a switchable multi-vendor product marketplace.

## 2. Hardcoded and environment-dependent references

### Hardcoded/default references

- `APP_NAME` and many page labels/defaults contain **PawHaven**; some homepage, email, and navigation copy remains literal even though a database brand name is configurable.
- `SettingsService::defaults()` contains `[redacted account]`, `+1 (555) 729-4283`, `100 Pet Haven Way, Suite 400, Seattle, WA 98101`, and PawHaven social URLs. These look like sample/default contact details and should be replaced before a buyer deployment.
- The dedicated Admin login form has the personal-looking placeholder `[redacted account]`; tests also contain developer/sample addresses. The actual `.env` was not read.
- Public forms use US phone and Seattle examples. The storefront defaults to USD and uses US-oriented sample catalog copy.
- `resources/views/layouts/app/header.blade.php` links to the Laravel Livewire starter-kit GitHub repository. This is a developer/starter-kit reference, not a buyer's project link.
- The Admin login screen states that attempts are “geo-tracked”; no geolocation tracking implementation was found in the inspected application code. Do not treat that statement as verified behavior.

### Environment-dependent references

- `.env.example` uses `APP_URL=http://localhost:8000`, SQLite, database sessions/cache/queue, and Gmail SMTP placeholders. These are sample settings, not production credentials.
- `APP_URL` also determines the public storage URL and Fortify passkey origins.
- Mail transport, database, cache, queue, storage disk, cookie security, and application key are environment-configured.
- The local app URL and production host/TLS/proxy configuration were not tested.

## 3. Existing configuration capabilities

The database-backed Settings service and Admin screens provide partial installation-level customization:

- Brand name, tagline, colors, logos, favicon, contact email/phone/address, social URLs, and meta description.
- Homepage headline/supporting text, announcement content, CTA labels/targets, section visibility, and staged draft/publish.
- Shop currency/symbol, delivery fee, free-shipping threshold, COD flag, address, support phone, and tax percentage.
- Staff/branch feature flags and branch records.

These are real configuration fields and some are used by the relevant views/controllers. Their presence does **not** establish complete payment, shipping, tax, multi-branch, or white-label workflows. The Configuration guide also documents the business-settings path as `/admin/settings/business`, while the current route is `/admin/settings/shop`.

## 4. Missing white-label capabilities

- Brand settings do not replace all literal PawHaven text, email content, starter-kit links, sample copy, or US/USD assumptions.
- There is no Admin UI for `APP_URL`, mail transport, database credentials, cookie policy, storage backend, or deployment secrets; those belong in deployment configuration.
- No complete buyer-facing license/ownership or third-party notices inventory was found.
- No complete localization or per-installation content translation system was found.
- The current default contact/social values need explicit replacement during installation.

## 5. Installation, portability, upgrade, and backup gaps

- `composer.json` requires PHP `^8.3`, while `docs/INSTALLATION.md` and `README.md` describe PHP 8.2 as supported. This version floor is inconsistent.
- `config/database.php` defines SQLite, MySQL, MariaDB, PostgreSQL, and SQL Server connections; the setup documentation focuses on SQLite/MySQL. No cross-engine migration/test matrix was verified. Several migrations use database-sensitive column-placement/JSON constructs; portability beyond the active SQLite configuration should be tested before it is marketed as supported.
- Fresh installation instructions cover Composer/npm setup, key generation, migrations, `storage:link`, and `admin:create`. The Composer setup script runs migrations and builds assets; it is not an in-place upgrade procedure.
- A backup/restore guide exists, but its SQLite restore example copies a file directly over the live database. A buyer should stop application writers, restore to a separate path, verify integrity/hash, and then perform a controlled replacement. The guide also includes `.env` in the backup set; that file contains secrets and needs restricted/encrypted backup storage.
- No documented upgrade/rollback or restore-drill procedure was verified.
- The public disk maps uploaded files to `storage/app/public`, exposed through `public/storage`. Branding and listing imagery therefore require media backups in addition to the database. Existing public logos and compiled assets are also deployment files.

## 6. Payment, shipping, mail, and notification status

- **Payments:** Payment statuses and administrative/Seller status operations exist; the shop exposes a COD setting. No live payment gateway SDK, processor credentials, payment intent/capture/refund integration, or transaction ledger was found. This is not a live online-payment workflow.
- **Shipping:** Delivery fee, free-shipping threshold, address/phone, and delivery-zone text are configurable. No carrier API, label purchase, live rate lookup, tracking integration, or fulfillment service was found.
- **Mail:** Laravel mail transports are configurable (SMTP, SES, Postmark, Resend, log, and others). The application has OTP, verification/reset, order, moderation, and security email classes. Admin/Staff's dedicated login depends on delivered email OTP; buyer deployments must configure and test mail delivery. A Gmail example in `.env.example` is a placeholder, not a required provider.
- **Notifications:** Email delivery is implemented for selected flows; no evidence was found of mandatory SMS/push-provider infrastructure.

## 7. Security and external-dependency findings

- No application-level HTTP callback, remote activation/DRM check, kill switch, telemetry endpoint, or developer-owned mandatory runtime service was found in the inspected app/routes/config/frontend code.
- The Vite configuration uses the Bunny Fonts plugin to obtain Instrument Sans during asset building. This is a build-time network dependency; production should serve the compiled local assets and can bundle/provide fonts under its own deployment policy.
- Configured email and normal dependency installation/build registries are external services. Social URLs are outbound links, not application callbacks.
- Admin OTP is implemented on the dedicated Admin/Staff portal. However, the generic Fortify login route currently accepts a password-authenticated user and its `LoginResponse` routes Admin/Staff to `/admin`; the dedicated email-OTP controller is not part of that generic login pipeline. The custom `App\Actions\Fortify\RedirectIfTwoFactorAuthenticatable` class is not wired by the current Fortify provider. Unless an account has separately enabled Fortify TOTP, the generic route can bypass the dedicated email-OTP step. This must be corrected before deployment.
- The root Composer package metadata declares `MIT`, while the README describes a commercial source-code license. This is a material licensing/packaging ambiguity; resolve it with the rights holder. Composer/npm transitive dependency licenses and the provenance/license of supplied logos and fonts were not exhaustively audited, and no complete third-party notices manifest was verified.
- Production `.env`, secrets, and real provider configuration were deliberately not inspected.

## 8. Homepage and dashboard routing findings

Current behavior observed from route and middleware source:

- Guest `GET /` renders the public homepage.
- Authenticated `GET /` also renders that same homepage; role-based root dispatch is not implemented.
- `GET /dashboard` dispatches Admin/Staff to `/admin`, Seller to `/seller/dashboard`, and recognized Customer/legacy-customer roles to the Customer dashboard. Unsupported roles are rejected there rather than treated as Customer.
- `/admin` operations require `auth`, verified email, and `staff_or_admin`; Admin-only settings/management routes add the `admin` middleware. Seller routes require `auth`, verified email, and `seller`; Customer workflows use the `customer` middleware.
- Legacy null, empty, and `user` roles are intentionally treated as Customer by `User::isCustomer()`. Other unknown role values do not qualify as Customer.
- Fortify's login response uses role-based default destinations, but intended-URL filtering is based on URL substring checks for Customer/Seller, and the Admin/Staff branch does not filter Seller intended URLs. Route middleware remains the authorization boundary, so an invalid intended path can still end in a 403; the current redirect behavior is inconsistent and needs focused tests.
- Existing navigation commonly links to `route('home')`, which is currently `/`. If root dispatch is introduced, an explicit always-public homepage URL and consistent logo/navigation links will be needed.

## 9. Persistent-session findings and blocking design constraint

Current configuration and behavior:

- `config/session.php` defaults to database sessions with a 120-minute idle lifetime; `expire_on_close` defaults to `false`.
- Session cookies are HTTP-only by default and SameSite `lax`; the `secure` flag and domain are environment-controlled. The `.env.example` does not expose all cookie policy settings.
- The users migration already has Laravel's `remember_token` column. Fortify's normal login and the dedicated Admin OTP flow only request persistent login when the submitted `remember` value is truthy. Both login forms currently render that option unchecked by default; the Customer/Seller label says “Remember me for 30 days,” which does not establish the framework cookie's actual lifetime.
- Fortify's standard authenticated-session preparation regenerates the session; the dedicated Admin OTP flow also regenerates it after successful OTP. Standard logout invalidates the session and rotates Laravel's remember token.
- Password reset/change actions replace the password hash. Laravel's recaller validates against the password hash as well as the remember token, so a previously issued recaller using the old hash is rejected after a password change/reset. No password-change/reset workflow was exercised in this inspection.
- A browser shares its session cookie across tabs in the same profile. No browser-level test was run.

**Stop condition:** Laravel's standard remember-me mechanism stores one `remember_token` per user, not per browser/device. Rotating it on logout makes the old recaller unusable as required, but also makes persistent recaller cookies issued to other browser profiles/devices unusable on their next session restoration. Not rotating it would leave a previously issued cookie replayable after logout. Therefore the exact combination of “revoke only this browser's persistent login” and “a previously issued remember cookie cannot restore authentication after logout” cannot be safely satisfied with the existing single-token mechanism alone.

Meeting both requirements requires a per-device remember-token registry and corresponding authentication/logout lifecycle (including a schema change and careful backward-compatible migration), or an explicit change to the requested device-isolation policy. That is a larger authentication architecture decision. Per the stop instruction, no such migration or workaround was introduced and no persistent-login behavior was changed.

## 10. Exact files changed and why

- Created only this audit document: `docs/audit/PawHaven_Step_1A_Commercial_Architecture_Audit.md`, to record the requested findings, backup evidence, and stop reason.
- No application code, configuration, tests, database, uploads, or existing documentation were changed.
- Initial worktree baseline: branch `master`; 34 modified tracked files and 287 untracked files; no staged changes. The Step 0.5 report records an earlier pre-phase baseline of 33 modified tracked files and 281 untracked files and documents subsequent Step 0.5 deliverables. The existing worktree was preserved; no reset, stash, clean, checkout, stage, commit, push, or deployment was performed.

## 11. Database and migration impact

- No application database writes, schema changes, migrations, data backfills, or destructive operations were performed.
- The current SQLite database was inspected read-only with `PRAGMA integrity_check`; it returned `ok`.
- The existing users schema already contains `remember_token`; a separate per-device registry would require an additional schema design and migration, which was not authorized/implemented.
- Automated tests were not run during this stopped inspection, so the active application database was not changed by test setup.

## 12. Backup location and verification

Fresh backup created outside the project:

`<repository-root>\Step1A_Backup_20261108_1021`

- SQLite snapshot: `database.sqlite`, 323,584 bytes.
- Snapshot SHA-256: `3D89307AFE7E1F55ADEA47A3669C03E2C592EBBF649A943170F03E777F48C1AB`.
- The SQLite online backup API (`sqlite3 .backup`) was used because the active database file was held open by another process and could not be read by a normal Windows file-hash operation.
- `PRAGMA integrity_check` returned `ok` for both the active database and the backed-up database.
- The copied `storage/app/public` tree contains four files (538,411 bytes total); each source/copy SHA-256 matched:
  - `.gitignore` — 14 bytes — `240A3E0D37D2E86B614063F5347EB02D4F99CA6C254DE6B82871FF8D95532A7D`
  - `branding/oIYwbX5na7GyoZM1Tr4KRqUMW8ULGiUMqRTK3dli.png` — 124,497 bytes — `A996FA5B32571A356C8A91494A26B41E7D7852288880BFFC89063A7B522F1ED7`
  - `branding/ormNtMhuOo1uNuNNjn1usHjXBZqgHAAHldW60Jxi.png` — 156,938 bytes — `EF9B07F78C28AF2075BAACC7AF49F6CBD840AFC2ADD8D99FC0ED1A8F7A95435F`
  - `branding/uBjQ8f7apqRkh6H1kuOYbYIdYtuE5O6VHQq9M9GL.png` — 256,962 bytes — `B80CB850966B05AB3E22D7A981811EDEFBDCC59928CF051D3431D7CECE8A15CE`
- The active SQLite file could not be independently hashed while locked; the fresh backup file itself and every upload copy were hashed and verified.
- No `.env` file or credentials were copied into this backup.

## 13. Tests executed and results

- PHP CLI version: 8.5.0.
- Laravel version: 13.35.0.
- Active database integrity check: passed (`ok`).
- Backup database integrity check: passed (`ok`).
- Upload source/copy hash comparison: passed for all four files.
- Step 1A feature tests and full test suite: **not run**, because implementation stopped at the per-device persistent-auth design constraint. No browser-session simulation was performed.
- The Step 0.5 report records a prior full-suite result of 192 passed / 843 assertions; that result was not rerun or independently verified during this inspection.

Closing/reopening a real browser, tab synchronization, and profile/device isolation still require manual browser testing after an approved persistent-auth design is selected.

## 14. Remaining limitations

- None of the authorized dashboard/root corrections were applied: authenticated root requests still see the public homepage.
- Generic Fortify login's Admin/Staff OTP bypass remains an unresolved security issue.
- Remember-me remains optional and unchecked by default; no new persistent-login guarantee was introduced.
- Per-device remember-token revocation and isolation remain unresolved.
- All routes, session behavior, Step 0.5 regression tests, and the full suite must be revalidated after the architecture decision.
- Commercial licensing, transitive package/asset attribution, production mail/TLS/database configuration, and cross-database support remain unverified.

## 15. Prioritized recommendations for Step 1B

1. **Approve a per-device authentication design or revise the device-isolation requirement.** If approved, specify storage, token hashing/rotation, backward compatibility, expiry, logout-all-devices behavior, and migration/rollback before implementation.
2. **Close the generic Admin/Staff login bypass.** Ensure only the dedicated Admin/Staff email-OTP flow can authenticate those roles; keep OTP mandatory and add generic-login rejection tests.
3. **Implement and test role-aware root dispatch.** Preserve guest homepage rendering, add an always-public homepage URL for authenticated navigation, cover Admin/Staff/Seller/Customer/unknown roles, and keep direct route middleware as the authorization boundary.
4. **Implement persistent login consistently across the approved roles.** Specify default checkbox behavior, session/recaller cookie attributes, expiry, session regeneration, logout revocation, and password-change/reset handling; test copied old cookies and unrelated profiles.
5. **Run targeted and full regression tests**, including all Step 0.5 purchase, stock, price, transaction, role, seeder, Seller compatibility, and legacy-order safeguards; then manually test multiple tabs and separate browser profiles.
6. **Resolve commercial packaging gaps:** reconcile PHP version requirements, license metadata, third-party notices/font provenance, starter-kit links, placeholder contact data, mail setup, database support claims, and safe restore/upgrade procedures.

No Step 1B work was started.
