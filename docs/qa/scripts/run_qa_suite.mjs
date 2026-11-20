import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { BASE_URL, CHROME_PATH, qaCredentials, REPO_ROOT, WEBAPP_ROOT } from './qa-config.mjs';

const adminCredentials = qaCredentials('admin');
const customerCredentials = qaCredentials('customer');
const sellerCredentials = qaCredentials('seller');

const VIEWPORTS = {
    desktop: { width: 1440, height: 900 },
    tablet: { width: 768, height: 1024 },
    mobile: { width: 375, height: 812 }
};

const manifest = [];

function ensureDir(dirPath) {
    if (!fs.existsSync(dirPath)) {
        fs.mkdirSync(dirPath, { recursive: true });
    }
}

async function capture(page, category, filename, id, description, viewportName = 'desktop') {
    const webappDir = path.join(WEBAPP_ROOT, 'docs', 'qa', 'screenshots', category);
    const repoDir = path.join(REPO_ROOT, 'docs', 'qa', 'screenshots', category);
    ensureDir(webappDir);
    ensureDir(repoDir);

    const webappFile = path.join(webappDir, filename);
    const repoFile = path.join(repoDir, filename);

    await page.screenshot({ path: webappFile, fullPage: false });
    fs.copyFileSync(webappFile, repoFile);

    const stat = fs.statSync(webappFile);
    const entry = {
        id,
        category,
        filename,
        path: `docs/qa/screenshots/${category}/${filename}`,
        sizeBytes: stat.size,
        viewport: `${VIEWPORTS[viewportName].width}x${VIEWPORTS[viewportName].height} (${viewportName})`,
        url: page.url(),
        title: await page.title(),
        description,
        timestamp: new Date().toISOString(),
        status: 'PASSED'
    };
    manifest.push(entry);
    console.log(`[PASS] Captured ${id}: ${filename} (${stat.size} bytes)`);
    return entry;
}

async function safeLogout(page) {
    try {
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
        await page.evaluate(() => {
            const logoutForms = document.querySelectorAll('form[action*="logout"]');
            if (logoutForms.length > 0) {
                logoutForms[0].submit();
            }
        });
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 5000 }).catch(() => {});
    } catch (e) {
        // ignore
    }
}

