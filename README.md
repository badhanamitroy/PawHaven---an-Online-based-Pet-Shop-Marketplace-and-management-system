# PawHaven — Pet Shop, Marketplace & Multi-Branch Management System

**Find Pets. Give Hope. Shop with Love.**

[![Laravel 13](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP 8.3+](https://img.shields.io/badge/PHP-%5E8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net)
[![Tailwind CSS 4](https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Livewire 4](https://img.shields.io/badge/Livewire-4.x-FB70A9?style=for-the-badge&logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Vite](https://img.shields.io/badge/Vite-8.x-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev)

PawHaven is a full-stack Laravel enterprise web platform integrating a public pet marketplace, an ethical adoption and rescue hub, a single-brand pet supplies store, multi-branch business operations with inventory reservations, and an internal employee recruitment pipeline.

**Repository URL:** [https://github.com/badhanamitroy/PawHaven---an-Online-based-Pet-Shop-Marketplace-and-management-system](https://github.com/badhanamitroy/PawHaven---an-Online-based-Pet-Shop-Marketplace-and-management-system)

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
  - [1. Storefront & Marketplace](#1-storefront--marketplace)
  - [2. Redesigned Admin Console](#2-redesigned-admin-console)
  - [3. Dynamic Brand Media & Cache Invalidation](#3-dynamic-brand-media--cache-invalidation)
  - [4. Custom Paw Cursor](#4-custom-paw-cursor)
  - [5. Branch Operations & Inventory](#5-branch-operations--inventory)
  - [6. Careers & Recruitment Workflow](#6-careers--recruitment-workflow)
- [Architecture & Role Boundaries](#architecture--role-boundaries)
- [Technology Stack](#technology-stack)
- [Canonical Media Directory](#canonical-media-directory)
- [Application Directory Structure](#application-directory-structure)
- [Getting Started & Local Development](#getting-started--local-development)
  - [Prerequisites](#prerequisites)
  - [Step-by-Step Installation](#step-by-step-installation)
  - [Environment Configuration](#environment-configuration)
  - [Database Migrations & First Admin Setup](#database-migrations--first-admin-setup)
  - [Background Queues & Scheduled Workers](#background-queues--scheduled-workers)
- [Testing & Quality Assurance](#testing--quality-assurance)
- [Security Architecture](#security-architecture)
- [Current Roadmap & Limitations](#current-roadmap--limitations)
- [Contributing](#contributing)
- [License & Author](#license--author)

---

## Overview

PawHaven bridges customer-facing commerce with deep back-office business controls. The platform operates around a single primary company structure that can span multiple physical and regional branches.

Access and authority are strictly separated between **administrative platform concerns** and **company commerce operations**:

1. **Customers:** Browse pet listings, view breed guides, explore adoption candidates, request pet purchases or inquiries, order supplies, and track their orders.
2. **Marketplace Sellers:** Independent pet owners or breeders who submit listings for administrator moderation and manage listing inquiries.
3. **Employee Sellers (Sales Associates):** Staff members assigned to a specific branch for branch-level store operations.
4. **Branch Managers:** Staff members authorized to supervise orders, inventory, and branch operations for their designated branch.
5. **Shop Owner:** Holds company-wide business operational authority over products, categories, branches, and staff memberships.
6. **Platform Admins:** Manage platform-level configurations, moderation queues, brand identity, announcements, careers, and security auditing.

---

## Key Features

### 1. Storefront & Marketplace
- **Pet Listings & Inquiries:** Category-driven browsing (Dogs, Cats, Birds, Rabbits, etc.) with detailed health history, vaccination records, and seller inquiries.
- **Pet Shop Catalogue:** PawHaven direct-to-consumer pet supplies and accessories.
- **Breed Encyclopedia:** Comprehensive breed profiles with size, temperament, shedding, and grooming guidelines.
- **Adoption & Rescue Hub:** Ethical rehoming listings with special adoption application workflows.

### 2. Redesigned Admin Console
Built using a cohesive visual design system with dark forest green navigation (`#0F2A20`), primary action emeralds (`#17603F`), clean white cards, and Inter typography:
- **Admin Dashboard:** Real-time KPI summaries, operational alerts, recent order activity, and quick-action navigation.
- **Brand Identity & Media:** Real-time brand configuration, live storefront header/sidebar preview, color picker palette, and search engine metadata preview.
- **Pet Categories:** Hierarchical category management with active listings counters and publication states.
- **Homepage Customizer:** Live customizer for hero banners, announcements, featured collections, and trust highlights.
- **Marketplace Listings & Moderation:** Full review queue with one-click approve/reject actions, listing inspection modals, and seller feedback notes.
- **Companies & Branch Access:** Multi-branch oversight, staff role assignment, and branch assignment controls.
- **Breed Profiles & Adoption Hub:** Breed directory management and non-profit adoption partner moderation.

### 3. Dynamic Brand Media & Cache Invalidation
Centralized through `App\Services\MediaManager`:
- **Canonical Filesystem Location:** `public/Media/` contains canonical assets (`Hero-Banner.png`, `PawHeaven-Green-logo.png`, `PawHeaven-White-logo.png`, `PawHeaven-NavyBlue-logo.png`, `PawHeaven-icon.png`).
- **Zero-Friction Overwrites:** If a buyer or administrator manually replaces an asset file directly in the filesystem, or uploads a replacement through the Admin Panel, the application automatically computes a timestamp-based cache buster (`?v=<filemtime>`).
- **No Manual Cache Clearing:** Browsers and CDNs immediately receive the fresh asset without requiring `php artisan cache:clear` or code changes.
- **Transactional Atomic Uploads:** Validates MIME types, extensions, dimensions, and file size limits, staging files in temporary storage with automatic rollback and error logging on failure.

### 4. Custom Paw Cursor
- **Brand-Themed Silhouette:** High-contrast dark green (`#17603F`) paw silhouette with white stroke and soft drop shadow (`public/Media/paw-cursor.svg` with 32×32 PNG fallback).
- **Admin Toggle:** Persisted under `brand.custom_paw_cursor` in the settings table.
- **Interactive Admin Sandbox:** A live test card in the Admin Brand Identity page allowing administrators to test cursor hover states on buttons, links, and text fields before saving.
- **Non-Intrusive Design:** Scoped to desktop fine-pointer devices (`@media (hover: hover) and (pointer: fine)`). Native text selection (`cursor: text !important`) is strictly preserved on paragraphs, headings, inputs, and textareas. Touchscreens and mobile devices are completely unaffected.

### 5. Branch Operations & Inventory
- **Single-Company Multi-Branch Scope:** Each branch maintains its own stock counts and fulfilled orders.
- **Inventory Reservation:** Stock is automatically reserved upon order creation and safely restored if an order is cancelled.
- **Role Enforcement:** Branch Managers cannot modify company-wide product definitions or stock quantities outside their assigned branch.

### 6. Careers & Recruitment Workflow
- **Public Vacancy Board:** Publicly listed job openings with online CV submissions.
- **Application Review:** Private CV downloads stored on protected disks with reviewer notes.
- **Secure Job Offers:** Generates an offer link requiring email OTP verification before creating the employee account.
- **Shop Owner Final Approval:** The new employee membership remains inactive until approved by the Shop Owner.

---

## Architecture & Role Boundaries

PawHaven separates **system accounts** (`users.role`) from **business positions** (`business_memberships`):

| Role / Position | Database Representation | Operational Boundary |
|---|---|---|
| **Platform Admin** | `users.role = admin` | Platform configuration, moderation, brand identity, user administration, careers, and security audit logs. |
| **Shop Owner** | `users.role = staff` + `membership.role = owner` (no branch) | Company-wide business operations: product catalog, stock allocations, branch management, and employee approvals. |
| **Branch Manager** | `users.role = staff` + `membership.role = manager` (branch-scoped) | Assigned branch orders, local inventory viewing, and branch employee directory. Cannot modify company-wide catalog. |
| **Employee Seller** | `users.role = staff` + `membership.role = seller` (branch-scoped) | Branch-scoped operational activities and product inquiries. |
| **Marketplace Seller** | `users.role = seller` | Own marketplace pet listings and direct buyer inquiries. |
| **Customer** | `users.role = customer` | Personal profile, pet purchase requests, shop order tracking, and adoption applications. |

---

## Technology Stack

| Layer | Technologies |
|---|---|
| **Backend Runtime** | PHP `^8.3` (running on PHP 8.3+) |
| **Web Framework** | Laravel `^13.x` |
| **Authentication & 2FA** | Laravel Fortify `^1.37`, Email OTP challenges, and TOTP support |
| **Frontend Framework** | Blade templates, Livewire `^4.1`, Flux `^2.13`, Alpine.js |
| **Styling & Design System** | Tailwind CSS `^4.0`, Inter font, custom CSS design system |
| **Build Tools** | Vite `^8.0`, Laravel Vite Plugin `^3.1`, Vite Plus |
| **Database Support** | SQLite (development default), MySQL, MariaDB, PostgreSQL |
| **Testing & Quality** | PHPUnit `^12.5`, Laravel Test Runner, Laravel Pint |

---

## Canonical Media Directory

All canonical media assets are organized in the public web root:

```text
public/Media/
├── Images/
│   ├── Hero-Banner.png          # Storefront homepage hero banner (1920x640)
│   ├── birds-category.jpg       # Category showcase thumbnail
│   ├── british-shorthair.jpg    # Category showcase thumbnail
│   ├── holland-lop.webp         # Category showcase thumbnail
│   └── rabbits-category.jpg     # Category showcase thumbnail
├── Logos/
│   ├── PawHeaven-Green-logo.png # Primary green logo (light surfaces / navbar)
│   ├── PawHeaven-White-logo.png # White / dark logo (footer / admin sidebar)
│   ├── PawHeaven-NavyBlue-logo.png # Navy logo (invoices / printable receipts)
│   └── PawHeaven-icon.png       # Square icon & browser favicon (512x512)
├── paw-cursor.svg               # Scalable vector custom cursor
└── paw-cursor.png               # 32x32 transparent PNG cursor fallback
```

---

## Application Directory Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/               # Admin dashboard, brand, moderation, breeds, branches
│   │   ├── Customer/            # Customer profile, orders, purchase requests
│   │   └── Seller/              # Marketplace seller listing management
│   ├── Middleware/              # Role-aware route gates (Admin, Staff, Seller, Customer)
│   └── Requests/                # Form request validation classes
├── Models/                      # Eloquent models (User, PetListing, Order, Company, Branch, etc.)
├── Policies/                    # Authorization policies for orders, listings, and branches
└── Services/
    ├── MediaManager.php         # Canonical asset URLs, validation, and atomic replacements
    ├── SettingsService.php      # Key-value persistent settings with memory caching
    ├── BusinessAccessService.php# Multi-branch membership & role resolution
    └── DeviceSessionService.php # Device session tracking and security pruning
resources/
├── views/
│   ├── admin/                   # Redesigned Admin Console views
│   ├── layouts/                 # Base layouts (Admin, Auth, App)
│   ├── partials/                # Reusable partials (custom-cursor, favicon, head)
│   └── welcome.blade.php        # Public storefront & hero section
routes/
├── web.php                      # Public storefront, customer, and auth routes
├── admin.php                    # Platform Admin and Shop Owner console routes
└── seller.php                   # Marketplace Seller portal routes
```

---

## Getting Started & Local Development

### Prerequisites

- **PHP 8.3 or higher** with required extensions (`pdo`, `sqlite3`, `curl`, `mbstring`, `openssl`).
- **Composer** (latest version).
- **Node.js** (`^20.19.0`, `^22.18.0`, or `>=24.11.0`) with **npm**.
- **SQLite** (default local database) or a configured MySQL/PostgreSQL server.

### Step-by-Step Installation

#### 1. Clone the Repository
```bash
git clone https://github.com/badhanamitroy/PawHaven---an-Online-based-Pet-Shop-Marketplace-and-management-system.git PawHaven
cd PawHaven
```

#### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm ci
```

#### 3. Environment Setup
Create your local environment file:

**Windows PowerShell:**
```powershell
Copy-Item .env.example .env
php artisan key:generate
```

**Linux / macOS:**
```bash
cp .env.example .env
php artisan key:generate
```

#### 4. Prepare Local Database
By default, the application is configured to use SQLite.

**Windows PowerShell:**
```powershell
if (-not (Test-Path database/database.sqlite)) {
    New-Item -ItemType File -Path database/database.sqlite | Out-Null
}
```

**Linux / macOS:**
```bash
touch database/database.sqlite
```

Run database migrations:
```bash
php artisan migrate
```

#### 5. Storage Symlink
Create the storage symbolic link for user uploads:
```bash
php artisan storage:link
```

#### 6. Provision First Platform Administrator
Create the initial Platform Admin account interactively:
```bash
php artisan admin:create
```
Follow the prompt to provide the administrator name, email, and password.

#### 7. Build Assets & Start Development Servers
In your first terminal, start the asset compiler:
```bash
npm run dev
```

In a second terminal, start the Laravel development server:
```bash
php artisan serve
```

Visit the application at: `http://127.0.0.1:8000`

---

## Background Queues & Scheduled Workers

The application uses the `database` queue driver and includes scheduled security cleanup tasks.

### Queue Worker
Run the queue worker in a separate terminal:
```bash
php artisan queue:work
```

### Scheduled Task Runner
For local testing of daily session pruning:
```bash
php artisan schedule:work
```

In production, register a single cron entry on the host:
```bash
* * * * * cd /path-to-pawhaven && php artisan schedule:run >> /dev/null 2>&1
```

---

## Testing & Quality Assurance

Run the test suite with PHPUnit:
```bash
php artisan test
```

Run static analysis and code styling checks:
```bash
composer test
```

Compile frontend production assets:
```bash
npm run build
```

---

## Security Architecture

- **Role & Scope Segregation:** System roles (`admin`, `staff`, `seller`, `customer`) and business memberships (`owner`, `manager`, `seller`) are verified server-side in controllers and policies.
- **Audit Trails:** Administrative, moderation, and business actions are recorded in the `audit_logs` table with user and timestamp context.
- **Expiring Credentials:** Employee invitation tokens and career offers are single-use and time-bound.
- **OTP Protection:** Email OTP verification guards sensitive workflows including offer acceptance and staff onboarding.
- **Private Storage:** Application CVs and internal documents are stored on non-public disks and served exclusively via authorized streaming controllers.

---

## Contributing

1. Fork the repository.
2. Create a descriptive feature branch (`git checkout -b feature/amazing-feature`).
3. Commit your changes (`git commit -m 'feat: add amazing feature'`).
4. Push to the branch (`git push origin feature/amazing-feature`).
5. Open a Pull Request.

---

## License & Author

- **Project:** PawHaven — Pet Shop, Marketplace & Multi-Branch Management System
- **Lead Developer & Maintainer:** [Badhan Roy](https://github.com/badhanamitroy)
- **License:** Open-source software licensed under the [MIT License](https://opensource.org/licenses/MIT).
