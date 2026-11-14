# PawHaven Performance Audit

**Run date:** 2026-11-01 (follow-up measurements)  
**Scope:** Login, registration, profile/product/settings saves. No dashboard redesign.

## Summary

The settings write-amplification finding is confirmed: after the existing bulk-upsert change, repeated isolated runs made 6 shop-setting queries and 5 branding-setting queries, versus historical single-run baselines of 21 and 28. That is **71.4% and 82.1% fewer SQL queries**. The repeated current server-side save medians were 13.10 ms and 9.49 ms, but the historical timing baseline has only one sample per workflow; no reliable wall-time improvement is claimed.

The reported 14.5–15.7 second cold login-page load was **not reproduced**. Three fresh local-server starts produced first-request login TTFB values of 1.325–1.426 seconds; repeated warm server requests were much faster. A browser navigation immediately after a fresh server start loaded in 2.039 seconds in the existing browser context. The original 14–16 second delay remains unexplained, so no cold-start optimization was made.

Independent bcrypt measurements at the configured cost of 12 were hundreds of milliseconds per hash/check. Authentication test requests were about 0.54–0.61 seconds with less than 0.7 ms of cumulative SQL time. Hashing is therefore a substantial authentication CPU cost, but does not explain the slow GET/cold page load. Hashing parameters were not changed. Real SMTP/OTP delivery time remains unmeasured.

Three additional isolated Laravel GET trials reset the process-local settings cache before each page request. They produced page-render medians of 123.58 ms (home), 45.22 ms (login), 5.69 ms (registration), and 13.41 ms (admin login); each request used 6 SQL queries except home (12), with under 2 ms cumulative SQL. These in-process test measurements do not reproduce the reported cold browser delay and do not replace browser-navigation timings.

## Environment and method

### Local application

Captured with `php artisan about` and non-secret `.env` settings:

| Setting | Value |
|---|---|
| OS | Windows |
| PHP / Laravel | 8.5.0 / 13.35.0 |
| Environment | `local`, debug enabled |
| Database | SQLite, `database/database.sqlite` |
| Cache / session / queue | database / database / database |
| Mail | SMTP |
| Password hash cost | bcrypt rounds 12 |
| Initial framework caches | config/events/routes not cached; views cached |

The supplied URL was initially unavailable. I started `php artisan serve` on `127.0.0.1:8000` for read-only GET checks, restarted it for three first-request trials, and stopped it afterward. The listener is stopped at the end of this follow-up.

### Test environment and measurement boundaries

`tests/Feature/Performance/PerformanceCharacterizationTest.php` times requests with `hrtime` and records SQL query count and cumulative database time with `DB::listen`; it does not record SQL bindings, passwords, or OTPs. Its fixtures use `RefreshDatabase` and SQLite `:memory:`. The performance runs set `BCRYPT_ROUNDS=12` in the test process to match the local app; the test no longer overrides that setting itself. The test overrides cache/session to database drivers and uses fake mail. The admin measurement stops at the OTP challenge, and the seller test records current behavior; neither reads or submits an OTP.

The independent driver probes used the local database with random, nonexistent cache keys and session IDs. Cache reads and session starts were read-only; they did not insert session/cache records. Browser and `curl` timings are separate from in-process Laravel request timings. Authentication and save test timings exclude browser networking and real SMTP.

### Isolated Laravel request measurements

The “before” column is the historical single observation from the prior audit. “After” is the median and range from three repeated current runs at bcrypt cost 12. SQL counts and cumulative DB times are from SQLite `:memory:` and include request middleware/session/cache work where it occurred; they are not production-file-database timings.

