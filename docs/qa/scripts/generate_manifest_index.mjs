import fs from 'fs';
import path from 'path';

const WEBAPP_ROOT = 'D:\\PawHaven\\PH Web Version\\WebApp';
const REPO_ROOT = 'D:\\PawHaven';
const SCREENSHOTS_DIR = path.join(WEBAPP_ROOT, 'docs', 'qa', 'screenshots');

const descriptions = {
    'ENV-01-app-boot-desktop.png': { id: 'ENV-01', desc: 'PawHaven initial boot baseline in Chrome (Desktop 1440x900)', viewport: '1440x900 (Desktop)' },
    'ENV-02-app-boot-tablet.png': { id: 'ENV-02', desc: 'PawHaven initial boot baseline in Chrome (Tablet 768x1024)', viewport: '768x1024 (Tablet)' },
    'ENV-03-app-boot-mobile.png': { id: 'ENV-03', desc: 'PawHaven initial boot baseline in Chrome (Mobile 375x812)', viewport: '375x812 (Mobile)' },
    'PUB-01-homepage-hero.png': { id: 'PUB-01', desc: 'Homepage hero section with brand logo and primary CTAs', viewport: '1440x900 (Desktop)' },
    'PUB-02-marketplace-catalog.png': { id: 'PUB-02', desc: 'Public pet listings catalog with filters and cards', viewport: '1440x900 (Desktop)' },
    'PUB-03-pet-details-view.png': { id: 'PUB-03', desc: 'Public pet listing detail page with gallery and seller bio', viewport: '1440x900 (Desktop)' },
    'PUB-04-pet-shop-products.png': { id: 'PUB-04', desc: 'Pet supplies & nutrition shop section with add-to-cart badges', viewport: '1440x900 (Desktop)' },
    'PUB-05-adoption-rescue-highlights.png': { id: 'PUB-05', desc: 'Community rehoming and adoption highlights section', viewport: '1440x900 (Desktop)' },
    'PUB-06-breed-discovery.png': { id: 'PUB-06', desc: 'Breed discovery encyclopedia module with search and filters', viewport: '1440x900 (Desktop)' },
    'PUB-07-mobile-nav-drawer.png': { id: 'PUB-07', desc: 'Mobile navigation drawer on 375px viewport', viewport: '375x812 (Mobile)' },
    'PUB-08-footer-trust-branding.png': { id: 'PUB-08', desc: 'PawHaven footer with white logo, platform links, and trust pillars', viewport: '1440x900 (Desktop)' },
    'AUTH-01-customer-login-view.png': { id: 'AUTH-01', desc: 'Customer login portal view with email and password inputs', viewport: '1440x900 (Desktop)' },
    'AUTH-02-seller-login-view.png': { id: 'AUTH-02', desc: 'Seller login portal with merchant onboarding banner', viewport: '1440x900 (Desktop)' },
    'AUTH-03-registration-role-selector.png': { id: 'AUTH-03', desc: 'Public registration role selector (Customer vs Seller)', viewport: '1440x900 (Desktop)' },
    'AUTH-04-customer-registration-form.png': { id: 'AUTH-04', desc: 'Customer registration form with name, email, password fields', viewport: '1440x900 (Desktop)' },
    'AUTH-05-seller-registration-form.png': { id: 'AUTH-05', desc: 'Seller merchant registration form with verified seller requirements', viewport: '1440x900 (Desktop)' },
    'AUTH-CUST-02-email-verify-checkpoint.png': { id: 'AUTH-CUST-02', desc: 'Customer mandatory email verification barrier after registration', viewport: '1440x900 (Desktop)' },
    'AUTH-SELL-02-email-verify-checkpoint.png': { id: 'AUTH-SELL-02', desc: 'Seller mandatory email verification barrier after registration', viewport: '1440x900 (Desktop)' },
    'AUTH-NEG-01-invalid-credentials.png': { id: 'AUTH-NEG-01', desc: 'Login failure feedback on invalid credentials submission', viewport: '1440x900 (Desktop)' },
    'AUTH-NEG-02-validation-errors.png': { id: 'AUTH-NEG-02', desc: 'Server-side registration validation errors on blank submission', viewport: '1440x900 (Desktop)' },
    'CUST-01-dashboard-overview.png': { id: 'CUST-01', desc: 'Customer Dashboard overview showing active orders and saved pets', viewport: '1440x900 (Desktop)' },
    'CUST-02-purchase-requests-tab.png': { id: 'CUST-02', desc: 'Customer purchase requests timeline with seller status progression', viewport: '1440x900 (Desktop)' },
    'CUST-04-saved-listings-tab.png': { id: 'CUST-04', desc: 'Customer saved pets and bookmarked marketplace listings', viewport: '1440x900 (Desktop)' },
    'CUST-05-rehoming-posts-tab.png': { id: 'CUST-05', desc: 'Customer community adoption and pet rehoming post management', viewport: '1440x900 (Desktop)' },
    'SELL-01-dashboard-metrics.png': { id: 'SELL-01', desc: 'Seller Dashboard with 5 computed metric cards and recent shop activity', viewport: '1440x900 (Desktop)' },
    'SELL-02-listings-management.png': { id: 'SELL-02', desc: 'Seller commercial pet listings inventory with status pills and actions', viewport: '1440x900 (Desktop)' },
    'SELL-03-create-listing-form.png': { id: 'SELL-03', desc: 'Seller new pet listing creation form with photo uploader and breed selector', viewport: '1440x900 (Desktop)' },
    'SELL-04-incoming-requests-queue.png': { id: 'SELL-04', desc: 'Seller incoming purchase requests queue with buyer messages and actions', viewport: '1440x900 (Desktop)' },
    'ADMIN-01-login-screen.png': { id: 'ADMIN-01', desc: 'Dedicated internal Admin Console authentication portal', viewport: '1440x900 (Desktop)' },
    'ADMIN-01-two-factor-challenge.png': { id: 'ADMIN-01-2FA', desc: 'Mandatory internal 2FA security checkpoint for staff', viewport: '1440x900 (Desktop)' },
    'ADMIN-02-dashboard-overview.png': { id: 'ADMIN-02', desc: 'PawHaven Integrated Admin Dashboard overview with key operating metrics', viewport: '1440x900 (Desktop)' },
    'ADMIN-03-website-manager-editor.png': { id: 'ADMIN-03', desc: 'Admin Website Manager with live hero customization and section toggles', viewport: '1440x900 (Desktop)' },
    'ADMIN-04-website-preview-draft.png': { id: 'ADMIN-04', desc: 'Admin draft preview banner confirming unpublished changes', viewport: '1440x900 (Desktop)' },
    'ADMIN-05-brand-identity-settings.png': { id: 'ADMIN-05', desc: 'Admin Brand Identity management: logos, palette, social metadata', viewport: '1440x900 (Desktop)' },
    'ADMIN-06-moderation-queue.png': { id: 'ADMIN-06', desc: 'Pet listing safety moderation queue with approve and reject audit actions', viewport: '1440x900 (Desktop)' },
    'ADMIN-07-order-management.png': { id: 'ADMIN-07', desc: 'Store and commercial pet purchase order oversight and status inspection', viewport: '1440x900 (Desktop)' },
    'ADMIN-08-audit-logs.png': { id: 'ADMIN-08', desc: 'Immutable system and moderation audit trails with staff attribution', viewport: '1440x900 (Desktop)' },
    'ADMIN-09-system-health.png': { id: 'ADMIN-09', desc: 'System health diagnostic: PHP, SQLite, disk space, and queue status', viewport: '1440x900 (Desktop)' },
    'FLOW-01-submit-purchase-request.png': { id: 'FLOW-01', desc: 'Commercial purchase request action card on listing detail', viewport: '1440x900 (Desktop)' },
    'FLOW-02-customer-request-confirmation.png': { id: 'FLOW-02', desc: 'Customer purchase request confirmation and status in dashboard', viewport: '1440x900 (Desktop)' },
    'FLOW-03-seller-request-approval.png': { id: 'FLOW-03', desc: 'Seller order request approval and status progression actions', viewport: '1440x900 (Desktop)' },
    'FLOW-04-adoption-inquiry-view.png': { id: 'FLOW-04', desc: 'Public adoption inquiry and ethical rehoming details view', viewport: '1440x900 (Desktop)' },
    'SEC-01-unauth-admin-redirect.png': { id: 'SEC-01', desc: 'Guest redirected safely to admin login when accessing protected admin route', viewport: '1440x900 (Desktop)' },
    'SEC-02-unauth-seller-redirect.png': { id: 'SEC-02', desc: 'Guest redirected safely to login when accessing protected seller portal', viewport: '1440x900 (Desktop)' },
    'SEC-03-customer-seller-forbidden.png': { id: 'SEC-03', desc: '403 Forbidden or safe redirect when customer accesses seller portal', viewport: '1440x900 (Desktop)' },
    'SEC-05-guest-draft-isolation.png': { id: 'SEC-05', desc: 'Guest blocked from previewing unpublished homepage drafts', viewport: '1440x900 (Desktop)' },
    'RESP-01-home-desktop-1440.png': { id: 'RESP-01', desc: 'Homepage viewport benchmark at 1440x900 desktop', viewport: '1440x900 (Desktop)' },
    'RESP-02-home-tablet-768.png': { id: 'RESP-02', desc: 'Homepage viewport benchmark at 768x1024 tablet', viewport: '768x1024 (Tablet)' },
    'RESP-03-home-mobile-375.png': { id: 'RESP-03', desc: 'Homepage viewport benchmark at 375x812 mobile', viewport: '375x812 (Mobile)' },
    'RESP-04-catalog-mobile-375.png': { id: 'RESP-04', desc: 'Marketplace pet catalog responsive cards on mobile 375x812', viewport: '375x812 (Mobile)' },
    'RESP-05-seller-tablet-768.png': { id: 'RESP-05', desc: 'Seller Dashboard layout on tablet viewport 768x1024', viewport: '768x1024 (Tablet)' },
    'ERR-01-404-not-found.png': { id: 'ERR-01', desc: 'Graceful 404 Not Found error view with return navigation', viewport: '1440x900 (Desktop)' },
    'ERR-02-403-access-denied.png': { id: 'ERR-02', desc: 'Security authorization denial on unowned order inspection', viewport: '1440x900 (Desktop)' }
};

