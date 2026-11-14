# PawHaven Performance Audit

**Audit date:** 2026-11-07  
**Target:** `http://127.0.0.1:8000/`  
**Scope:** Local app/browser, targeted Laravel performance tests, read-only SQLite inspection and asset inventory. This is not a production load test.

## Summary and cold/warm measurements

The supplied 14.5–15.7 s cold-load report was **not reproduced**. The browser's first fresh-tab navigation to an already-running local server took about **1.89 s through `load`**, including a **1.384 s TTFB**. Subsequent reloads stabilized at **0.36–0.40 s load** and **0.110–0.112 s TTFB**. This confirms a first-request/warm-state difference in this session, but not a 15-second failure or its root cause. The local web process was already running, so this is not a cold PHP-process/server-start measurement.

| Measurement | First fresh-tab request | Warm reload / warm local requests |
|---|---:|---:|
| Browser TTFB (`responseStart - requestStart`) | 1,383.9 ms | 110.4 ms (later reloads 112.2 and 110.4 ms) |
| DOMContentLoaded | 1,883.2 ms | 234.9 ms |
| `load` event end | 1,891.7 ms | 361.2 ms (earlier reload was 921.4 ms while warming) |
| Document transfer | 100,764 bytes | 100,764 bytes |
| Resource entries | 17 | 17; cached remote image resources report zero transferred bytes |

Warm `curl.exe` samples (time-to-first-byte / total seconds): `/` 0.253 / 0.254; `/listings` 0.070 / 0.072; `/listings/1` 0.103 / 0.106; `/login` 0.129 / 0.131. `/contact` returned 404. Guest dashboard/admin/seller-dashboard requests returned redirects as expected. These command-line samples were taken after the browser had warmed the app and are not cold-start results.

The app's [PerformanceCharacterizationTest](../../tests/Feature/Performance/PerformanceCharacterizationTest.php) was run independently, then the full suite was run. It reports home rendering at **23.59–69.41 ms**, **12 DB queries**, and **1.36–1.85 ms accumulated DB time** with a cold settings cache. Login page reports 6 queries and 0.26–0.63 ms DB time. These are framework test-dispatch timings against the test suite's in-memory SQLite, not browser/server TTFB. The test deliberately sets `BCRYPT_ROUNDS=4`; this is not production password-hash performance evidence. Local `.env` reports 12 rounds and no weakening was attempted.

## Browser resources and front-end weight

The fresh navigation had 17 observed resources. Summing the cold resource transfer sizes shown in the browser gives approximately **1.8 MB** for those resources, or **1.9 MB including the 100,764-byte document**; cache state changes the observed transferred bytes. Largest first-load assets:

| Resource | Observed transfer |
|---|---:|
| Livewire JavaScript | 609,717 bytes |
| Compiled Tailwind/Flux CSS | 450,188 bytes |
| Flux JavaScript | 135,151 bytes |
| Green/white logo PNGs | 157,238 / 124,797 bytes |
| Three WOFF2 Instrument Sans weights | ~52 KB total |
| Additional WOFF fonts requested by CSS | ~44 KB total |
| Five visible Unsplash pet images | ~224 KB combined in the initial trace |

The compiled CSS file is 449,888 bytes on disk; the Vite app JS file is zero bytes, while Livewire/Flux and passkeys have their own bundles. Instrument Sans is local. The hero image was eager-loaded and remote; several pet listing images used default `loading=auto`, while product images were marked lazy. Remote Unsplash images add third-party availability/latency and disclose the browser's image request to that host. The SMTP mailer is not used by a public homepage GET; no email was sent or tested.

## Database, query shape and server evidence

- Active database is a small local SQLite file (23 tables). Current data includes 8 users, 4 pet listings and 4 products; it cannot represent production growth.
- Home and browse code eager-loads listing relationships (`petCategory`, `breed`, `images`, `seller`); listing details eager-loads related listing data. No N+1 pattern was demonstrated in these routes or in the 12-query homepage test.
- `SettingsService` merges defaults and DB values, caches the table pluck through the configured cache store, and maintains a per-request static cache. In the local environment, both cache and sessions use the database. Cold-cache home query data is available above.
- Home uses a bounded set of featured records; marketplace browse is paginated. Customer dashboard loads all saved pets and owned listings without pagination, so that path may grow with user activity.
- `orders` has status/payment-status indexes but no explicit `user_id` or `seller_id` index in the inspected schema; `order_items` has no explicit secondary index. Do not add indexes based only on this small DB: capture query plans and production-like distributions first.
- PHP CLI reports OPcache enabled and a 4 MB realpath cache, but web-SAPI OPcache/configuration is **unverified**. PHP memory limit is 128 MB in the CLI configuration. Runtime starts/cache state for the web process was not measured.
- Browser performance entries did not produce reliable LCP/CLS values in this harness. Core Web Vitals must be collected in a real browser trace/RUM run; unavailable values are not zero values.

## Findings and root-cause status

**Verified:** The first local browser request was slower than warmed reload; warmed endpoint responses and synthetic app dispatch are much faster. Database query execution in the test measurement is a small fraction of app dispatch time. CSS, Livewire and logo payloads are substantial; the first load also fetched remote pet photos.

**Not established:** The cause of the earlier 14.5–15.7 s observation, whether PHP bootstrap/opcache/server cold start was responsible, whether the 1.38 s first TTFB is representative, actual production web-SAPI settings, SMTP wait, client network conditions, real LCP, or a particular slow query. No evidence supports attributing the earlier report to SQLite or weakening security controls.

## Next measurement gate

Before optimization, reproduce on a clean browser profile and a deliberately restarted but production-configured app process, recording server process start, first request and at least five warmed requests. Capture Laravel query count/time on the same request, web-SAPI OPcache/config/cache state, DevTools network waterfall, image/font cache behavior and LCP/CLS/INP on mobile and desktop. Keep password hashing, OTP limits and authorization unchanged. The roadmap sequences payload reductions only after these measurements.