| Workflow | Before (single ms) | After (median; range ms, n=3) | Current SQL queries | Current DB time (median; range ms) | Notes |
|---|---:|---:|---:|---|
| Customer login | 619.10 | 586.46 (560.98–611.45) | 6 | 0.46 (0.41–0.60) | No login code changed |
| Seller login | 563.67 | 557.46 (554.51–567.04) | 6 | 0.36 (0.29–0.70) | Current public flow authenticates directly; see security note |
| Admin login, first factor | 599.42 | 556.15 (543.80–563.22) | 7 | 0.60 (0.57–0.68) | Fake mail; stops at OTP challenge |
| Customer registration | 290.04 | 291.55 (291.30–306.98) | 6 | 0.47 (0.40–0.66) | Fake mail |
| Seller registration | 371.78 | 286.53 (280.47–289.99) | 6 | 0.59 (0.29–0.96) | Fake mail |
| Customer profile update | 178.26 | 42.39 (33.50–4,856.95) | 2 | 0.27 (0.25–0.33) | One large outlier; no profile code changed |
| Seller profile update | 29.79 | 17.10 (16.02–51.71) | 2 | 0.12 (0.11–0.12) | No profile code changed |
| Admin profile update | 28.83 | 17.01 (16.36–75.46) | 2 | 0.11 (0.11–0.28) | No profile code changed |
| Product create | 10.02 | 4.57 (4.34–51.42) | 8 | 0.26 (0.26–1.90) | No product code changed |
| Product update | 3.97 | 7.41 (3.08–8.43) | 7 | 0.63 (0.17–1.10) | No product code changed |
| Shop settings update | 20.91 | 13.10 (6.18–14.45) | 6 (before 21) | 0.78 (0.40–0.87) | 71.4% fewer queries; no reliable latency claim |
| Brand settings update | 16.09 | 9.49 (7.11–13.24) | 5 (before 28) | 0.92 (0.53–1.03) | 82.1% fewer queries; no reliable latency claim |

The timing comparison is not treated as an improvement: old timings are single samples and several current ranges are wide or contain outliers. The stable finding is the lower settings query count, not a proven user-perceived save speedup.

### Isolated public-page rendering with cold settings cache

The feature test resets `SettingsService`'s process-local cache before each GET. These are three repeated test-process observations on SQLite `:memory:`, with database cache/session drivers; they are not browser or production-file-database timings.

| Page | Laravel duration median (range, ms; n=3) | SQL queries | Cumulative SQL time median (range, ms) |
|---|---:|---:|---:|
| Home (`/`) | 123.58 (119.30–192.47) | 12 | 1.60 (1.14–1.88) |
| Login (`/login`) | 45.22 (44.27–56.27) | 6 | 0.36 (0.36–0.54) |
| Registration (`/register`) | 5.69 (5.65–11.70) | 6 | 0.24 (0.20–0.44) |
| Admin login (`/admin/login`) | 13.41 (12.07–13.42) | 6 | 0.59 (0.44–0.66) |

These GET timings show neither a high SQL-time cause nor a repeatable 14–16 second delay. The separate fresh-server HTTP first-request measurement remains the closest cold-start observation, and the original delay remains unexplained.

### Browser and frontend observations

**Server-side HTTP timings** below are sequential local `curl` GET requests; every response was HTTP 200. Cold login means the first request after a fresh `php artisan serve` process, with no readiness HTTP request sent first.

| Endpoint | Cold first-request TTFB (n=3) | Warm TTFB median (range, n=5 for login; n=7 otherwise) | Warm total median (range) |
|---|---:|---:|---:|
| `/login` | median 1,384 ms; range 1,325–1,426 ms | 149.7 ms (115.6–263.2) | 151.9 ms (118.5–265.0) |
| `/` | Not repeated as a fresh-server first request | 163.7 ms (128.7–890.1) | 166.2 ms (132.7–895.0) |
| `/register` | Not repeated as a fresh-server first request | 130.3 ms (105.3–153.1) | 133.0 ms (107.7–156.8) |
| `/admin/login` | Not repeated as a fresh-server first request | 114.8 ms (98.7–159.8) | 117.0 ms (101.0–164.1) |

**Browser navigation timings** are from five sequential navigations per route in the attached browser, using its existing asset cache. Values are `responseStart` / DOM content loaded / page load, in milliseconds.

| Page | responseStart median (range) | DOM loaded median (range) | Page load median (range) |
|---|---:|---:|---:|
| `/` | 173 (146–818) | 281 (253–964) | 489 (443–1,331) |
| `/login` | 162 (150–201) | 266 (252–313) | 323 (305–367) |
| `/register` | 154 (148–162) | 254 (248–269) | 312 (293–323) |
| `/admin/login` | 140 (118–158) | 244 (214–264) | 305 (271–324) |