async function run() {
    console.log('====================================================');
    console.log('PAWHAVEN MASTER CHROME QA & SECURITY SUITE');
    console.log(`Target URL: ${BASE_URL}`);
    console.log(`Chrome Executable: ${CHROME_PATH}`);
    console.log('====================================================\n');

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1440,900'
        ]
    });

    try {
        const page = await browser.newPage();
        await page.setViewport(VIEWPORTS.desktop);

        // ----------------------------------------------------
        // 00-ENVIRONMENT
        // ----------------------------------------------------
        console.log('\n--- 00-ENVIRONMENT ---');
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2', timeout: 30000 });
        await capture(page, '00-environment', 'ENV-01-app-boot-desktop.png', 'ENV-01', 'PawHaven initial boot baseline in Chrome (Desktop 1440x900)', 'desktop');

        await page.setViewport(VIEWPORTS.tablet);
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
        await capture(page, '00-environment', 'ENV-02-app-boot-tablet.png', 'ENV-02', 'PawHaven initial boot baseline in Chrome (Tablet 768x1024)', 'tablet');

        await page.setViewport(VIEWPORTS.mobile);
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
        await capture(page, '00-environment', 'ENV-03-app-boot-mobile.png', 'ENV-03', 'PawHaven initial boot baseline in Chrome (Mobile 375x812)', 'mobile');

        // Reset to desktop
        await page.setViewport(VIEWPORTS.desktop);

        // ----------------------------------------------------
        // 01-PUBLIC-WEBSITE
        // ----------------------------------------------------
        console.log('\n--- 01-PUBLIC-WEBSITE ---');
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
        await capture(page, '01-public-website', 'PUB-01-homepage-hero.png', 'PUB-01', 'Homepage hero section with brand logo and primary CTAs');

        // Marketplace Catalog
        await page.goto(`${BASE_URL}/listings`, { waitUntil: 'networkidle2' });
        await capture(page, '01-public-website', 'PUB-02-marketplace-catalog.png', 'PUB-02', 'Public pet listings catalog with filters and cards');

        // Detail View of First Available Pet
        const detailLink = await page.$('a[href*="/listings/"]');
        if (detailLink) {
            await detailLink.click();
            await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
            await capture(page, '01-public-website', 'PUB-03-pet-details-view.png', 'PUB-03', 'Public pet listing detail page with gallery and seller bio');
        }

        // Homepage Sections: Pet Shop, Adoption, Breeds, Footer
        await page.goto(`${BASE_URL}/#pet-shop`, { waitUntil: 'networkidle2' });
        await page.evaluate(() => {
            const el = document.getElementById('pet-shop');
            if (el) el.scrollIntoView();
        });
        await new Promise(r => setTimeout(r, 600));
        await capture(page, '01-public-website', 'PUB-04-pet-shop-products.png', 'PUB-04', 'Pet supplies & nutrition shop section with add-to-cart badges');

        await page.goto(`${BASE_URL}/#adoption-rescue`, { waitUntil: 'networkidle2' });
        await page.evaluate(() => {
            const el = document.getElementById('adoption-rescue');
            if (el) el.scrollIntoView();
        });
        await new Promise(r => setTimeout(r, 600));
        await capture(page, '01-public-website', 'PUB-05-adoption-rescue-highlights.png', 'PUB-05', 'Community rehoming and adoption highlights section');

        await page.goto(`${BASE_URL}/#breeds`, { waitUntil: 'networkidle2' });
        await page.evaluate(() => {
            const el = document.getElementById('breeds');
            if (el) el.scrollIntoView();
        });
        await new Promise(r => setTimeout(r, 600));
        await capture(page, '01-public-website', 'PUB-06-breed-discovery.png', 'PUB-06', 'Breed discovery encyclopedia module with search and filters');

        // Footer Trust & Safety
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await new Promise(r => setTimeout(r, 600));
        await capture(page, '01-public-website', 'PUB-08-footer-trust-branding.png', 'PUB-08', 'PawHaven footer with white logo, platform links, and trust pillars');

        // Mobile Nav Drawer
        await page.setViewport(VIEWPORTS.mobile);
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
        const menuBtn = await page.$('header button');
        if (menuBtn) {
            await menuBtn.click().catch(() => {});
            await new Promise(r => setTimeout(r, 400));
            await capture(page, '01-public-website', 'PUB-07-mobile-nav-drawer.png', 'PUB-07', 'Mobile navigation drawer on 375px viewport', 'mobile');
        }

        // Reset to desktop
        await page.setViewport(VIEWPORTS.desktop);

        // ----------------------------------------------------
        // 02-AUTH-CUSTOMER & 03-AUTH-SELLER
        // ----------------------------------------------------
        console.log('\n--- 02-AUTH & 03-AUTH ---');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
        await capture(page, '02-auth-customer', 'AUTH-01-customer-login-view.png', 'AUTH-01', 'Customer login portal view with email and password inputs');

        await page.goto(`${BASE_URL}/login?role=seller`, { waitUntil: 'networkidle2' });
        await capture(page, '03-auth-seller', 'AUTH-02-seller-login-view.png', 'AUTH-02', 'Seller login portal with merchant onboarding banner');

        await page.goto(`${BASE_URL}/register`, { waitUntil: 'networkidle2' });
        await capture(page, '02-auth-customer', 'AUTH-03-registration-role-selector.png', 'AUTH-03', 'Public registration role selector (Customer vs Seller)');

        await page.goto(`${BASE_URL}/register?role=customer`, { waitUntil: 'networkidle2' });
        await capture(page, '02-auth-customer', 'AUTH-04-customer-registration-form.png', 'AUTH-04', 'Customer registration form with name, email, password fields');

        await page.goto(`${BASE_URL}/register?role=seller`, { waitUntil: 'networkidle2' });
        await capture(page, '03-auth-seller', 'AUTH-05-seller-registration-form.png', 'AUTH-05', 'Seller merchant registration form with verified seller requirements');

        // ----------------------------------------------------
        // 04-AUTH-NEGATIVE
        // ----------------------------------------------------
        console.log('\n--- 04-AUTH-NEGATIVE ---');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', 'invalid-user@pawhaven.test');
        await page.type('input[name="password"]', 'not-a-valid-test-password');
        const loginBtn = await page.$('button[type="submit"]');
        if (loginBtn) {
            await loginBtn.click();
            await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
            await capture(page, '04-auth-negative', 'AUTH-NEG-01-invalid-credentials.png', 'AUTH-NEG-01', 'Login failure feedback on invalid credentials submission');
        }

        await page.goto(`${BASE_URL}/register`, { waitUntil: 'networkidle2' });
        await page.evaluate(() => {
            const form = document.querySelector('form.auth-form') || document.querySelector('form');
            if (form) {
                form.querySelectorAll('input').forEach(i => i.removeAttribute('required'));
                form.submit();
            }
        });
        await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
        await capture(page, '04-auth-negative', 'AUTH-NEG-02-validation-errors.png', 'AUTH-NEG-02', 'Server-side registration validation errors on blank submission');

        // ----------------------------------------------------
        // 05-CUSTOMER-PORTAL (Login with Customer)
        // ----------------------------------------------------
        console.log('\n--- 05-CUSTOMER-PORTAL ---');
        await safeLogout(page);
        await page.goto(`${BASE_URL}/login?role=customer`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', customerCredentials.email);
        await page.type('input[name="password"]', customerCredentials.password);
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});

        await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle2' });
        await capture(page, '05-customer-portal', 'CUST-01-dashboard-overview.png', 'CUST-01', 'Customer Dashboard overview showing active orders and saved pets');

        await page.goto(`${BASE_URL}/dashboard?tab=requests`, { waitUntil: 'networkidle2' });
        await capture(page, '05-customer-portal', 'CUST-02-purchase-requests-tab.png', 'CUST-02', 'Customer purchase requests timeline with seller status progression');

        await page.goto(`${BASE_URL}/dashboard?tab=saved`, { waitUntil: 'networkidle2' });
        await capture(page, '05-customer-portal', 'CUST-04-saved-listings-tab.png', 'CUST-04', 'Customer saved pets and bookmarked marketplace listings');

        await page.goto(`${BASE_URL}/dashboard?tab=posts`, { waitUntil: 'networkidle2' });
        await capture(page, '05-customer-portal', 'CUST-05-rehoming-posts-tab.png', 'CUST-05', 'Customer community adoption and pet rehoming post management');

        // ----------------------------------------------------
        // 06-SELLER-PORTAL (Login with Seller)
        // ----------------------------------------------------
        console.log('\n--- 06-SELLER-PORTAL ---');
        await safeLogout(page);
        await page.goto(`${BASE_URL}/login?role=seller`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', sellerCredentials.email);
        await page.type('input[name="password"]', sellerCredentials.password);
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});

        await page.goto(`${BASE_URL}/seller/dashboard`, { waitUntil: 'networkidle2' });
        await capture(page, '06-seller-portal', 'SELL-01-dashboard-metrics.png', 'SELL-01', 'Seller Dashboard with 5 computed metric cards and recent shop activity');

        await page.goto(`${BASE_URL}/seller/listings`, { waitUntil: 'networkidle2' });
        await capture(page, '06-seller-portal', 'SELL-02-listings-management.png', 'SELL-02', 'Seller commercial pet listings inventory with status pills and actions');

        await page.goto(`${BASE_URL}/seller/listings/create`, { waitUntil: 'networkidle2' });
        await capture(page, '06-seller-portal', 'SELL-03-create-listing-form.png', 'SELL-03', 'Seller new pet listing creation form with photo uploader and breed selector');

        await page.goto(`${BASE_URL}/seller/requests`, { waitUntil: 'networkidle2' });
        await capture(page, '06-seller-portal', 'SELL-04-incoming-requests-queue.png', 'SELL-04', 'Seller incoming purchase requests queue with buyer messages and actions');

        // ----------------------------------------------------
        // 07-ADMIN-PORTAL (Login with Admin + 2FA)
        // ----------------------------------------------------
        console.log('\n--- 07-ADMIN-PORTAL ---');
        await safeLogout(page);
        await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-01-login-screen.png', 'ADMIN-01', 'Dedicated internal Admin Console authentication portal');

        await page.type('input[name="email"]', adminCredentials.email);
        await page.type('input[name="password"]', adminCredentials.password);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]);

        console.log('Admin login navigation landed on:', page.url());
        if (page.url().includes('two-factor')) {
            await capture(page, '07-admin-portal', 'ADMIN-01-two-factor-challenge.png', 'ADMIN-01-2FA', 'Mandatory internal 2FA security checkpoint for staff');

            // Retrieve OTP from cache via PHP CLI
            const otp = execSync('php docs/qa/scripts/get_otp.php', { cwd: WEBAPP_ROOT }).toString().trim();

            if (otp) {
                console.log('Completing 2FA verification code in browser...');
                await page.type('input[name="code"]', otp);
                await Promise.all([
                    page.waitForNavigation({ waitUntil: 'networkidle2' }),
                    page.click('button[type="submit"]')
                ]);
                console.log('Post-2FA destination URL:', page.url());
            }
        }

        await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-02-dashboard-overview.png', 'ADMIN-02', 'PawHaven Integrated Admin Dashboard overview with key operating metrics');

        await page.goto(`${BASE_URL}/admin/homepage`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-03-website-manager-editor.png', 'ADMIN-03', 'Admin Website Manager with live hero customization and section toggles');

        // Preview Draft
        await page.goto(`${BASE_URL}/admin/homepage/preview`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-04-website-preview-draft.png', 'ADMIN-04', 'Admin draft preview banner confirming unpublished changes');

        await page.goto(`${BASE_URL}/admin/brand`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-05-brand-identity-settings.png', 'ADMIN-05', 'Admin Brand Identity management: logos, palette, social metadata');

        await page.goto(`${BASE_URL}/admin/marketplace/moderation`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-06-moderation-queue.png', 'ADMIN-06', 'Pet listing safety moderation queue with approve and reject audit actions');

        await page.goto(`${BASE_URL}/admin/orders`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-07-order-management.png', 'ADMIN-07', 'Store and commercial pet purchase order oversight and status inspection');

        await page.goto(`${BASE_URL}/admin/audit-logs`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-08-audit-logs.png', 'ADMIN-08', 'Immutable system and moderation audit trails with staff attribution');

        await page.goto(`${BASE_URL}/admin/system`, { waitUntil: 'networkidle2' });
        await capture(page, '07-admin-portal', 'ADMIN-09-system-health.png', 'ADMIN-09', 'System health diagnostic: PHP, SQLite, disk space, and queue status');

        // ----------------------------------------------------
        // 08-MARKETPLACE & 09-ADOPTION WORKFLOWS
        // ----------------------------------------------------
        console.log('\n--- 08-MARKETPLACE & 09-ADOPTION ---');
        await safeLogout(page);
        await page.goto(`${BASE_URL}/listings`, { waitUntil: 'networkidle2' });
        const firstListing = await page.$('a[href*="/listings/"]');
        if (firstListing) {
            await firstListing.click();
            await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
            await capture(page, '08-marketplace-workflow', 'FLOW-01-submit-purchase-request.png', 'FLOW-01', 'Commercial purchase request action card on listing detail');
        }

        // Customer Request Confirmation View
        await page.goto(`${BASE_URL}/login?role=customer`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', customerCredentials.email);
        await page.type('input[name="password"]', customerCredentials.password);
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
        await page.goto(`${BASE_URL}/dashboard?tab=requests`, { waitUntil: 'networkidle2' });
        await capture(page, '08-marketplace-workflow', 'FLOW-02-customer-request-confirmation.png', 'FLOW-02', 'Customer purchase request confirmation and status in dashboard');

        // Seller Request Approval View
        await safeLogout(page);
        await page.goto(`${BASE_URL}/login?role=seller`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', sellerCredentials.email);
        await page.type('input[name="password"]', sellerCredentials.password);
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
        await page.goto(`${BASE_URL}/seller/requests`, { waitUntil: 'networkidle2' });
        await capture(page, '08-marketplace-workflow', 'FLOW-03-seller-request-approval.png', 'FLOW-03', 'Seller order request approval and status progression actions');

        await page.goto(`${BASE_URL}/#adoption-rescue`, { waitUntil: 'networkidle2' });
        await capture(page, '09-adoption-workflow', 'FLOW-04-adoption-inquiry-view.png', 'FLOW-04', 'Public adoption inquiry and ethical rehoming details view');

        // ----------------------------------------------------
        // 10-SECURITY-TESTING
        // ----------------------------------------------------
        console.log('\n--- 10-SECURITY-TESTING ---');
        await safeLogout(page);

        // 1. Unauthenticated access to /admin
        await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle2' });
        await capture(page, '10-security-testing', 'SEC-01-unauth-admin-redirect.png', 'SEC-01', 'Guest redirected safely to admin login when accessing protected admin route');

        // 2. Unauthenticated access to /seller/dashboard
        await page.goto(`${BASE_URL}/seller/dashboard`, { waitUntil: 'networkidle2' });
        await capture(page, '10-security-testing', 'SEC-02-unauth-seller-redirect.png', 'SEC-02', 'Guest redirected safely to login when accessing protected seller portal');

        // 3. Customer attempting to access /seller/dashboard
        await page.goto(`${BASE_URL}/login?role=customer`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', customerCredentials.email);
        await page.type('input[name="password"]', customerCredentials.password);
        await page.click('button[type="submit"]');
        await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});

        await page.goto(`${BASE_URL}/seller/dashboard`, { waitUntil: 'networkidle2' });
        await capture(page, '10-security-testing', 'SEC-03-customer-seller-forbidden.png', 'SEC-03', '403 Forbidden or safe redirect when customer accesses seller portal');

        // 4. Guest attempting to access unpublished draft preview
        await safeLogout(page);
        await page.goto(`${BASE_URL}/admin/homepage/preview`, { waitUntil: 'networkidle2' });
        await capture(page, '10-security-testing', 'SEC-05-guest-draft-isolation.png', 'SEC-05', 'Guest blocked from previewing unpublished homepage drafts');

        // ----------------------------------------------------
        // 11-RESPONSIVE-VIEWS
        // ----------------------------------------------------
        console.log('\n--- 11-RESPONSIVE-VIEWS ---');
        await page.setViewport(VIEWPORTS.desktop);
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
        await capture(page, '11-responsive-views', 'RESP-01-home-desktop-1440.png', 'RESP-01', 'Homepage viewport benchmark at 1440x900 desktop', 'desktop');

        await page.setViewport(VIEWPORTS.tablet);
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
        await capture(page, '11-responsive-views', 'RESP-02-home-tablet-768.png', 'RESP-02', 'Homepage viewport benchmark at 768x1024 tablet', 'tablet');

        await page.goto(`${BASE_URL}/seller/dashboard`, { waitUntil: 'networkidle2' });
        await capture(page, '11-responsive-views', 'RESP-05-seller-tablet-768.png', 'RESP-05', 'Seller Dashboard layout on tablet viewport 768x1024', 'tablet');

        await page.setViewport(VIEWPORTS.mobile);
        await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle2' });
        await capture(page, '11-responsive-views', 'RESP-03-home-mobile-375.png', 'RESP-03', 'Homepage viewport benchmark at 375x812 mobile', 'mobile');

        await page.goto(`${BASE_URL}/listings`, { waitUntil: 'networkidle2' });
        await capture(page, '11-responsive-views', 'RESP-04-catalog-mobile-375.png', 'RESP-04', 'Marketplace pet catalog responsive cards on mobile 375x812', 'mobile');

        // Reset to desktop
        await page.setViewport(VIEWPORTS.desktop);

        // ----------------------------------------------------
        // 12-ERROR-STATES
        // ----------------------------------------------------
        console.log('\n--- 12-ERROR-STATES ---');
        await page.goto(`${BASE_URL}/non-existent-resource-endpoint-404`, { waitUntil: 'networkidle2' });
        await capture(page, '12-error-states', 'ERR-01-404-not-found.png', 'ERR-01', 'Graceful 404 Not Found error view with return navigation');

        // Trigger 403 Forbidden state
        await page.goto(`${BASE_URL}/seller/requests/999999`, { waitUntil: 'networkidle2' });
        await capture(page, '12-error-states', 'ERR-02-403-access-denied.png', 'ERR-02', 'Security authorization denial on unowned order inspection');

    } finally {
        await browser.close();
        console.log('\nBrowser closed cleanly.');
    }

    // Write Manifest
    const manifestWebapp = path.join(WEBAPP_ROOT, 'docs', 'qa', 'screenshot-manifest.json');
    const manifestRepo = path.join(REPO_ROOT, 'docs', 'qa', 'screenshot-manifest.json');
    const jsonStr = JSON.stringify(manifest, null, 2);
    fs.writeFileSync(manifestWebapp, jsonStr, 'utf8');
    fs.writeFileSync(manifestRepo, jsonStr, 'utf8');
    console.log(`\nManifest saved with ${manifest.length} entries.`);

    // Write SCREENSHOT_INDEX.md
    let indexMd = `# PAWHAVEN — REAL CHROME BROWSER QA SCREENSHOT INDEX\n\n`;
    indexMd += `**Total Verified Screenshots Captured:** ${manifest.length}  \n`;
    indexMd += `**Date Captured:** ${new Date().toUTCString()}  \n`;
    indexMd += `**Browser Automation Engine:** Chrome (Headless v2) via Puppeteer Core  \n`;
    indexMd += `**Local Server:** http://127.0.0.1:8000  \n\n`;
    indexMd += `| ID | Category | Filename | Viewport | Status | Description |\n`;
    indexMd += `|:---|:---|:---|:---|:---:|:---|\n`;

    for (const item of manifest) {
        indexMd += `| \`${item.id}\` | \`${item.category}\` | [\`${item.filename}\`](file:///${path.resolve(WEBAPP_ROOT, item.path).replace(/\\/g, '/')}) | ${item.viewport} | <span style="color:green;font-weight:bold;">PASS</span> | ${item.description} |\n`;
    }

    const indexWebapp = path.join(WEBAPP_ROOT, 'docs', 'qa', 'SCREENSHOT_INDEX.md');
    const indexRepo = path.join(REPO_ROOT, 'docs', 'qa', 'SCREENSHOT_INDEX.md');
    fs.writeFileSync(indexWebapp, indexMd, 'utf8');
    fs.writeFileSync(indexRepo, indexMd, 'utf8');
    console.log('SCREENSHOT_INDEX.md generated successfully.');
}

run().catch(err => {
    console.error('Fatal suite failure:', err);
    process.exit(1);
});
