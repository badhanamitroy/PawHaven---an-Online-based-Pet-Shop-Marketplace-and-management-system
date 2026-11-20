# PawHaven — Self-Hosted Installation Guide

This guide covers setup on Linux servers (Ubuntu/Debian) and Windows development environments. Hosting providers and production database combinations still require validation in the buyer's target environment.

---

## 1. System Requirements

| Requirement | Minimum | Recommended | Notes |
| :--- | :--- | :--- | :--- |
| **Operating System** | Linux (Ubuntu 22.04+ / Debian 12+) or Windows 10/11 | Linux Ubuntu 24.04 LTS | Compatible with shared VPS or dedicated hardware |
| **PHP Version** | `8.3.0` | `8.5` | CLI & FPM; Composer requires `^8.3` |
| **Required PHP Extensions** | `pdo`, `pdo_sqlite` (or `pdo_mysql`), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl` | Same + `opcache` | Ensure `fileinfo` is enabled for image uploads |
| **Web Server** | Nginx `1.20+` or Apache `2.4+` | Nginx with PHP-FPM | Document root pointing to `/public` |
| **Database** | SQLite `3.35+` or MySQL `8.0+` | SQLite for local/single-server use; MySQL is configured but needs deployment-specific validation | SQLite is the default; this guide does not establish production concurrency guarantees |
| **Node.js & npm** | Node `20.19+` or `22.12+` | Current supported Node LTS | Required only for asset compilation; Vite 8 does not support Node 18 |
| **Composer** | `2.2.0` | `2.7+` | PHP dependency manager |

---

## 2. Linux Production Setup (Ubuntu 22.04 / 24.04)

### Step 1: Install System Dependencies
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx git curl unzip sqlite3 \
    php8.3 php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
```

### Step 2: Install Composer & Node.js
```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js (via NodeSource LTS)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### Step 3: Deploy Project Files
```bash
# Clone or copy application into standard web directory
sudo mkdir -p /var/www/pawhaven
sudo chown -R $USER:$USER /var/www/pawhaven

# Unpack or copy your PawHaven source files into /var/www/pawhaven
cd /var/www/pawhaven
```

### Step 4: Install Dependencies & Build Frontend
```bash
# Install optimized PHP dependencies
composer install --no-dev --optimize-autoloader

# Install JavaScript modules and compile production CSS/JS
npm ci
npm run build
```

### Step 5: Environment & Encryption Key
```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` configuration:
```env
APP_NAME=PawHaven
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=sqlite
# For MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=pawhaven
# DB_USERNAME=pawhaven_user
# DB_PASSWORD=your_secure_password

# Configure these using credentials from your SMTP provider:
MAIL_MAILER=smtp
MAIL_HOST=smtp.provider.example
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

Keep real mail credentials in the deployment's protected environment or secret manager; never commit them. After setup, test account email verification and the Administrator email one-time code before opening the service to buyers.

### Step 6: Database Initialization & Storage Symlink
```bash
# If using SQLite:
touch database/database.sqlite

# Execute database migrations
php artisan migrate --force

# Create public storage symlink for uploaded pet photos and branding logos
php artisan storage:link

# Confirm the installed schema is current
php artisan migrate:status
```

Do not run demo seeders against a live installation. The migration chain creates the installation's initial company record; it does not create or assign a Shop Owner.

### Step 7: File Permissions
```bash
sudo chown -R deploy:www-data /var/www/pawhaven/storage /var/www/pawhaven/bootstrap/cache
sudo find /var/www/pawhaven/storage /var/www/pawhaven/bootstrap/cache -type d -exec chmod 2770 {} \;
sudo find /var/www/pawhaven/storage /var/www/pawhaven/bootstrap/cache -type f -exec chmod 660 {} \;

# SQLite only: keep database access limited to the trusted application group.
sudo chown deploy:www-data /var/www/pawhaven/database /var/www/pawhaven/database/database.sqlite
sudo chmod 2770 /var/www/pawhaven/database
sudo chmod 660 /var/www/pawhaven/database/database.sqlite
```

### Step 8: Bootstrap the Initial Administrator Account
PawHaven includes a dedicated CLI command to securely bootstrap the initial Administrator account. Do not use database seeders to create administrator or customer accounts:
```bash
php artisan admin:create
```
You will be prompted for:
- Full Name (e.g., `Admin Owner`)
- Email Address (e.g., `admin@your-domain.com`)
- Secure Password (min. 8 characters)

The command prompts for the password without echoing it. Passwords are deliberately not accepted as command-line options, where they can be retained in shell history or process listings.

## 3. Initial Shop Owner and Company Setup

The platform Administrator and Shop Owner are separate roles. Sign in to the verified platform Administrator account, open **Admin → Business Management**, and assign the **Owner** role to the verified customer account that will operate the business. The initial setup action is available only to the platform Administrator and is disabled after an active Owner assignment exists. It does not automatically grant the Administrator access to private company records.

Use an account that belongs to the business owner and has no existing marketplace activity that would make it ineligible for initial ownership. Do not share an Administrator login as the Shop Owner account. Configure and test mail delivery before relying on email verification or administrator one-time codes.

---

## 4. Nginx Web Server Configuration

Create an Nginx server block: `/etc/nginx/sites-available/pawhaven`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /var/www/pawhaven/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    index index.php;

    charset utf-8;

    # Maximum file upload size (matches 5 x 2MB pet photos)
    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site and obtain SSL via Let's Encrypt:
```bash
sudo ln -s /etc/nginx/sites-available/pawhaven /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Optional: Free automated SSL certificate
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

---

## 5. Windows Development & Local Hosting

1. Open PowerShell in the directory where you installed PawHaven.
2. Run Composer and npm installs:
   ```powershell
   composer install
   npm install
   npm run build
   ```
3. Prepare environment and storage:
   ```powershell
   copy .env.example .env
   php artisan key:generate
   php artisan storage:link
   ```
4. Run migrations:
   ```powershell
   php artisan migrate
   ```
5. Bootstrap initial Admin:
   ```powershell
   php artisan admin:create
   ```
6. Follow **Initial Shop Owner and Company Setup** after signing in as the verified Platform Administrator. Configure mail delivery first.
7. Start development server:
   ```powershell
   php artisan serve --port=8000
   ```
   Open `http://localhost:8000/` in your browser.

## 6. Safe upgrades and schema troubleshooting

Before upgrading an existing installation, put the application into maintenance mode if appropriate, stop background writers, and create and verify a database backup using [the backup guide](BACKUP_AND_RESTORE.md). Then deploy the release and run:

```bash
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
```

Do not use `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or `migrate:rollback` as an upgrade procedure. If a page reports a missing table or column, compare the effective database connection with the expected installation and inspect migration status before changing configuration or data. Never suppress the schema error or run migrations before a verified backup.

**Currency upgrade gate:** the currency-snapshot migration adds `USD` defaults to existing products, listings, and orders. If upgrading a database that already contains monetary records, and its configured shop currency is not USD, stop before applying that migration and request a reviewed reconciliation plan. The migration cannot infer historical currency from amounts alone; do not guess or rewrite those records during installation.