The first browser login navigation after a fresh server start (one observation, browser asset cache already warm) was responseStart **1,862 ms**, DOM loaded **1,978 ms**, and page load **2,039 ms**. This and the three cold `curl` trials did not reproduce the prior 14–16 second delay. The original observation remains unexplained; the fresh-browser first-asset-cache-miss scenario was not repeated, so it cannot be ruled out.

The inspected login page references a 450,188-byte CSS asset, 300-byte JavaScript asset, fonts, and logos. Warm browser resource timings for these assets were about 20–50 ms; no external asset host was contacted. One earlier shared-browser event history contained 403 console messages, but the final instrumented login navigation had no HTTP responses >=400 and no console errors; those earlier responses were not reproduced or attributable to the final navigation. `/seller/login` and `/seller/register` redirect to the shared forms as designed. No frontend change is supported by the current evidence.

## Root-cause assessment

| Candidate | Evidence and conclusion |
|---|---|
| Password hashing | Confirmed as a substantial authentication CPU cost. Nine direct bcrypt `make` samples at rounds 12 had median **298 ms** (268–412); nine `check` samples had median **306 ms** (272–458). Three-run login request medians were 556–586 ms, with <0.7 ms cumulative SQL. User registration hashes once via the `hashed` cast; login verifies once. No security parameter was reduced. This does not explain the slow GET/cold page load. |
| Database/query work | Not the primary measured login cost. Authentication uses 6–7 test-harness queries with under 1 ms cumulative SQL. Settings saves had repeatable write amplification from one `updateOrCreate` per key; the existing bulk upsert reduces total request query counts. |
| Database connection | A local SQLite `select 1` completed in 1.0 ms in the follow-up probe. This was not a production workload or a repeated connection-pool benchmark; no connection bottleneck is evidenced. |
| Cache/session | Local cache and session drivers are both database-backed. Nine read-only database-cache misses had median total 0.28 ms (0.22–1.95), one query, and median SQL time 0.10 ms. Nine read-only session starts for nonexistent random IDs had median 0.39 ms (0.16–1.20), one query, and median SQL time 0.21 ms. These are small and do not account for a 14-second delay. |
| PHP/Laravel initialization | Standalone CLI bootstrap measurements were 29–59 ms in two runs. This does not directly isolate the HTTP request bootstrap; it provides no evidence for a 14-second framework initialization. |
| Email / OTP | Admin OTP delivery uses synchronous mail in the existing flow. Tests fake mail and stop at challenge creation; real SMTP/provider wait and complete manual OTP processing were not measured. No queue change was made because worker availability and delivery guarantees are not established. |
| Redirects / repeated middleware | No redirect loop or repeated page request was observed. The seller shortcuts intentionally redirect to the shared forms. Login requests had stable query counts; no N+1 or duplicate hashing was evidenced. |
| Frontend/assets | Browser GETs completed without reproduced failed requests or JavaScript errors. Assets were local; warm resource timings were short. The CSS is large by transfer-size reporting, but it does not explain a high server responseStart and no asset fix is justified. |
| File upload/image processing | Settings characterization did not upload files. Existing feature tests use fake storage; no upload processing bottleneck was measured. |
| Cold app start | The previous 14–16 second first-page delay did not recur. Fresh-server `/login` first TTFB was 1.325–1.426 seconds; warm TTFB was 0.116–0.263 seconds. The source of the earlier delay remains **unproven**. |

## Changes made

- `app/Services/SettingsService.php`: the existing `setMany()` bulk upsert was verified; no further runtime change was made. It writes timestamped rows, conflicts on the existing unique `settings.key`, updates value/group/`updated_at`, preserves existing `created_at`, flushes both caches, keeps boolean encoding, and returns safely for empty input.
- `tests/Feature/Performance/PerformanceCharacterizationTest.php`: retained isolated measurements for login/registration, profile/product updates, settings writes, query counts, and DB time. Removed its local bcrypt-round override after a combined test run exposed an order-dependent factory-hash mismatch. Production-cost measurements now set `BCRYPT_ROUNDS=12` only in the test process; normal test runs use the configured PHPUnit test value.
- `docs/qa/PawHaven_Performance_Audit.md`: this follow-up audit and reproduction record.

