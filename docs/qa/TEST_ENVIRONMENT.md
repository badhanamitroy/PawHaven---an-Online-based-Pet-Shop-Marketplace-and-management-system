# PawHaven QA Test Environment Specification

This document summarizes the historical local QA environment. It contains no real account identifiers or machine-specific paths. The environment and test claims below have not been independently rerun as part of the current commercial-readiness audit.

---

## 1. Host Operating System & Hardware

| Parameter | Specification | Notes |
|:---|:---|:---|
| **Operating System** | Windows 11 Enterprise (64-bit) | Workstation build |
| **Shell Environment** | Microsoft PowerShell 7 / Windows PowerShell | Windows native pathing |
| **Workspace Root** | `<project-root>` | Use the directory where PawHaven is installed |
| **Repository Root** | `<repository-root>` | Use the checked-out source directory |

---

## 2. Web Application Runtime Stack

| Component | Version | Configuration Details |
|:---|:---|:---|
| **PHP Runtime** | PHP 8.5.0 (CLI/CGI) | Extensions observed: OpenSSL, PDO, PDO_SQLite, cURL, MBString |
| **Laravel Framework** | Laravel 13.x | Full MVC, Fortify auth, Livewire reactive UI |
| **Frontend Tooling** | Vite Plus (`vite.config.js`) | Tailwind CSS v4, Flux UI, ES Module bundling |
| **Database Engine** | SQLite 3 | `database/database.sqlite` (WAL journaling mode) |
| **Cache Driver** | Database (`cache` table) | Persistent multi-process cache |
| **Session Driver** | Database (`sessions` table) | Encrypted HTTP-only cookies (`pawhaven-session`) |
| **Mail Driver** | SMTP | Use a provider and credentials configured for the deployment; secret values are intentionally omitted |

---

## 3. Browser Automation Harness

| Component | Specification | Purpose |
|:---|:---|:---|
| **Browser Binary** | Chromium-based browser | Set `CHROME_PATH` for the local browser executable when needed |
| **Automation Engine** | `puppeteer-core` | Browser automation via Chrome DevTools Protocol (CDP) |
| **Viewports Tested** | Desktop, tablet, and mobile sizes were used in earlier QA | This historical statement is not a current regression result |
| **Local Web Server** | `php artisan serve --port=8000` | Local development only; no network-level smoke test was completed in the current audit |

The QA scripts read `CHROME_PATH`, optional `PAWHAVEN_QA_BASE_URL`, and role-specific `PAWHAVEN_QA_ADMIN_EMAIL` / `PAWHAVEN_QA_ADMIN_PASSWORD`, `PAWHAVEN_QA_CUSTOMER_EMAIL` / `PAWHAVEN_QA_CUSTOMER_PASSWORD`, and `PAWHAVEN_QA_SELLER_EMAIL` / `PAWHAVEN_QA_SELLER_PASSWORD` from the local process environment. Supply isolated synthetic test accounts; never place real credentials in scripts, logs, or source control.

---

## 4. Test Accounts & Credential Topology

| Role | Account identifier | Verification state | 2FA state | Primary purpose |
|:---|:---|:---:|:---:|:---|
| **Administrator** | Omitted | Verified in the historical environment | Email OTP | Platform management |
| **Marketplace Seller** | Omitted | Verified in the historical environment | Optional | Marketplace listing workflow |
| **Customer** | Omitted | Verified in the historical environment | Optional | Marketplace buyer workflow |
| **Test Seller** | Omitted | Unverified test account | Registration checkpoint | Registration flow |
| **Test Customer** | Omitted | Unverified test account | Registration checkpoint | Registration flow |

> [!NOTE]
> Do not store real account email addresses, passwords, tokens, or other personal data in QA documentation or generated guides. Use isolated synthetic test accounts.
