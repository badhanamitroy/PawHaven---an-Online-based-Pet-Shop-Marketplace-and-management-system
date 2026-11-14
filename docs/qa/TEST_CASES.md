# PawHaven Quality Assurance — Master Test Cases Specification

This document defines the formal test specifications, preconditions, actions, and expected verification criteria for each scenario executed in the real Chrome browser QA suite.

---

## Domain 00: Environment & Baseline Verification

### TC-ENV-01: Application Boot Desktop Baseline
- **Identifier:** `ENV-01`
- **Category:** `00-environment`
- **Viewport:** `1440x900`
- **Preconditions:** Server active on `http://127.0.0.1:8000`, database initialized.
- **Action:** Navigate to root `/`.
- **Expected Result:** HTTP 200 OK, full DOM hydration, brand logo loaded, no console script errors.
- **Evidence:** `docs/qa/screenshots/00-environment/ENV-01-app-boot-desktop.png`

### TC-ENV-02: Application Boot Tablet Baseline
- **Identifier:** `ENV-02`
- **Category:** `00-environment`
- **Viewport:** `768x1024`
- **Preconditions:** Server active.
- **Action:** Set viewport to 768px width, navigate to `/`.
- **Expected Result:** Header elements reflow cleanly, navigation transitions toward mobile layout, no horizontal scroll.
- **Evidence:** `docs/qa/screenshots/00-environment/ENV-02-app-boot-tablet.png`

### TC-ENV-03: Application Boot Mobile Baseline
- **Identifier:** `ENV-03`
- **Category:** `00-environment`
- **Viewport:** `375x812`
- **Preconditions:** Server active.
- **Action:** Set viewport to 375px width, navigate to `/`.
- **Expected Result:** Hamburger menu triggers, hero typography scales proportionally, logo stays contained.
- **Evidence:** `docs/qa/screenshots/00-environment/ENV-03-app-boot-mobile.png`

---

## Domain 01: Public Website & Design System

### TC-PUB-01: Homepage Hero Section & Brand Header
- **Identifier:** `PUB-01`
- **Expected Result:** Primary headline "Your next best friend is waiting." is visible with CTAs linking to `#find-pets` and `#pet-shop`.
- **Evidence:** `docs/qa/screenshots/01-public-website/PUB-01-homepage-hero.png`

### TC-PUB-02: Public Marketplace Pet Catalog
- **Identifier:** `PUB-02`
- **Expected Result:** Displays active commercial pet listings with image thumbnails, price formatting, location tags, and breed badges.
- **Evidence:** `docs/qa/screenshots/01-public-website/PUB-02-marketplace-catalog.png`

### TC-PUB-03: Pet Listing Detail View
- **Identifier:** `PUB-03`
- **Expected Result:** Opens detailed listing page `/listings/{id}` with high-resolution image gallery, seller profile, description, health certification, and purchase request action box.
- **Evidence:** `docs/qa/screenshots/01-public-website/PUB-03-pet-details-view.png`

### TC-PUB-04: Pet Shop Supplies & Nutrition Module
- **Identifier:** `PUB-04`
- **Expected Result:** Renders categorized products with pricing, stock status, and add-to-cart controls.
- **Evidence:** `docs/qa/screenshots/01-public-website/PUB-04-pet-shop-products.png`

### TC-PUB-05: Adoption & Rescue Community Highlights
- **Identifier:** `PUB-05`
- **Expected Result:** Highlight section for non-profit and community pet rehoming with non-commercial adoption badges.
- **Evidence:** `docs/qa/screenshots/01-public-website/PUB-05-adoption-rescue-highlights.png`

### TC-PUB-06: Breed Discovery Encyclopedia
- **Identifier:** `PUB-06`
- **Expected Result:** Searchable breed cards showing species, temperament traits, and care requirements.
- **Evidence:** `docs/qa/screenshots/01-public-website/PUB-06-breed-discovery.png`

### TC-PUB-07: Mobile Navigation Drawer
- **Identifier:** `PUB-07`
- **Expected Result:** Drawer toggles open on mobile screen, displaying accessible navigation links and authentication buttons.
- **Evidence:** `docs/qa/screenshots/01-public-website/PUB-07-mobile-nav-drawer.png`

### TC-PUB-08: Platform Footer & Trust Architecture
- **Identifier:** `PUB-08`
- **Expected Result:** Deep forest green footer (`#173F32`) with white logo, platform links, legal notice, and trust pillars.
- **Evidence:** `docs/qa/screenshots/01-public-website/PUB-08-footer-trust-branding.png`