No migration, index, authentication policy, hash algorithm/cost, queue, mail, `.env`, or frontend changes were made. The repository status lists these three performance files as untracked (not staged); the many other pre-existing modified/untracked files remain untouched.

## Security and scope notes

- Password hashing and existing password compatibility were left unchanged.
- The new performance tests use `Mail::fake()` and disposable in-memory records. They do not expose passwords, OTP values, or query bindings.
- **Disclosure:** the first baseline command also ran the pre-existing `AdminAuthenticationTest`, whose test implementation reads a synthetic OTP from `Mail::fake()` and submits it. This happened before that test was inspected. No real SMTP email was sent, and no OTP value appeared in output. That test was not rerun; the new performance test stops at the admin challenge.
- A pre-existing seller-login discrepancy was found: `app/Actions/Fortify/RedirectIfTwoFactorAuthenticatable.php` declares mandatory seller OTP behavior, but the custom action is not registered by the inspected Fortify provider. The measured public seller login authenticated without an OTP challenge. This was not changed as part of the performance-only scope; it needs a separate security decision.

## Validation executed

- Final `PerformanceCharacterizationTest.php` run at bcrypt rounds 12: **8 passed, 43 assertions**. The public-page test was also run three times separately to produce the medians above.
- Combined performance, registration, profile, business-settings, and brand-identity regression run at PHPUnit's normal test setting: **30 passed, 138 assertions**.
- `BusinessSettingsTest.php` and `BrandIdentityTest.php`: **10 passed, 56 assertions**. This covered admin update authorization, validation, persistence, and fake-storage upload cases.
- Pint `--test` on the performance test: **passed**; the settings service had also passed in the prior validation.
- PHPStan on the performance test: **passed**; the settings service had also passed in the prior validation.
- A first combined run exposed **8 profile-test errors** because the performance test's temporary cost-12 config left `UserFactory`'s cached hash inconsistent with the PHPUnit cost-4 setting. Removing that test-only override resolved the order dependency; the combined suite then passed.
- `npm run build`: **passed**; Vite reported that optional `fontaine` is absent, but completed successfully.
- `php artisan optimize`: completed for the cold-start experiment; generated config/route/event caches were cleared, restoring the initial state.
- `git diff --check`: reported a pre-existing extra blank line at EOF in `resources/css/app.css` (already modified before this task); that file was not changed for this task.
- Read-only local browser/curl GET trials: `/`, `/login`, `/register`, and `/admin/login`; no login form was submitted, no real email was sent, and no persistent database or upload changes were made. The temporary server is stopped.

## Remaining bottlenecks and reproduction

1. To reproduce the application-cost characterization without touching the application database or sending mail, temporarily set bcrypt cost 12 for that PowerShell process. The test database is SQLite `:memory:`:
   ```powershell
   $previous = $env:BCRYPT_ROUNDS
   $env:BCRYPT_ROUNDS = '12'
   php artisan test tests/Feature/Performance/PerformanceCharacterizationTest.php
   if ($null -eq $previous) { Remove-Item Env:BCRYPT_ROUNDS -ErrorAction SilentlyContinue } else { $env:BCRYPT_ROUNDS = $previous }
   ```
   `PAWHAVEN_PERF` lines contain request duration, SQL count, and cumulative SQL time only. PHPUnit's default environment uses bcrypt cost 4; restore the process environment after a cost-12 run as above.
2. The previous cold-load delay remains unproven. To continue, capture a first navigation in a fresh browser profile with cache disabled, and record browser `responseStart`, DOM/load, each asset, and matching `curl` TTFB after a fresh server start. Repeat before attributing the delay to app startup or assets.
3. Browser-observed settings-update POST times were not measured: no authorized admin test account was supplied, and no admin form was submitted. The isolated test request figures are not browser timings. Reproduce in a controlled authorized environment and separate OTP/provider wait from server processing; enter OTP manually.
4. Real SMTP delivery and the complete OTP workflow remain unmeasured. Investigate provider timing only with approved test accounts. Do not lower bcrypt cost or introduce queueing without confirming a worker and delivery monitoring.
5. Review and decide seller OTP enforcement separately. The current public seller login test confirms direct authentication, while the custom Fortify action describes mandatory OTP but is not wired by the inspected provider. No policy change was made here.
