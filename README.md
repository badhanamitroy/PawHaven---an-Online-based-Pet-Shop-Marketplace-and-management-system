# PawHaven

**Find Pets. Give Hope. Shop with Love.**

PawHaven is a Laravel web application for pet listings, customer purchase and inquiry requests, a PawHaven-managed product catalogue, branch operations, and employee recruitment.

![Laravel 13](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)
![PHP 8.3+](https://img.shields.io/badge/PHP-%5E8.3-777BB4?logo=php&logoColor=white)
![Tailwind CSS 4](https://img.shields.io/badge/Tailwind_CSS-4.x-06B6D4?logo=tailwindcss&logoColor=white)

PawHeaven source and support details are provided with the buyer distribution.

> **Repository contents:** The GitHub `main` branch currently publishes this README only. The Laravel application source is available in a separate `PH Web Version/WebApp` source checkout and has not been published to this repository. The setup instructions below apply to a checkout that contains the Laravel project files; cloning the current GitHub branch alone is not enough to run the application.

## Contents

- [Overview](#overview)
- [Core features](#core-features)
- [Architecture and authorization](#architecture-and-authorization)
- [Technology stack](#technology-stack)
- [Application structure](#application-structure)
- [Local development](#local-development)
- [Environment configuration](#environment-configuration)
- [Database and migrations](#database-and-migrations)
- [Security](#security)
- [Testing](#testing)
- [Current limitations and roadmap](#current-limitations-and-roadmap)
- [Contributing](#contributing)
- [License](#license)
- [Maintainer](#maintainer)

## Overview

PawHaven brings together a public pet marketplace and the internal tools used to manage PawHaven products, branches, inventory, orders, and employee access. A deployment is designed around one PawHaven company with one or more branches.

The application has distinct account and business-position concepts:

- **Customers** browse listings and products, submit purchase or inquiry requests, and manage their own account activity.
- **Marketplace Sellers** are independent marketplace users who manage their own pet listings and seller-associated requests.
- **Employee Sellers / Sales Associates** work for PawHaven. They use an internal `staff` account with a branch-scoped `seller` business membership. They are not Marketplace Sellers.
- **Branch Managers** use an internal `staff` account with a branch-scoped `manager` membership.
- **The Shop Owner** has company-wide business authority through an active `owner` membership.
- **Platform Admins** manage platform-level functions. Their `admin` account does not automatically make them the Shop Owner.

## Core features

### Customers and marketplace

- Customer registration, email verification, login, and account security flows.
- Public browsing of published pet listings, listing details, breed information, and adoption or rehoming opportunities.
- Customer purchase requests and inquiries for listings, with customer dashboards for their own activity.
- Marketplace Seller listing creation and management, with administrator review before publication.
- A PawHaven product catalogue shown on the public site.
- Product buy requests that reserve available stock and create an order for review. The current interface explicitly defers a digital cart and full checkout; see [Current limitations and roadmap](#current-limitations-and-roadmap).

### Business operations

- A single installation Company with Company-owned Branch records.
- Active business memberships for Shop Owner, Branch Manager, and Employee Seller positions.
- Company-wide product and category management by the Shop Owner.
- Branch-scoped operational visibility for Managers, including assigned-branch inventory and orders.
- Inventory reservation when a product buy request is submitted, and inventory restoration when an eligible order is cancelled.
- Server-side company and branch checks in services, middleware, and policies.

### Administration

- A Platform Admin area for platform functions such as marketplace moderation, user administration, site content, branding, breeds, community settings, and system information.
- Shop Owner controls for company operations, branches, employee assignments, products, categories, inventory, and business settings.
- Audit records for significant business and administrative actions.
- Administrative invitation tools retained for exceptional or recovery cases; Careers is the primary employee recruitment workflow.

### Careers and recruitment

- A public Careers page with published vacancy announcements, vacancy details, and account-free applications.
- Admin-managed vacancy publication, application review, internal notes, and private CV downloads.
- Formal offers with an assigned position, branch, terms, deadline, and secure registration link.
- Offer-bound email OTP verification before employee User and membership creation.
- Shop Owner approval before the new employee membership becomes operationally active.

## Architecture and authorization

PawHaven separates a system account role from an employee's business position. Employee positions are stored in `business_memberships`; a membership is scoped to the installation Company and, for employees, to a Branch. Business access is enforced on the server through middleware, services, policies, and database relationships.

| Account | Representation | Main access boundary |
| --- | --- | --- |
| Platform Admin | `users.role = admin` | Platform administration and recruitment. Does not receive implicit Shop Owner authority for business operations. |
| Shop Owner | `users.role = staff` plus one active `owner` membership with no Branch | Company-wide business operations, including catalogue, categories, inventory, branches, and employee assignments. |
| Branch Manager | `users.role = staff` plus an active `manager` membership for a Branch | Permitted operational access for the assigned Branch, including branch orders, inventory viewing, and employee directory visibility. Cannot manage the company catalogue, categories, stock quantities, or employee access. |
| Employee Seller / Sales Associate | `users.role = staff` plus an active `seller` membership for a Branch | Branch-scoped product access needed for operations. Does not manage products, categories, stock, orders, or employees. A POS workflow is not implemented. |
| Marketplace Seller | `users.role = seller` | Own marketplace listings and seller-associated requests; separate from employee memberships. |
| Customer | Normally `users.role = customer` | Personal account, published marketplace content, and the customer's own requests and orders. |

The Shop Owner is the company-wide operational authority. Platform Admin retains platform and initial-setup controls, but is not implicitly authorized for day-to-day business operations. Manager access to business records is constrained to the assigned Branch. Employee Sellers have a more limited operational scope. Platform Admin pages and Shop Owner pages are separate authorization areas; hiding a navigation item is not the authorization control.

For a new installation, `php artisan admin:create` provisions a **Platform Admin**, not a Shop Owner. Initial Owner setup is available through the business access area when no active Owner exists. It requires a verified, otherwise unassigned Customer account; the initial setup flow changes that account to `staff` and assigns the company-wide Owner membership.

## Technology stack

Versions below are based on the project manifests and lockfiles:

| Area | Technology |
| --- | --- |
| Runtime | PHP `^8.3` |
| Application framework | Laravel `^13.17` |
| Authentication | Laravel Fortify `^1.37.2`, with application email OTP flows and Fortify TOTP support |
| Server-rendered UI | Blade, Livewire `^4.1`, and Flux `^2.13.1` |
| CSS | Tailwind CSS `^4.0.7` |
| Frontend build | Vite `^8.0.0`, Laravel Vite Plugin `^3.1`, and Vite Plus `0.3.0` |
| JavaScript | ES modules; Laravel Passkeys client package is present |
| Database connections | SQLite, MySQL, MariaDB, PostgreSQL, and SQL Server are configured |
| Tests and quality tools | PHPUnit `^12.5.23`, Laravel test runner, Pint, and Larastan/PHPStan |

The locked Vite Plus package requires Node.js `^20.19.0`, `^22.18.0`, or `>=24.11.0`; the locked Vite version also requires Node.js `^20.19.0` or `>=22.12.0`. Use a Node.js version satisfying both ranges. The repository does not specify an npm version range.

Database drivers are configured in the application, but that does not mean every migration or concurrency behavior has been validated on every engine. Validate migrations and locking behavior against the actual deployment database before release.

## Application structure

The main application layers are:

| Path | Responsibility |
| --- | --- |
| `routes/` | Public, authentication, seller, admin, and settings routes. |
| `app/Http/Controllers/` | HTTP request handling and response selection. |
| `app/Http/Requests/` | Request validation and authorization for complex inputs. |
| `app/Models/` | Eloquent models and relationships for users, listings, orders, branches, memberships, invitations, and Careers. |
| `app/Services/` | Business access, memberships, invitations, OTP, recruitment, settings, order inventory, and device-session logic. |
| `app/Policies/` | Record and action authorization. |
| `app/Http/Middleware/` | Role-aware route gates and session/access checks. |
| `database/migrations/` | User, marketplace, commerce, business-access, invitation, and Careers schema. |
| `resources/views/` | Blade pages and layouts for public, customer, seller, admin, email, and Careers workflows. |
| `resources/js/`, `resources/css/` | Frontend entry points and Tailwind styling. |
| `tests/` | PHPUnit unit and feature tests. |

## Local development

### Source availability

The GitHub repository currently contains only this README, so the clone command below does not yet provide the Laravel application files. Use a source checkout containing `artisan`, `composer.json`, and `package.json` to run the application. Once those files are published to GitHub, a fresh clone can be used for the following steps.

### Prerequisites

- PHP `^8.3` with the extensions required by Composer dependencies, including a PDO driver for the selected database.
- Composer.
- Node.js satisfying the Vite Plus and Vite ranges above, with npm.
- SQLite for a local default setup, or a separately provisioned database supported by the application configuration.

### Setup

```bash
git clone <repository-url>
cd PawHaven
```

The commands below must be run from the Laravel source directory (the directory containing `artisan`).

Install backend and frontend dependencies:

```bash
composer install
npm ci
```

Create the environment file and application key.

**Windows PowerShell:**

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

**Linux or macOS:**

```bash
cp .env.example .env
php artisan key:generate
```

Configure `.env` for the intended local database. SQLite is the default. Create the SQLite file only if it does not already exist.

**Windows PowerShell:**

```powershell
if (-not (Test-Path database/database.sqlite)) {
    New-Item -ItemType File -Path database/database.sqlite | Out-Null
}
```

**Linux or macOS:**

```bash
touch database/database.sqlite
```

For MySQL or another configured server, create an empty database and database user separately, then set the matching `DB_*` values in `.env`. Do not use a destructive reset command against a database containing data.

Run the migrations for the configured development database:

```bash
php artisan migrate
```

If the application serves uploaded marketplace listing images from the public disk, create the Laravel storage link:

```bash
php artisan storage:link
```

Create the first Platform Admin interactively. The command prompts for account details and a password; do not put real passwords in shell history or commit them.

```bash
php artisan admin:create
```

Then create and verify the intended Shop Owner's Customer account and assign the initial Owner membership under **Admin → Business**. The setup flow is available only while there is no active Shop Owner. Create Branches after Owner setup. Configure a working mail transport before exercising login OTP, invitations, or Careers offer email flows.

Build frontend assets or start the development watcher:

```bash
npm run build
# or
npm run dev
```

Start Laravel in another terminal:

```bash
php artisan serve
```

### Queues and scheduled tasks

The example environment and queue configuration use the `database` queue connection. The queue tables are included in the standard migrations. When the application has queued work to process, run a worker in a separate terminal:

```bash
php artisan queue:work
```

The application currently registers one scheduled task: `device-sessions:prune`, which runs daily to remove device-session records past their retention period. For local development, keep Laravel's scheduler running in a separate terminal:

```bash
php artisan schedule:work
```

In production, configure the host scheduler to invoke `php artisan schedule:run` once per minute from the Laravel project directory. The scheduler only runs tasks that are due; it does not replace a queue worker for database-backed queued jobs.

## Environment configuration

The application reads configuration through Laravel's `.env` file. Set values locally and keep `.env` out of version control.

| Category | Relevant variables |
| --- | --- |
| Application | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, locale variables |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |
| Filesystem | `FILESYSTEM_DISK`; `AWS_*` settings when using the configured S3 disk |
| Session, cache, queue | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `BROADCAST_CONNECTION` |
| Device sessions | `DEVICE_SESSION_COOKIE`, `DEVICE_SESSION_IDLE_DAYS`, `DEVICE_SESSION_RETENTION_DAYS`, `DEVICE_SESSION_ROTATION_GRACE_SECONDS` |

The `.env.example` file is the source of the available environment-variable names and defaults. Replace placeholders with environment-appropriate settings; never publish credentials, application keys, or mail passwords.

## Database and migrations

The schema includes users and authentication data, pet categories and listings, listing images and moderation history, products and product categories, orders and order items, settings, device sessions, Companies and Branches, business memberships, invitations, audit logs, and Careers postings, applications, offers, and offer OTP challenges.

A fresh business-access migration establishes the installation Company. The business model then uses one Company per installation, with multiple Branches. Employee memberships use the `owner`, `manager`, or `seller` position and require the corresponding company-wide or branch scope. Database constraints protect Company/Branch relationships and active membership integrity where supported by the migration implementation.

Run migrations with `php artisan migrate` only after configuring the intended database. Existing installations should be reviewed with the read-only preflight command before applying business-integrity migrations:

```bash
php artisan pawhaven:business-preflight
```

The preflight reports data conditions that need review; it does not repair records. Ambiguous legacy employee assignments may need explicit manual resolution. SQLite is used for the project's isolated test configuration. Other configured database drivers require their own migration and concurrency validation before deployment.

## Security

- Laravel authentication, password hashing, verified-email middleware, request validation, CSRF protection on web forms, and route throttling are used across the application.
- Platform Admin, Shop Owner, employee, Marketplace Seller, and Customer access are separated in server-side middleware, services, and policies.
- Company and Branch assignments are validated server-side. Business queries and actions use membership-based company/branch authorization.
- Invitation links and employment offers use expiring, single-use registration credentials; resending or revoking invalidates prior links.
- Careers uses a separate offer-bound email OTP challenge. The employee User is created only after successful verification; the membership remains inactive until Shop Owner approval.
- Careers CVs are stored on the private local disk and served through an authorized administrative download route. Marketplace listing images use the public disk and require the storage link when served locally.
- Significant administrative, business, invitation, and recruitment actions are recorded through the audit-log infrastructure.
- Email OTP and Fortify TOTP continuation are implemented for applicable authentication flows. Passkey login routes are currently disabled while they are incompatible with the required OTP/TOTP policy.

These are application controls, not a security certification. Review deployment configuration, database permissions, mail transport, backups, and operational procedures for the environment where PawHaven will run.

## Testing

The PHPUnit configuration uses an in-memory SQLite database and array-backed mail, cache, and session services. Tests should run with the project test environment; they do not require the configured application database when the repository test configuration is respected.

Run the feature and unit suites:

```bash
php artisan test
```

The Composer test script also runs formatting and static-analysis checks before Laravel tests:

```bash
composer test
```

To build frontend assets:

```bash
npm run build
```

SQLite tests do not establish row-lock or concurrent-request behavior for MySQL, MariaDB, PostgreSQL, or SQL Server. Test those behaviors against an isolated instance of the intended deployment engine.

## Current limitations and roadmap

- **Cart and full checkout:** the product flow creates a buy request and reserves stock; a customer-facing cart and complete checkout experience are not implemented.
- **POS and offline sales:** no point-of-sale workflow is implemented.
- **Sales attribution:** orders do not yet record the employee Seller who handled a sale or a complete Company/Branch/channel attribution model for online and offline sales.
- **Sales reporting and correction:** employee-specific sales reports and audited sales-correction workflows are deferred.
- **Careers scope:** the application implements vacancies, applications, review, offers, offer OTP, employee creation, and Owner approval. It is not a full HRM system.
- **HR features:** payroll, attendance, leave management, and performance management are not implemented.
- **Database validation:** migration and locking behavior still needs validation on each intended production database engine; SQLite-only results are not sufficient for production sign-off.
- **Passkeys:** passkey login endpoints are disabled until they can preserve the mandatory authentication challenge policy.

## Contributing

Contributions are welcome through a simple reviewable workflow:

1. Fork the repository.
2. Create a focused branch for your change.
3. Make the change and add or update relevant tests.
4. Run the applicable test and quality checks.
5. Open a pull request describing the change and its verification.

No separate formal contribution policy is currently published. Avoid including `.env` files, credentials, personal application data, or private uploads in a contribution.

## License

The local application's `composer.json` declares `MIT`, but this source checkout has no `LICENSE` text file, and the current GitHub repository does not publish the application manifest. Review the intended license and publish its license text before relying on an open-source grant. Until a license is clearly published, do not assume reuse permissions.

## Maintainer
- Source repository URL is supplied with the buyer distribution.