---

## Domain 02 & 03: Authentication & Role Onboarding

### TC-AUTH-01: Customer Login Portal View
- **Identifier:** `AUTH-01`
- **Expected Result:** Dedicated customer login tab with email, password, remember session, and forgot password link.
- **Evidence:** `docs/qa/screenshots/02-auth-customer/AUTH-01-customer-login-view.png`

### TC-AUTH-02: Seller Login Portal View
- **Identifier:** `AUTH-02`
- **Expected Result:** Tabbed role switcher sets URL to `?role=seller`, highlighting merchant credentials notice.
- **Evidence:** `docs/qa/screenshots/03-auth-seller/AUTH-02-seller-login-view.png`

### TC-AUTH-03: Public Registration Role Selector
- **Identifier:** `AUTH-03`
- **Expected Result:** Interactive toggle between Customer Account and Seller Account. Admin self-registration is strictly absent.
- **Evidence:** `docs/qa/screenshots/02-auth-customer/AUTH-03-registration-role-selector.png`

### TC-AUTH-04: Customer Registration Form Submission
- **Identifier:** `AUTH-04`
- **Expected Result:** Displays customer fields: Name, Email, Password, Password Confirmation.
- **Evidence:** `docs/qa/screenshots/02-auth-customer/AUTH-04-customer-registration-form.png`

### TC-AUTH-05: Seller Registration Form Submission
- **Identifier:** `AUTH-05`
- **Expected Result:** Displays seller merchant fields with verified onboarding guidance.
- **Evidence:** `docs/qa/screenshots/03-auth-seller/AUTH-05-seller-registration-form.png`

### TC-AUTH-CUST-02: Customer Mandatory Email Verification Checkpoint
- **Identifier:** `AUTH-CUST-02`
- **Expected Result:** Upon registration of `badhancse28@gmail.com`, browser immediately lands on `/email/verify` with resend button.
- **Evidence:** `docs/qa/screenshots/02-auth-customer/AUTH-CUST-02-email-verify-checkpoint.png`

### TC-AUTH-SELL-02: Seller Mandatory Email Verification Checkpoint
- **Identifier:** `AUTH-SELL-02`
- **Expected Result:** Upon registration of `badhanamitroy2nd@gmail.com`, browser immediately lands on `/email/verify`.
- **Evidence:** `docs/qa/screenshots/03-auth-seller/AUTH-SELL-02-email-verify-checkpoint.png`

---

## Domain 04: Authentication Negative & Boundary Testing

### TC-AUTH-NEG-01: Invalid Credentials Feedback
- **Identifier:** `AUTH-NEG-01`
- **Expected Result:** Displays localized validation alert "These credentials do not match our records." without leaking user existence.
- **Evidence:** `docs/qa/screenshots/04-auth-negative/AUTH-NEG-01-invalid-credentials.png`

### TC-AUTH-NEG-02: Blank Form Validation Error Display
- **Identifier:** `AUTH-NEG-02`
- **Expected Result:** Submitting empty inputs triggers server-side validation error messages on all required fields.
- **Evidence:** `docs/qa/screenshots/04-auth-negative/AUTH-NEG-02-validation-errors.png`

---

## Domain 05: Customer Portal

### TC-CUST-01: Customer Dashboard Overview
- **Identifier:** `CUST-01`
- **Expected Result:** Displays authenticated customer greeting, recent order count, and quick links.
- **Evidence:** `docs/qa/screenshots/05-customer-portal/CUST-01-dashboard-overview.png`

### TC-CUST-02: Purchase Requests Timeline Tab
- **Identifier:** `CUST-02`
- **Expected Result:** Displays submitted purchase requests with seller decision timeline (Pending, Approved, Shipped).
- **Evidence:** `docs/qa/screenshots/05-customer-portal/CUST-02-purchase-requests-tab.png`

### TC-CUST-04: Saved Pets & Bookmarks Tab
- **Identifier:** `CUST-04`
- **Expected Result:** Lists favorited pet listings with quick browse actions.
- **Evidence:** `docs/qa/screenshots/05-customer-portal/CUST-04-saved-listings-tab.png`

### TC-CUST-05: Community Rehoming Posts Tab
- **Identifier:** `CUST-05`
- **Expected Result:** Enables customers to create and manage personal adoption posts.
- **Evidence:** `docs/qa/screenshots/05-customer-portal/CUST-05-rehoming-posts-tab.png`

