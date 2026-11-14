# PawHaven — Self-Hosted Installation Guide

This guide covers complete, step-by-step setup instructions for installing PawHaven on fresh Linux servers (Ubuntu/Debian) or Windows local environments.

---

## 1. System Requirements

| Requirement | Minimum | Recommended | Notes |
| :--- | :--- | :--- | :--- |
| **Operating System** | Linux (Ubuntu 22.04+ / Debian 12+) or Windows 10/11 | Linux Ubuntu 24.04 LTS | Compatible with shared VPS or dedicated hardware |
| **PHP Version** | `8.3.0` | `8.5` | CLI & FPM; Composer requires `^8.3` |
| **Required PHP Extensions** | `pdo`, `pdo_sqlite` (or `pdo_mysql`), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl` | Same + `opcache` | Ensure `fileinfo` is enabled for image uploads |
| **Web Server** | Nginx `1.20+` or Apache `2.4+` | Nginx with PHP-FPM | Document root pointing to `/public` |
| **Database** | SQLite `3.35+` or MySQL `8.0+` | SQLite (low/medium traffic) or MySQL 8.0 | Zero-configuration SQLite is default |
| **Node.js & npm** | Node `18.0.0` / npm `9.0.0` | Node `20.x LTS` | Required only for asset compilation |
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
```

### Step 6: Database Initialization & Storage Symlink
```bash
# If using SQLite:
touch database/database.sqlite

# Execute database migrations
php artisan migrate --force

# Seed local demo catalog data (Optional; skipped in production)
php artisan db:seed --class=ShopSeeder

# Create public storage symlink for uploaded pet photos and branding logos
php artisan storage:link
```

### Step 7: File Permissions
```bash
sudo chown -R www-data:www-data /var/www/pawhaven/storage /var/www/pawhaven/bootstrap/cache /var/www/pawhaven/database
sudo chmod -R 775 /var/www/pawhaven/storage /var/www/pawhaven/bootstrap/cache /var/www/pawhaven/database
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

Alternatively, run non-interactively:
```bash
php artisan admin:create --name="Site Admin" --email="admin@your-domain.com" --password="YourSecurePassword123!"
```

---

## 3. Nginx Web Server Configuration

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

## 4. Windows Development & Local Hosting

1. Open PowerShell in `D:\PawHaven\PH Web Version\WebApp`.
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
6. Start development server:
   ```powershell
   php artisan serve --port=8000
   ```
   Open `http://localhost:8000/` in your browser.
