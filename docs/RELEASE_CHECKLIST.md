# PawHaven — Production Release Checklist

Before launching PawHaven into a live production environment or presenting to marketplace reviewers, verify every item on this release readiness checklist.

---

## 📋 Pre-Flight Checklist

### 1. Environment & Configuration
- [ ] **`APP_ENV` is set to `production`** in `.env`.
- [ ] **`APP_DEBUG` is set to `false`**.
- [ ] **`APP_KEY` is generated and valid** (`php artisan key:generate`).
- [ ] **`APP_URL` matches the canonical HTTPS domain** (e.g. `https://pawhaven.com`).
- [ ] **Database configuration verified**: the effective connection resolves to the intended SQLite file or a reachable MySQL database; do not print credentials in logs.
- [ ] **`.env` is not committed or publicly accessible** (verified via web browser: accessing `https://your-domain.com/.env` returns `403` or `404`).

### 2. Assets & Storage
- [ ] **Production assets compiled**: `npm run build` completed with zero errors (`public/build` manifest generated).
- [ ] **Storage symlink active**: `php artisan storage:link` links `public/storage` to `storage/app/public`.
- [ ] **Least-privilege permissions set**: only required storage/cache paths are writable by the application account; keep SQLite files private and avoid blanket recursive permission changes.
- [ ] **Branding logos placed**: Light and dark logos confirmed visible on header, footer, and login screens.

### 3. Database & System Migrations
- [ ] **Migration history reviewed**: `php artisan migrate:status` shows the expected migration sequence and no unexpected pending or unknown migrations.
- [ ] **Upgrade backup verified**: create and independently verify a database backup before applying migrations to an existing installation.
- [ ] **Migration behavior reviewed**: inspect backfills, constraints, data conversions, and rollback behavior; the migration chain is not assumed to be strictly additive.
- [ ] **Initial Administrator created**: run `php artisan admin:create` interactively and verify email one-time-code delivery.
- [ ] **Initial Shop Owner assigned**: use Business Management to assign a verified eligible business-owner account; the migration does not create an Owner membership automatically.

### 4. Communications & Email
- [ ] **Mail transport configured**: `MAIL_MAILER` set to a functional SMTP/API provider (e.g. Mailgun, Postmark, SES).
- [ ] **Email test performed**: Password reset or user verification email successfully delivered to an external inbox.

### 5. Automated Testing & Verification
- [ ] **Full test suite passes**: `php artisan test` reports 100% green pass rate across all unit and feature tests.
- [ ] **Static analysis clean**: `php vendor/bin/phpstan analyse --memory-limit=1G` passes with 0 errors.
- [ ] **Code formatting clean**: `php vendor/bin/pint --test` passes with 0 style violations.

### 6. Role & Security Verification
- [ ] **Admin panel isolated**: Guests and customers are denied access to `/admin` (`403` / redirect).
- [ ] **Seller portal isolated**: Sellers can only view and mutate their own listings and requests (`IDOR` protected).
- [ ] **Customer privacy isolated**: Customers cannot view or cancel other customer orders.
- [ ] **Draft content hidden**: Unpublished homepage drafts are never exposed to public visitors.

### 7. Buyer Package Hygiene
- [ ] **Generated guides current**: rebuild and review buyer PDFs from the current guide source before distribution; do not ship old generated copies without review.
- [ ] **No developer data included**: exclude local QA screenshots, real account identifiers, personal workstation paths, and credentials from the buyer package.
- [ ] **Test automation configured locally**: browser QA scripts use local environment variables for credentials and browser paths; do not commit those values.

---

## 🛠️ Post-Launch Verification Commands

```bash
# 1. Clear and warm configuration and route caches for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 2. Verify system health
php artisan test --filter="SystemHealthTest"
```