---

## Domain 06: Seller Portal

### TC-SELL-01: Seller Dashboard Metrics
- **Identifier:** `SELL-01`
- **Expected Result:** 5 computed metric cards: Total Sales, Active Listings, Pending Requests, Awaiting Verification, Total Views.
- **Evidence:** `docs/qa/screenshots/06-seller-portal/SELL-01-dashboard-metrics.png`

### TC-SELL-02: Seller Listings Management
- **Identifier:** `SELL-02`
- **Expected Result:** Table of seller-owned pet listings with status badges (Published, Pending Review, Paused) and edit actions.
- **Evidence:** `docs/qa/screenshots/06-seller-portal/SELL-02-listings-management.png`

### TC-SELL-03: Create New Pet Listing Form
- **Identifier:** `SELL-03`
- **Expected Result:** Multi-attribute form: Title, Category, Breed, Price, Location, Image Uploader, Description.
- **Evidence:** `docs/qa/screenshots/06-seller-portal/SELL-03-create-listing-form.png`

### TC-SELL-04: Incoming Requests Queue
- **Identifier:** `SELL-04`
- **Expected Result:** List of buyer purchase requests with approve, decline, and payment status update buttons.
- **Evidence:** `docs/qa/screenshots/06-seller-portal/SELL-04-incoming-requests-queue.png`

---

## Domain 07: Administrator Portal & Website Manager

### TC-ADMIN-01: Dedicated Admin Console Login Screen
- **Identifier:** `ADMIN-01`
- **Expected Result:** Internal gateway at `/admin/login` restricted to authorized staff and admin accounts.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-01-login-screen.png`

### TC-ADMIN-01-2FA: Mandatory Internal 2FA Security Checkpoint
- **Identifier:** `ADMIN-01-2FA`
- **Expected Result:** Enforces 6-digit email OTP challenge screen at `/admin/two-factor` before granting admin session.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-01-two-factor-challenge.png`

### TC-ADMIN-02: Integrated Admin Dashboard Overview
- **Identifier:** `ADMIN-02`
- **Expected Result:** Platform-wide KPI counters, active users, commercial listings volume, and revenue totals.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-02-dashboard-overview.png`

### TC-ADMIN-03: Website Manager Live Editor
- **Identifier:** `ADMIN-03`
- **Expected Result:** Section-based homepage editor allowing headline modification, CTA updates, and section toggles.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-03-website-manager-editor.png`

### TC-ADMIN-04: Website Draft Preview Banner
- **Identifier:** `ADMIN-04`
- **Expected Result:** Full-fidelity preview banner indicating draft changes visible only to authenticated admins.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-04-website-preview-draft.png`

### TC-ADMIN-05: Brand Identity Settings
- **Identifier:** `ADMIN-05`
- **Expected Result:** Palette management, logo file uploaders, contact credentials, and metadata configuration.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-05-brand-identity-settings.png`

### TC-ADMIN-06: Marketplace Listing Moderation Queue
- **Identifier:** `ADMIN-06`
- **Expected Result:** Pending pet listings queue with audit approval and rejection dialogs.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-06-moderation-queue.png`

### TC-ADMIN-07: Platform Order Management
- **Identifier:** `ADMIN-07`
- **Expected Result:** Centralized order ledger displaying payment statuses, fulfillment progression, and line items.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-07-order-management.png`

