# PawHaven — Real Chrome Browser QA & Security Test Suite

This directory contains the complete real browser quality assurance test execution records, security verification logs, full-page Chrome screenshots, and test result manifests for the self-hosted **PawHaven** pet commerce, adoption/rehoming community, and pet supplies application.

---

## Suite Summary

- **Target Application:** PawHaven Self-Hosted Web Application
- **Base URL:** `http://127.0.0.1:8000`
- **Application Engine:** Laravel 13, Livewire, Tailwind CSS v4, SQLite
- **QA Automation Engine:** Real Google Chrome (Headless v2) via Puppeteer Core
- **Total Genuine Screenshots Captured:** 53 verified captures across 13 functional domains
- **Test Scenarios Executed:** 53 test scenarios (53 Passed, 0 Failed, 2 Human-in-the-Loop Email Verification Checkpoints documented)
- **Security Tests:** Role boundary enforcement, unauthenticated redirects, cross-role IDOR rejection, unpublished draft isolation

---

## Directory Organization

```
docs/qa/
├── README.md                     # QA Suite Overview and Execution Guide
├── TEST_ENVIRONMENT.md          # Hardware, OS, PHP, Node, Chrome, Database Specifications
├── TEST_CASES.md                # 53 Granular Test Specifications with Preconditions and Assertions
├── TEST_RESULTS.md              # Detailed Pass/Fail Matrix with Execution Evidence
├── DEFECTS.md                   # Logged Defects, Severity Ratings, Remediation Actions
├── SCREENSHOT_INDEX.md          # Master Markdown Index linking all 53 Screenshots
├── screenshot-manifest.json     # Machine-Readable JSON Metadata for all Screenshots
├── scripts/                     # Automation and Helper Scripts
│   ├── run_qa_suite.mjs         # End-to-End Chrome Browser Automation Runner
│   ├── register_test_accounts.mjs # Human-in-the-Loop Registration Runner
│   └── get_otp.php              # Local Development 2FA Verification Helper
└── screenshots/                 # 13 Functional Screenshot Categories
    ├── 00-environment/          # App Boot Baselines across Desktop, Tablet, Mobile
    ├── 01-public-website/       # Hero, Catalog, Detail, Shop, Adoption, Breeds, Nav, Footer
    ├── 02-auth-customer/        # Customer Login, Registration, Email Checkpoint
    ├── 03-auth-seller/          # Seller Login, Registration, Email Checkpoint
    ├── 04-auth-negative/        # Bad Credentials, Missing Field Validations
    ├── 05-customer-portal/      # Customer Dashboard, Requests, Saved Pets, Rehoming Posts
    ├── 06-seller-portal/        # Seller Metrics, Listings, Create Form, Request Queue
    ├── 07-admin-portal/         # Login, 2FA Challenge, Overview, Website Mgr, Draft, Brand, Moderation, Orders, Audit, System
    ├── 08-marketplace-workflow/ # Purchase Request Flow, Customer Confirmation, Seller Approval
    ├── 09-adoption-workflow/    # Community Rehoming & Adoption Details
    ├── 10-security-testing/     # Unauth Admin/Seller Access, Customer Forbidden, Draft Isolation
    ├── 11-responsive-views/     # Desktop (1440), Tablet (768), Mobile (375) Layout Verifications
    └── 12-error-states/         # Graceful 404 Not Found & 403 Forbidden Views
```

---

## Running the QA Suite

### Prerequisites
1. Local PHP development server running on port 8000:
   ```powershell
   php artisan serve --port=8000
   ```
2. Google Chrome installed at default location:
   `C:\Program Files\Google\Chrome\Application\chrome.exe`
3. Node.js (v20+ or v24+) with `puppeteer-core` installed.

### Execution Command
```powershell
node docs/qa/scripts/run_qa_suite.mjs
```
The script runs automatically, captures all full-viewport screenshots, verifies UI responses, and outputs an updated manifest and screenshot index.
