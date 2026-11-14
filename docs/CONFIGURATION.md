# PawHaven — Configuration Reference

This guide details all configuration options available in PawHaven for administrators and developers.

---

## 1. Environment Configuration (`.env`)

### Application Identity & URL
```env
APP_NAME=PawHaven
APP_ENV=production          # local | staging | production
APP_KEY=base64:...          # Generated with: php artisan key:generate
APP_DEBUG=false             # Always false in production
APP_URL=https://your-domain.com
```

### Database Connections

#### A. SQLite (Default & Recommended for single-instance hosting)
```env
DB_CONNECTION=sqlite
# Database file resides at database/database.sqlite
```

#### B. MySQL 8.0+
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pawhaven
DB_USERNAME=pawhaven_user
DB_PASSWORD=your_secure_password
```

### Mail & Notifications
PawHaven uses standard Laravel mail drivers for email verification, order notifications, and password resets:

#### SMTP / Custom Mail Server
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@your-domain.com
MAIL_PASSWORD=your-smtp-secret-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="support@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

#### Local Development / Log Driver
```env
MAIL_MAILER=log
# Writes outgoing emails directly to storage/logs/laravel.log
```

### Session & Cache
```env
SESSION_DRIVER=database
SESSION_LIFETIME=120        # Session expires after 120 minutes of inactivity
SESSION_ENCRYPT=false
SESSION_SECURE_COOKIE=true  # Set true when using HTTPS

CACHE_STORE=database
QUEUE_CONNECTION=database   # Asynchronous background jobs
```

---

## 2. Dynamic Settings (Admin Portal)

PawHaven stores operational and website settings in the database via the `Settings` service, enabling administrators to configure the site without editing code or restarting services.

### Brand Identity (`/admin/brand`)
Configurable from the Admin Website Manager:
- **Brand Name**: Displayed in navigation, emails, and page titles (Default: `PawHaven`).
- **Tagline**: Displayed in hero banners and meta headers.
- **Brand Logo (Light)**: High-resolution PNG/SVG for light backgrounds (`public/images/pawhaven-logo-green.png`).
- **Brand Logo (Dark)**: High-resolution PNG/SVG for dark footer and splash panels (`public/images/pawhaven-logo-white.png`).
- **Favicon**: Browser tab icon (`public/favicon.ico`).
- **Meta Description**: Search engine summary.

### Homepage & Website Manager (`/admin/homepage`)
Administrators can customize public content safely using the **Staged Draft & Publish** workflow:
- **Hero Headline**: Main homepage heading (Default: *"Your next best friend is waiting."*).
- **Hero Supporting Text**: Subtitle copy.
- **Announcement Bar**: Top banner toggle and text.
- **Primary & Secondary CTAs**: Custom button text and anchor targets.
- **Section Visibility Toggles**: Enable/disable Marketplace, Pet Supplies Shop, Adoption Highlights, Breed Directory, and Trust Community sections.
- **Draft Isolation**: Changes remain in draft state with an administrator-only visual preview banner (`/admin/homepage/preview`) until clicked **"Publish Live"**.

### Commercial Store Settings (`/admin/settings/business`)
- **Store Name & Contact**: Official customer service phone and email.
- **Currency Code**: Supported currency (e.g. `USD`, `EUR`, `GBP`).
- **Fixed Delivery Fee**: Major units (e.g. `$5.00` &rarr; stored as minor integer units `500`).
- **Cash on Delivery (COD) Enabled**: Toggle cash handover payment settlement.
