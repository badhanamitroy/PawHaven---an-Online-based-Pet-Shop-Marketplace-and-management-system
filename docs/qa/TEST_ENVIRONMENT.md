# PawHaven QA Test Environment Specification

This document details the exact hardware, operating system, runtime dependencies, web application configuration, and browser automation harness used during the real Chrome browser QA and security testing of PawHaven.

---

## 1. Host Operating System & Hardware

| Parameter | Specification | Notes |
|:---|:---|:---|
| **Operating System** | Windows 11 Enterprise (64-bit) | Workstation build |
| **Shell Environment** | Microsoft PowerShell 7 / Windows PowerShell | Windows native pathing |
| **Workspace Root** | `D:\PawHaven\PH Web Version\WebApp` | Project installation directory |
| **Repository Root** | `D:\PawHaven` | Root documentation & logos repository |

---

## 2. Web Application Runtime Stack

| Component | Version | Configuration Details |
|:---|:---|:---|
| **PHP Runtime** | PHP 8.5.0-dev (CLI/CGI) | Extensions: OpenSSL, PDO, PDO_SQLite, cURL, MBString |
| **Laravel Framework** | Laravel 13.x | Full MVC, Fortify auth, Livewire reactive UI |
| **Frontend Tooling** | Vite Plus (`vite.config.js`) | Tailwind CSS v4, Flux UI, ES Module bundling |
| **Database Engine** | SQLite 3 | `database/database.sqlite` (WAL journaling mode) |
| **Cache Driver** | Database (`cache` table) | Persistent multi-process cache |
| **Session Driver** | Database (`sessions` table) | Encrypted HTTP-only cookies (`pawhaven-session`) |
| **Mail Driver** | SMTP (`smtp.gmail.com:587`) | TLS encryption enabled |

---

## 3. Browser Automation Harness

| Component | Specification | Purpose |
|:---|:---|:---|
| **Browser Binary** | Google Chrome `131.0.6778.x` | `C:\Program Files\Google\Chrome\Application\chrome.exe` |
| **Automation Engine** | `puppeteer-core` v24.x | Direct Chrome DevTools Protocol (CDP) orchestration |
| **Viewports Tested** | • Desktop: `1440x900` (16:10)<br>• Tablet: `768x1024` (3:4 portrait)<br>• Mobile: `375x812` (iPhone-class portrait) | Full responsive layout validation |
| **Local Web Server** | `php artisan serve --port=8000` | Single-threaded built-in web server listening on `127.0.0.1:8000` |

---

## 4. Test Accounts & Credential Topology

| Role | Email Address | Verification State | 2FA State | Primary Purpose |
|:---|:---|:---:|:---:|:---|
| **Administrator** | `badhanamitroy571@gmail.com` | Verified | Enforced (Email OTP) | Platform management, Website Manager, Moderation |
| **Seller** | `seller@pawhaven.com` | Verified | Optional | Commercial pet inventory, order request fulfillment |
| **Customer** | `customer@pawhaven.com` | Verified | Optional | Marketplace exploration, purchase order requests |
| **Test Seller** | `badhanamitroy2nd@gmail.com` | Unverified (Registered during QA) | Checkpoint Enforced | Human-in-the-loop registration flow testing |
| **Test Customer** | `badhancse28@gmail.com` | Unverified (Registered during QA) | Checkpoint Enforced | Human-in-the-loop registration flow testing |

> [!NOTE]
> All passwords and secret tokens adhere to strict security masking policies and are never exposed in terminal logs, source control, test reports, or generated user guides.