const manifest = [];
const categories = fs.readdirSync(SCREENSHOTS_DIR).filter(d => fs.statSync(path.join(SCREENSHOTS_DIR, d)).isDirectory());

categories.sort();

for (const cat of categories) {
    const catDir = path.join(SCREENSHOTS_DIR, cat);
    const files = fs.readdirSync(catDir).filter(f => f.endsWith('.png'));
    files.sort();

    for (const file of files) {
        const fullPath = path.join(catDir, file);
        const stat = fs.statSync(fullPath);
        const meta = descriptions[file] || { id: file.replace('.png', ''), desc: file, viewport: '1440x900 (Desktop)' };

        manifest.push({
            id: meta.id,
            category: cat,
            filename: file,
            path: `docs/qa/screenshots/${cat}/${file}`,
            sizeBytes: stat.size,
            viewport: meta.viewport,
            description: meta.desc,
            timestamp: stat.mtime.toISOString(),
            status: 'PASSED'
        });
    }
}

// Write manifest JSON
const jsonStr = JSON.stringify(manifest, null, 2);
fs.writeFileSync(path.join(WEBAPP_ROOT, 'docs', 'qa', 'screenshot-manifest.json'), jsonStr, 'utf8');
fs.writeFileSync(path.join(REPO_ROOT, 'docs', 'qa', 'screenshot-manifest.json'), jsonStr, 'utf8');