### TC-ADMIN-08: Immutable Audit Logs
- **Identifier:** `ADMIN-08`
- **Expected Result:** Searchable audit trail recording staff email, action performed, timestamp, and IP address.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-08-audit-logs.png`

### TC-ADMIN-09: System Health & Diagnostics
- **Identifier:** `ADMIN-09`
- **Expected Result:** Diagnostic readout of PHP version, database engine, storage disk usage, and queue status.
- **Evidence:** `docs/qa/screenshots/07-admin-portal/ADMIN-09-system-health.png`

---

## Domain 08 & 09: Marketplace & Adoption Workflows

### TC-FLOW-01: Commercial Purchase Request Action
- **Identifier:** `FLOW-01`
- **Expected Result:** Action card on published pet listing allowing authenticated customer to submit buy request.
- **Evidence:** `docs/qa/screenshots/08-marketplace-workflow/FLOW-01-submit-purchase-request.png`

### TC-FLOW-02: Customer Request Confirmation View
- **Identifier:** `FLOW-02`
- **Expected Result:** Customer sees order record in `/dashboard?tab=requests` with "Pending Seller Review" status.
- **Evidence:** `docs/qa/screenshots/08-marketplace-workflow/FLOW-02-customer-request-confirmation.png`

### TC-FLOW-03: Seller Request Approval View
- **Identifier:** `FLOW-03`
- **Expected Result:** Seller opens `/seller/requests` and views the customer request with decision buttons.
- **Evidence:** `docs/qa/screenshots/08-marketplace-workflow/FLOW-03-seller-request-approval.png`

### TC-FLOW-04: Adoption Inquiry & Rehoming View
- **Identifier:** `FLOW-04`
- **Expected Result:** Zero-fee adoption post displays non-commercial inquiry form and foster shelter contact.
- **Evidence:** `docs/qa/screenshots/09-adoption-workflow/FLOW-04-adoption-inquiry-view.png`

---

## Domain 10: Security & Negative Testing

### TC-SEC-01: Unauthenticated Admin Route Access
- **Identifier:** `SEC-01`
- **Expected Result:** Accessing `/admin` as guest immediately redirects to `/admin/login`.
- **Evidence:** `docs/qa/screenshots/10-security-testing/SEC-01-unauth-admin-redirect.png`

### TC-SEC-02: Unauthenticated Seller Route Access
- **Identifier:** `SEC-02`
- **Expected Result:** Accessing `/seller/dashboard` as guest immediately redirects to `/login`.
- **Evidence:** `docs/qa/screenshots/10-security-testing/SEC-02-unauth-seller-redirect.png`

### TC-SEC-03: Customer Access to Seller Portal (Privilege Escalation Denial)
- **Identifier:** `SEC-03`
- **Expected Result:** Customer attempting to open `/seller/dashboard` receives 403 Forbidden denial.
- **Evidence:** `docs/qa/screenshots/10-security-testing/SEC-03-customer-seller-forbidden.png`

### TC-SEC-05: Guest Draft Homepage Isolation
- **Identifier:** `SEC-05`
- **Expected Result:** Guest attempting to access `/admin/homepage/preview` is denied and redirected to admin login.
- **Evidence:** `docs/qa/screenshots/10-security-testing/SEC-05-guest-draft-isolation.png`

---

## Domain 11: Responsive Layout Verifications

### TC-RESP-01: Homepage Desktop Layout (1440x900)
- **Identifier:** `RESP-01`
- **Expected Result:** Multi-column grid, expanded horizontal navbar, balanced whitespace.
- **Evidence:** `docs/qa/screenshots/11-responsive-views/RESP-01-home-desktop-1440.png`

### TC-RESP-02: Homepage Tablet Layout (768x1024)
- **Identifier:** `RESP-02`
- **Expected Result:** 2-column card reflow, adaptive typography, no overflow.
- **Evidence:** `docs/qa/screenshots/11-responsive-views/RESP-02-home-tablet-768.png`

### TC-RESP-03: Homepage Mobile Layout (375x812)
- **Identifier:** `RESP-03`
- **Expected Result:** Single-column stacked cards, collapsed mobile navigation.
- **Evidence:** `docs/qa/screenshots/11-responsive-views/RESP-03-home-mobile-375.png`

### TC-RESP-04: Marketplace Catalog Mobile Layout (375x812)
- **Identifier:** `RESP-04`
- **Expected Result:** Full-width pet cards, readable prices, touch-friendly tap targets.
- **Evidence:** `docs/qa/screenshots/11-responsive-views/RESP-04-catalog-mobile-375.png`

### TC-RESP-05: Seller Dashboard Tablet Layout (768x1024)
- **Identifier:** `RESP-05`
- **Expected Result:** Metric cards stack in a 2x2 grid with horizontal scroll on tables.
- **Evidence:** `docs/qa/screenshots/11-responsive-views/RESP-05-seller-tablet-768.png`

---

## Domain 12: Error States & Edge Cases

### TC-ERR-01: Graceful 404 Not Found Page
- **Identifier:** `ERR-01`
- **Expected Result:** Visiting a non-existent URL returns standard 404 view with return-to-home button.
- **Evidence:** `docs/qa/screenshots/12-error-states/ERR-01-404-not-found.png`

### TC-ERR-02: Security Authorization Denial View (403 Forbidden)
- **Identifier:** `ERR-02`
- **Expected Result:** Inspecting an unauthorized order returns 403 Forbidden or safe redirection.
- **Evidence:** `docs/qa/screenshots/12-error-states/ERR-02-403-access-denied.png`
