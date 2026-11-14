# PawHaven — Production Release Checklist

Before launching PawHaven into a live production environment or presenting to marketplace reviewers, verify every item on this release readiness checklist.

---

## 📋 Pre-Flight Checklist

### 1. Environment & Configuration
- [ ] **`APP_ENV` is set to `production`** in `.env`.
- [ ] **`APP_DEBUG` is set to `false`**.
- [ ] **`APP_KEY` is generated and valid** (`php artisan key:generate`).
- [ ] **`APP_URL` matches the canonical HTTPS domain** (e.g. `https://pawhaven.com`).
- [ ] **Database credentials verified**: SQLite file exists or MySQL database is reachable.
- [ ] **`.env` is not committed or publicly accessible** (verified via web browser: accessing `https://your-domain.com/.env` returns `403` or `404`).

### 2. Assets & Storage
- [ ] **Production assets compiled**: `npm run build` completed with zero errors (`public/build` manifest generated).
- [ ] **Storage symlink active**: `php artisan storage:link` links `public/storage` to `storage/app/public`.
- [ ] **Directory write permissions set**: `storage/` and `bootstrap/cache/` are writable by the web server user (`chmod 775`).
- [ ] **Branding logos placed**: Light and dark logos confirmed visible on header, footer, and login screens.

### 3. Database & System Migrations
- [ ] **Migrations up to date**: `php artisan migrate --status` shows all migrations `[Ran]`.
- [ ] **No destructive migrations**: Schema additions are strictly additive.
- [ ] **Initial Administrator created**: Account provisioned via `php artisan admin:create` and verified at `/admin/login`.

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