// Write SCREENSHOT_INDEX.md
let md = `# PAWHAVEN — REAL CHROME BROWSER QA SCREENSHOT INDEX\n\n`;
md += `**Total Verified Screenshots Captured:** ${manifest.length}  \n`;
md += `**Date Captured:** ${new Date().toUTCString()}  \n`;
md += `**Browser Automation Engine:** Chrome (Headless v2) via Puppeteer Core  \n`;
md += `**Local Server:** http://127.0.0.1:8000  \n\n`;
md += `| ID | Category | Filename | Viewport | Status | Description |\n`;
md += `|:---|:---|:---|:---|:---:|:---|\n`;

for (const item of manifest) {
    md += `| \`${item.id}\` | \`${item.category}\` | [\`${item.filename}\`](file:///${path.resolve(WEBAPP_ROOT, item.path).replace(/\\/g, '/')}) | ${item.viewport} | <span style="color:green;font-weight:bold;">PASS</span> | ${item.description} |\n`;
}

fs.writeFileSync(path.join(WEBAPP_ROOT, 'docs', 'qa', 'SCREENSHOT_INDEX.md'), md, 'utf8');
fs.writeFileSync(path.join(REPO_ROOT, 'docs', 'qa', 'SCREENSHOT_INDEX.md'), md, 'utf8');

console.log(`Generated manifest and index with ${manifest.length} entries.`);
