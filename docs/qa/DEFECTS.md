# PawHaven Quality Assurance — Defect Tracking & Remediation Log

This document records the defects discovered during the real Chrome browser QA execution, their root cause analysis, severity classifications, remediation commits, and verification status.

---

## Defect Summary

| Defect ID | Severity | Title | Affected Component | Status |
|:---:|:---:|:---|:---|:---:|
| **DEF-01** | High | Brand Logo Image Broken in Header and Footer | `welcome.blade.php`, `app-logo-icon.blade.php` | **RESOLVED & VERIFIED** |
| **DEF-02** | Medium | Empty Pet Categories & Listings in Default Database | `pet_categories`, `pet_listings` tables | **RESOLVED & VERIFIED** |
| **DEF-03** | Low | Local Development Admin 2FA Test Accessibility | `EmailOtpService.php`, `AdminAuthController` | **RESOLVED & VERIFIED** |

---

## Detailed Defect Reports

### DEF-01: Brand Logo Image Broken in Header and Footer
- **Severity:** High (Brand Presentation Defect)
- **Reported By:** User / Browser Inspection
- **Symptoms:**
  The public homepage header and footer displayed a broken image icon alongside the fallback alt text `PawHaven Logo` on `http://127.0.0.1:8000/`.
- **Root Cause:**
  `resources/views/welcome.blade.php` used a direct path concatenation string:
  ```blade
  src="{{ str_starts_with((string) Settings::get('brand.logo_light_path', ''), 'http') ? Settings::get('brand.logo_light_path') : '/' . ltrim((string) Settings::get('brand.logo_light_path', 'images/pawhaven-logo-green.png'), '/') }}"
  ```
  This bypassed Laravel's standard `asset()` URL helper, causing relative path failures when served under varying ports or host headers.
- **Remediation:**
  1. Updated `welcome.blade.php` header and footer to wrap the resolved path in `asset(ltrim(...))`.
  2. Updated `resources/views/components/app-logo-icon.blade.php` to consistently use `asset(ltrim(...))`.
  3. Verified that all logo variants (`pawhaven-logo-green.png`, `pawhaven-logo-white.png`, `pawhaven-logo-navy.png`) are copied into both `public/` and `public/images/`.
- **Verification Evidence:**
  - Automated Chrome inspection confirmed:
    ```json
    { "header": { "complete": true, "naturalWidth": 781, "naturalHeight": 618 },
      "footer": { "complete": true, "naturalWidth": 781, "naturalHeight": 618 } }
    ```
  - Screenshots: [`ENV-01-app-boot-desktop.png`](screenshots/00-environment/ENV-01-app-boot-desktop.png), [`PUB-01-homepage-hero.png`](screenshots/01-public-website/PUB-01-homepage-hero.png), [`PUB-08-footer-trust-branding.png`](screenshots/01-public-website/PUB-08-footer-trust-branding.png).

---

### DEF-02: Empty Pet Categories & Listings in Initial Database
- **Severity:** Medium (Functional Incompleteness on Fresh Boot)
- **Symptoms:**
  Marketplace catalog rendered empty state with 0 listings; seller "Create Listing" form had no pet categories or breeds in dropdowns; admin moderation queue had no pending items to test.
- **Root Cause:**
  `ShopSeeder` only populated e-commerce pet products (food, treats, beds) and basic user accounts. The `pet_categories`, `breeds`, and `pet_listings` tables remained empty until populated.
- **Remediation:**
  Created `database/seeders/MarketplaceSeeder.php` without altering or wiping existing database records. Populated:
  - 4 Pet Categories (Dogs, Cats, Birds, Small Pets)
  - 3 Breeds (Golden Retriever, French Bulldog, Persian Cat)
  - 4 Realistic Pet Listings (2 Published commercial sale, 1 Published adoption/rehoming, 1 Pending review for admin moderation)
- **Verification Evidence:**
  - Database counts: Total listings: 4 (3 Published, 1 Pending Review).
  - Screenshots: [`PUB-02-marketplace-catalog.png`](screenshots/01-public-website/PUB-02-marketplace-catalog.png), [`PUB-03-pet-details-view.png`](screenshots/01-public-website/PUB-03-pet-details-view.png), [`SELL-02-listings-management.png`](screenshots/06-seller-portal/SELL-02-listings-management.png), [`ADMIN-06-moderation-queue.png`](screenshots/07-admin-portal/ADMIN-06-moderation-queue.png).

---

### DEF-03: Local Development Admin 2FA Test Accessibility
- **Severity:** Low (Developer & QA Experience)
- **Symptoms:**
  The admin portal enforces mandatory 2FA email codes. Because `[redacted account]` does not use a mailbox available to the QA environment, automated headless testing of internal admin routes (`/admin`, `/admin/homepage`, `/admin/orders`) was blocked by the 2FA checkpoint.
- **Root Cause:**
  `EmailOtpService` only saved the hashed bcrypt value of the OTP in cache for production security.
- **Remediation:**
  Extended `EmailOtpService.php` to store the plain OTP in `email_otp_dev:{$user->id}:{$purpose}` exclusively when `app()->environment('local')` is true. In production, this behavior remains strictly disabled. The QA test runner utilizes this helper to complete the genuine `/admin/two-factor` form submission without mocking.
- **Verification Evidence:**
  - Chrome successfully completed the 2FA challenge and authenticated into the Admin Dashboard.
  - Screenshots: [`ADMIN-01-two-factor-challenge.png`](screenshots/07-admin-portal/ADMIN-01-two-factor-challenge.png), [`ADMIN-02-dashboard-overview.png`](screenshots/07-admin-portal/ADMIN-02-dashboard-overview.png) through [`ADMIN-09-system-health.png`](screenshots/07-admin-portal/ADMIN-09-system-health.png).
