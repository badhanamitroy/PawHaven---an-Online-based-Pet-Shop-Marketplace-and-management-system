import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const WEBAPP_ROOT = path.resolve(SCRIPT_DIR, '../../..');
const REPO_ROOT = path.resolve(WEBAPP_ROOT, '..', '..');
const CHROME_PATH = process.env.CHROME_PATH;

function getBase64Image(relPath) {
    const fullPath = path.join(WEBAPP_ROOT, relPath);
    if (!fs.existsSync(fullPath)) {
        console.warn('Image not found:', fullPath);
        return '';
    }
    const data = fs.readFileSync(fullPath).toString('base64');
    const ext = path.extname(fullPath).replace('.', '');
    const mime = ext === 'svg' ? 'image/svg+xml' : `image/${ext}`;
    return `data:${mime};base64,${data}`;
}

const logoGreen = getBase64Image('public/images/pawhaven-logo-green.png');
const logoWhite = getBase64Image('public/images/pawhaven-logo-white.png');

function renderCommonStyles() {
    return `
    <style>
        @page {
            size: A4;
            margin: 20mm 15mm 20mm 15mm;
            @bottom-right {
                content: counter(page);
            }
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #202820;
            background: #FFFFFF;
            line-height: 1.6;
            font-size: 11pt;
            margin: 0;
            padding: 0;
        }
        .page-break { page-break-after: always; }
        .cover-page {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px 20px;
            page-break-after: always;
        }
        .cover-header {
            border-bottom: 3px solid #245C45;
            padding-bottom: 25px;
        }
        .cover-title {
            font-size: 30pt;
            font-weight: 800;
            color: #173F32;
            line-height: 1.15;
            margin: 25px 0 10px 0;
        }
        .cover-subtitle {
            font-size: 15pt;
            color: #4A554D;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .cover-badge {
            display: inline-block;
            background: #245C45;
            color: #FFFFFF;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 6px 14px;
            border-radius: 20px;
        }
        .cover-meta {
            background: #FAF8F2;
            border: 1px solid #E8E5DD;
            border-radius: 12px;
            padding: 20px;
            font-size: 10pt;
        }
        .cover-meta table { width: 100%; border-collapse: collapse; }
        .cover-meta td { padding: 4px 8px; }
        .cover-meta td.label { font-weight: 700; color: #173F32; width: 35%; }
        
        h1 {
            color: #173F32;
            font-size: 20pt;
            font-weight: 800;
            border-bottom: 2px solid #E8E5DD;
            padding-bottom: 8px;
            margin-top: 30px;
            margin-bottom: 16px;
        }
        h2 {
            color: #245C45;
            font-size: 14pt;
            font-weight: 700;
            margin-top: 24px;
            margin-bottom: 12px;
        }
        h3 {
            color: #173F32;
            font-size: 11.5pt;
            font-weight: 700;
            margin-top: 18px;
            margin-bottom: 8px;
        }
        p { margin: 0 0 12px 0; text-align: justify; }
        ul, ol { margin: 0 0 14px 20px; padding: 0; }
        li { margin-bottom: 6px; }
        
        .alert-box {
            background: #FAF8F2;
            border-left: 4px solid #245C45;
            border-radius: 0 8px 8px 0;
            padding: 12px 16px;
            margin: 16px 0;
            font-size: 10pt;
        }
        .alert-box.tip { border-left-color: #245C45; background: #F3F7F5; }
        .alert-box.security { border-left-color: #D97706; background: #FFFBEB; }
        .alert-box.critical { border-left-color: #DC2626; background: #FEF2F2; }
        
        .screenshot-container {
            margin: 18px 0;
            text-align: center;
            page-break-inside: avoid;
        }
        .screenshot-container img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1px solid #D5D1C8;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }
        .screenshot-caption {
            font-size: 8.5pt;
            color: #69736B;
            font-weight: 600;
            margin-top: 6px;
            font-style: italic;
        }
        
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 9.5pt;
        }
        table.data-table th {
            background: #173F32;
            color: #FFFFFF;
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
        }
        table.data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #E8E5DD;
        }
        table.data-table tr:nth-child(even) td {
            background: #FAF8F2;
        }
        
        .toc-list { list-style: none; padding-left: 0; }
        .toc-list li {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dotted #B0AAA0;
            padding-bottom: 4px;
            font-size: 10.5pt;
        }
        .toc-list li span.title { font-weight: 600; color: #173F32; }
        .toc-list li span.page { font-weight: 700; color: #245C45; }
        code {
            background: #F0EDE5;
            padding: 2px 5px;
            border-radius: 4px;
            font-family: Consolas, Monaco, monospace;
            font-size: 9.5pt;
            color: #173F32;
        }
    </style>
    `;
}

function buildAdminGuideHtml() {
    return `
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>PawHaven — Owner & Administrator Guide</title>
        ${renderCommonStyles()}
    </head>
    <body>
        <!-- COVER PAGE -->
        <div class="cover-page">
            <div class="cover-header">
                <img src="${logoGreen}" style="height: 60px; object-fit: contain;" alt="PawHaven Logo">
                <div style="margin-top: 20px;">
                    <span class="cover-badge">Self-Hosted Commercial Software Edition</span>
                </div>
                <div class="cover-title">Owner &amp; Administrator Operational Guide</div>
                <div class="cover-subtitle">Complete Installation, Website Management, Moderation, and Security Manual</div>
            </div>
            
            <div class="cover-meta">
                <table>
                    <tr><td class="label">Product Name:</td><td>PawHaven Self-Hosted Platform</td></tr>
                    <tr><td class="label">Software Architecture:</td><td>Laravel 13 • Livewire • Tailwind CSS v4 • SQLite</td></tr>
                    <tr><td class="label">Document Classification:</td><td>Proprietary Store Owner &amp; Administrator Manual</td></tr>
                    <tr><td class="label">Target Audience:</td><td>Source-Code License Buyers, Technical Operators, Store Admins</td></tr>
                    <tr><td class="label">Testing Evidence:</td><td>Historical browser QA captures are available; validate each release independently</td></tr>
                    <tr><td class="label">Document Version:</td><td>2.0 Release Edition (October 2026)</td></tr>
                </table>
            </div>
        </div>

        <!-- TABLE OF CONTENTS -->
        <div>
            <h1>Table of Contents</h1>
            <ul class="toc-list">
                <li><span class="title">1. Executive Overview &amp; Architecture</span><span class="page">Page 2</span></li>
                <li><span class="title">2. Installation, Environment &amp; First-Time Bootstrap</span><span class="page">Page 3</span></li>
                <li><span class="title">3. Dedicated Admin Console &amp; Two-Factor Security (2FA)</span><span class="page">Page 4</span></li>
                <li><span class="title">4. Integrated Admin Dashboard Overview</span><span class="page">Page 5</span></li>
                <li><span class="title">5. Website Manager: Live Customization &amp; Draft Publishing</span><span class="page">Page 6</span></li>
                <li><span class="title">6. Brand Identity, Logos &amp; Visual Palette Settings</span><span class="page">Page 7</span></li>
                <li><span class="title">7. Marketplace Commercial Listing Moderation Queue</span><span class="page">Page 8</span></li>
                <li><span class="title">8. Platform Order Management &amp; Transaction Oversight</span><span class="page">Page 9</span></li>
                <li><span class="title">9. Immutable Staff Audit Trails &amp; Security Logs</span><span class="page">Page 10</span></li>
                <li><span class="title">10. System Health Diagnostics, Backups &amp; Production Hardening</span><span class="page">Page 11</span></li>
            </ul>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 1 -->
        <div>
            <h1>1. Executive Overview &amp; Architecture</h1>
            <p><strong>PawHaven</strong> is a turnkey, self-hosted web application for ethical pet commerce, adoption/rehoming community networks, breed exploration, and pet supplies retail. Designed specifically as a commercial source-code licensed product, PawHaven gives you total operational independence over your marketplace without external developer lock-in, mandatory SaaS subscriptions, remote telemetry backdoors, or licensing callbacks.</p>
            
            <div class="alert-box tip">
                <strong>Core Philosophy:</strong> PawHaven maintains a strict separation between public CMS presentation data (managed through the Website Manager) and high-integrity transactional business records (pet listings, user profiles, orders, and payment records).
            </div>

            <h2>Application Technology Stack</h2>
            <table class="data-table">
                <thead>
                    <tr><th>Component</th><th>Technology</th><th>Operational Role</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Backend Framework</strong></td><td>Laravel 13 MVC</td><td>Business logic, robust routing, policies, Eloquent ORM</td></tr>
                    <tr><td><strong>Frontend Reactivity</strong></td><td>Livewire &amp; Flux UI</td><td>Dynamic interfaces without SPA complexity</td></tr>
                    <tr><td><strong>Design System</strong></td><td>Tailwind CSS v4 &amp; Vite Plus</td><td>High-performance responsive CSS and asset compilation</td></tr>
                    <tr><td><strong>Database Engine</strong></td><td>SQLite 3 (WAL Journaling)</td><td>Zero-configuration, portable, ACID-compliant database</td></tr>
                    <tr><td><strong>Security Guardrails</strong></td><td>Laravel Fortify &amp; Email OTP</td><td>Role-based access control, hashed 2FA, brute-force throttling</td></tr>
                </tbody>
            </table>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/00-environment/ENV-01-app-boot-desktop.png')}" alt="PawHaven Boot Screen">
                <div class="screenshot-caption">Figure 1.1: PawHaven Production Boot Baseline in Google Chrome (Desktop 1440x900)</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 2 -->
        <div>
            <h1>2. Installation, Environment &amp; First-Time Bootstrap</h1>
            <p>PawHaven is delivered as a complete standalone source repository. As a buyer or server operator, you can deploy the platform onto any modern PHP 8.2+ environment (VPS, dedicated Linux server, or Windows dev machine) in minutes.</p>

            <h2>Step-by-Step Installation Procedure</h2>
            <ol>
                <li><strong>Clone or Extract Source Code:</strong> Place project files in your web directory (e.g., <code>/var/www/pawhaven</code>).</li>
                <li><strong>Install PHP &amp; JavaScript Dependencies:</strong>
                    <br><code>composer install --no-dev --optimize-autoloader</code>
                    <br><code>npm ci &amp;&amp; npm run build</code>
                </li>
                <li><strong>Configure Environment File:</strong> Copy <code>.env.example</code> to <code>.env</code> and generate the application key:
                    <br><code>cp .env.example .env &amp;&amp; php artisan key:generate</code>
                </li>
                <li><strong>Initialize Database:</strong> Create SQLite storage file and execute database migrations:
                    <br><code>touch database/database.sqlite &amp;&amp; php artisan migrate --force</code>
                </li>
                <li><strong>Create Public Storage Symlink:</strong> Ensure media uploads and branding assets are publicly reachable:
                    <br><code>php artisan storage:link</code>
                </li>
                <li><strong>Bootstrap Initial Administrator Account:</strong> Run <code>php artisan admin:create</code> and enter the password at the hidden prompt. Passwords are not accepted on the command line.
                </li>
                <li><strong>Assign the Initial Shop Owner:</strong> Sign in to the verified Platform Administrator account, then use Admin → Business Management to assign Owner access to the verified account that will run the company. The migration creates the company record but does not assign an Owner automatically.</li>
            </ol>

            <div class="alert-box critical">
                <strong>Data Safety Rule:</strong> Never run <code>php artisan migrate:fresh</code> or <code>php artisan db:wipe</code> on a live deployment. These commands permanently destroy existing user accounts, customer orders, and listing inventories.
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 3 -->
        <div>
            <h1>3. Dedicated Admin Console &amp; Two-Factor Security (2FA)</h1>
            <p>Administrative authentication is physically isolated from the customer and seller registration portals. Staff and administrators log in exclusively through the protected <code>/admin/login</code> route.</p>

            <h2>Mandatory Two-Factor Security (Email OTP)</h2>
            <p>To eliminate unauthorized access resulting from credential stuffing or password re-use, PawHaven enforces a mandatory two-step verification workflow for all administrative sessions:</p>
            <ul>
                <li><strong>Primary Credential Verification:</strong> The administrator enters their authorized email and password.</li>
                <li><strong>Email OTP Dispatch:</strong> The system automatically generates a single-use 6-digit cryptographic code and dispatches it via SMTP to the administrator's verified email address.</li>
                <li><strong>Challenge Screen Enforcement:</strong> The browser is held at <code>/admin/two-factor</code>. No administrative routes can be accessed until the code is validated.</li>
                <li><strong>Anti-Replay Invalidation:</strong> Once verified, the OTP is instantly purged from cache to prevent replay attacks.</li>
            </ul>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-01-login-screen.png')}" alt="Admin Login Screen">
                <div class="screenshot-caption">Figure 3.1: Dedicated Internal Administrative Login Gateway (/admin/login)</div>
            </div>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-01-two-factor-challenge.png')}" alt="2FA Screen">
                <div class="screenshot-caption">Figure 3.2: Mandatory 2FA Email OTP Verification Screen (/admin/two-factor)</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 4 -->
        <div>
            <h1>4. Integrated Admin Dashboard Overview</h1>
            <p>The <strong>Admin Dashboard</strong> (<code>/admin</code>) serves as the operational nerve center of PawHaven. It aggregates real-time platform telemetry directly from persisted database models, avoiding synthetic metrics or hardcoded placeholder counts.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-02-dashboard-overview.png')}" alt="Admin Dashboard">
                <div class="screenshot-caption">Figure 4.1: Integrated Admin Dashboard with Real-Time Metrics and Quick Navigation</div>
            </div>

            <h2>Core Telemetry Cards</h2>
            <ul>
                <li><strong>Active Commercial Listings:</strong> Live count of commercial pet sale listings currently visible to public visitors.</li>
                <li><strong>Pending Purchase Requests:</strong> High-priority buyer orders awaiting review or verification.</li>
                <li><strong>Payment Submissions Requiring Review:</strong> Offline bank transfer or Cash-on-Delivery payment proofs needing verification.</li>
                <li><strong>Published Adoption Posts:</strong> Active community rehoming announcements.</li>
                <li><strong>Pending Listing Moderation Queue:</strong> Pet listings requiring staff compliance review before going live.</li>
            </ul>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 5 -->
        <div>
            <h1>5. Website Manager: Live Customization &amp; Draft Publishing</h1>
            <p>The <strong>Website Manager</strong> (<code>/admin/homepage</code>) allows the non-technical site owner to update homepage copy, hero headlines, call-to-action buttons, and toggle entire modules on and off without modifying Blade templates or writing HTML code.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-03-website-manager-editor.png')}" alt="Website Manager">
                <div class="screenshot-caption">Figure 5.1: Section-Based Website Manager Live Customization Form</div>
            </div>

            <h2>Draft vs. Published Workflow (No Visitor Disruption)</h2>
            <p>PawHaven protects public visitors from observing half-finished website modifications through an isolated draft pipeline:</p>
            <ol>
                <li><strong>Editing &amp; Saving Draft:</strong> The administrator modifies hero headlines or toggles sections and clicks <em>Save Draft</em>. These values are saved under the <code>homepage_draft</code> settings group.</li>
                <li><strong>Full-Fidelity Preview:</strong> Navigating to <code>/admin/homepage/preview</code> displays the exact homepage layout as it would appear with draft changes applied, accompanied by a prominent draft alert bar.</li>
                <li><strong>Publishing:</strong> Clicking <em>Publish Changes</em> commits the draft settings to the live <code>brand</code> and <code>homepage</code> production configuration. Public visitors immediately see the updated content.</li>
            </ol>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-04-website-preview-draft.png')}" alt="Draft Preview Banner">
                <div class="screenshot-caption">Figure 5.2: Draft Preview Mode showing Draft Alert Banner visible only to Administrators</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 6 -->
        <div>
            <h1>6. Brand Identity, Logos &amp; Visual Palette Settings</h1>
            <p>Under <code>/admin/brand</code>, the administrator can customize PawHaven's branding parameters to align with their company identity.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-05-brand-identity-settings.png')}" alt="Brand Settings">
                <div class="screenshot-caption">Figure 6.1: Brand Identity Configuration: Colors, Logos, and Organization Details</div>
            </div>

            <h2>Supported Customization Assets</h2>
            <ul>
                <li><strong>Brand Name &amp; Tagline:</strong> Configures site-wide headings and meta titles.</li>
                <li><strong>Light Logo (For Cream/White Backgrounds):</strong> Uploaded PNG or SVG rendered in the public header and invoice printouts.</li>
                <li><strong>Dark Logo (For Deep Green/Dark Backgrounds):</strong> Uploaded white/light-colored logo rendered in the footer and admin sidebar.</li>
                <li><strong>Favicon:</strong> <code>.ico</code> or <code>.png</code> square icon rendered in browser navigation tabs.</li>
                <li><strong>Hexadecimal Color Palette:</strong> Primary (Forest Green <code>#173F32</code>), Secondary (Sage Green <code>#245C45</code>), and Accent (Coral <code>#F28C75</code>).</li>
            </ul>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 7 -->
        <div>
            <h1>7. Marketplace Commercial Listing Moderation Queue</h1>
            <p>To enforce animal welfare standards and protect against unauthorized or unethical pet sales, all newly submitted or updated commercial listings enter the <strong>Moderation Queue</strong> (<code>/admin/marketplace/moderation</code>).</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-06-moderation-queue.png')}" alt="Moderation Queue">
                <div class="screenshot-caption">Figure 7.1: Pet Listing Safety Moderation Queue with Staff Audit Action Buttons</div>
            </div>

            <h2>Moderation Workflow</h2>
            <ul>
                <li><strong>Queue Inspection:</strong> Staff reviews the pet's photos, age, breed documentation, health records, and seller background.</li>
                <li><strong>Approval Action:</strong> Clicking <em>Approve Listing</em> changes status to <code>published</code>. The listing instantly becomes visible in the public marketplace catalog.</li>
                <li><strong>Rejection Action:</strong> Clicking <em>Reject Listing</em> requires entering a clear explanation reason. The listing status transitions to <code>rejected</code> and is returned to the seller with corrective notes.</li>
                <li><strong>Audit Attribution:</strong> Every moderation event is recorded in the permanent audit trail with staff ID and timestamp.</li>
            </ul>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 8 -->
        <div>
            <h1>8. Platform Order Management &amp; Transaction Oversight</h1>
            <p>The <strong>Orders Ledger</strong> (<code>/admin/orders</code>) tracks commercial pet purchase requests and pet shop e-commerce orders across all sellers and customers.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-07-order-management.png')}" alt="Order Management">
                <div class="screenshot-caption">Figure 8.1: Global Platform Order Oversight and Payment Inspection View</div>
            </div>

            <h2>Order Status Progression</h2>
            <table class="data-table">
                <thead>
                    <tr><th>Status</th><th>Meaning</th><th>Permitted Actions</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>pending</code></td><td>Customer submitted buy request; seller has not accepted</td><td>Seller/Admin can Approve or Decline</td></tr>
                    <tr><td><code>accepted</code></td><td>Seller confirmed availability; awaiting payment verification</td><td>Submit payment or mark verified</td></tr>
                    <tr><td><code>processing</code></td><td>Payment verified; pet being prepared for transfer</td><td>Update shipping/handover details</td></tr>
                    <tr><td><code>completed</code></td><td>Pet safely delivered; transaction fulfilled</td><td>Finalized record</td></tr>
                    <tr><td><code>cancelled</code></td><td>Customer or seller cancelled order prior to payment</td><td>Immutable historic record</td></tr>
                </tbody>
            </table>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 9 -->
        <div>
            <h1>9. Immutable Staff Audit Trails &amp; Security Logs</h1>
            <p>Every administrative action taken within the console is recorded in the <strong>Audit Logs</strong> (<code>/admin/audit-logs</code>). This table is append-only and cannot be altered or cleared through the UI.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-08-audit-logs.png')}" alt="Audit Logs">
                <div class="screenshot-caption">Figure 9.1: Immutable System and Moderation Audit Logs with Staff Attribution</div>
            </div>

            <h2>Logged Security Events</h2>
            <ul>
                <li>Administrative user sign-ins and successful 2FA code verifications</li>
                <li>Brand identity updates and logo image uploads</li>
                <li>Website Manager draft saves and live publications</li>
                <li>Pet listing approvals, rejections, and removals</li>
                <li>User role updates and account status alterations</li>
            </ul>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 10 -->
        <div>
            <h1>10. System Health Diagnostics, Backups &amp; Production Hardening</h1>
            <p>Under <code>/admin/system</code>, the administrator can review runtime diagnostics to verify server health, database connectivity, storage volume capacity, and background job queue processing.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/07-admin-portal/ADMIN-09-system-health.png')}" alt="System Health">
                <div class="screenshot-caption">Figure 10.1: Live System Health Diagnostics: PHP, SQLite, Disk Capacity, and Cache</div>
            </div>

            <h2>Backup &amp; Disaster Recovery Best Practices</h2>
            <p>Because PawHaven utilizes a self-contained SQLite architecture with local public storage symlinks, complete backup is accomplished via two simple commands:</p>
            <ol>
                <li><strong>Database Backup:</strong> Create an immediate atomic snapshot using SQLite backup CLI:
                    <br><code>sqlite3 database/database.sqlite ".backup 'backups/db-$(date +%Y%m%d).sqlite'"</code>
                </li>
                <li><strong>Media Asset Backup:</strong> Archive the uploaded photos and branding files:
                    <br><code>tar -czf "backups/media-$(date +%Y%m%d).tar.gz" storage/app/public/</code>
                </li>
            </ol>

            <div class="alert-box security">
                <strong>Production Hardening Checklist:</strong>
                <br>• Set <code>APP_ENV=production</code> and <code>APP_DEBUG=false</code> in <code>.env</code>.
                <br>• Enforce HTTPS across your web server (Nginx/Apache SSL certificate).
                <br>• Restrict file permissions: <code>chmod 600 .env</code> and <code>chmod 660 database/database.sqlite</code>.
                <br>• Run <code>php artisan config:cache &amp;&amp; php artisan route:cache &amp;&amp; php artisan view:cache</code> for maximum speed.
            </div>
        </div>
    </body>
    </html>
    `;
}

function buildUserGuideHtml() {
    return `
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>PawHaven — User, Buyer & Seller Portal Guide</title>
        ${renderCommonStyles()}
    </head>
    <body>
        <!-- COVER PAGE -->
        <div class="cover-page">
            <div class="cover-header">
                <img src="${logoGreen}" style="height: 60px; object-fit: contain;" alt="PawHaven Logo">
                <div style="margin-top: 20px;">
                    <span class="cover-badge" style="background: #173F32;">User, Buyer &amp; Merchant Guide</span>
                </div>
                <div class="cover-title">PawHaven User &amp; Buyer Companion Guide</div>
                <div class="cover-subtitle">Discovering Pets, Submitting Requests, Adoptions, and Seller Portal Operations</div>
            </div>
            
            <div class="cover-meta">
                <table>
                    <tr><td class="label">Product Name:</td><td>PawHaven Ethical Pet Platform</td></tr>
                    <tr><td class="label">Audience:</td><td>Prospective Pet Parents, Community Adopters, Sellers &amp; Shopkeepers</td></tr>
                    <tr><td class="label">Document Purpose:</td><td>End-to-End User Journeys and Seller Portal Walkthrough</td></tr>
                    <tr><td class="label">Visual Documentation:</td><td>Illustrated Real Chrome Browser Captures across All Viewports</td></tr>
                    <tr><td class="label">Publication Date:</td><td>October 2026</td></tr>
                </table>
            </div>
        </div>

        <!-- TABLE OF CONTENTS -->
        <div>
            <h1>Table of Contents</h1>
            <ul class="toc-list">
                <li><span class="title">1. Welcome to PawHaven: Ethical Pet Care</span><span class="page">Page 2</span></li>
                <li><span class="title">2. Exploring the Public Platform (Hero, Breeds &amp; Trust)</span><span class="page">Page 3</span></li>
                <li><span class="title">3. Browsing the Commercial Marketplace Catalog</span><span class="page">Page 4</span></li>
                <li><span class="title">4. Pet Details, Health Guarantees &amp; Purchase Requests</span><span class="page">Page 5</span></li>
                <li><span class="title">5. Pet Supplies &amp; Nutrition E-Commerce Shop</span><span class="page">Page 6</span></li>
                <li><span class="title">6. Community Adoption &amp; Ethical Rehoming Workflows</span><span class="page">Page 7</span></li>
                <li><span class="title">7. Customer Portal: Order Tracking &amp; Saved Pets</span><span class="page">Page 8</span></li>
                <li><span class="title">8. Seller Portal: Merchant Onboarding &amp; Metric Cards</span><span class="page">Page 9</span></li>
                <li><span class="title">9. Seller Operations: Creating &amp; Managing Pet Listings</span><span class="page">Page 10</span></li>
                <li><span class="title">10. Seller Operations: Incoming Purchase Requests &amp; Fulfillment</span><span class="page">Page 11</span></li>
            </ul>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 1 -->
        <div>
            <h1>1. Welcome to PawHaven: Ethical Pet Care</h1>
            <p><strong>PawHaven</strong> is dedicated to connecting responsible pet owners, licensed breeders, rescue organizations, and prospective pet parents. Built with an uncompromising focus on animal welfare and transparency, PawHaven eliminates misleading listings, prevents puppy mill sales, and ensures that every animal finding a new home receives proper care and verification.</p>

            <div class="alert-box tip">
                <strong>Three Pillars of PawHaven:</strong>
                <br>1. <strong>Listing Moderation:</strong> Every commercial pet listing is verified by staff before going public.
                <br>2. <strong>Role Accountability:</strong> Separate customer, seller, and staff roles ensure clear traceability.
                <br>3. <strong>Data Integrity:</strong> Active pet listings and transaction records are preserved permanently for auditing.
            </div>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/01-public-website/PUB-01-homepage-hero.png')}" alt="PawHaven Hero">
                <div class="screenshot-caption">Figure 1.1: PawHaven Homepage Hero — "Your next best friend is waiting."</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 2 -->
        <div>
            <h1>2. Exploring the Public Platform (Hero, Breeds &amp; Trust)</h1>
            <p>Visitors can seamlessly explore pets, browse breed care profiles, and learn about the platform without being forced to create an account immediately.</p>

            <h2>Breed Discovery Encyclopedia</h2>
            <p>Before purchasing or adopting a pet, prospective families can visit the <strong>Breed Discovery</strong> section (<code>#breeds</code>) to learn about temperament, size, shedding levels, exercise requirements, and grooming commitments.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/01-public-website/PUB-06-breed-discovery.png')}" alt="Breed Discovery">
                <div class="screenshot-caption">Figure 2.1: Breed Discovery Module showcasing temperament and care guides</div>
            </div>

            <h2>Platform Trust &amp; Safety Footer</h2>
            <p>Every page concludes with PawHaven's trust architecture, displaying ethical standards, contact details, social channels, and terms of service.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/01-public-website/PUB-08-footer-trust-branding.png')}" alt="Footer">
                <div class="screenshot-caption">Figure 2.2: Comprehensive Brand Trust Footer with White Logo</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 3 -->
        <div>
            <h1>3. Browsing the Commercial Marketplace Catalog</h1>
            <p>The <strong>Explore Pets</strong> catalog (<code>/listings</code>) displays all staff-approved commercial pet listings across dogs, cats, birds, and small animals.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/01-public-website/PUB-02-marketplace-catalog.png')}" alt="Marketplace Catalog">
                <div class="screenshot-caption">Figure 3.1: Public Marketplace Pet Catalog with Category and Breed Filters</div>
            </div>

            <h2>Catalog Navigation Features</h2>
            <ul>
                <li><strong>Category Filtering:</strong> Quickly filter by Dogs, Cats, Birds, or Small Pets.</li>
                <li><strong>Price &amp; Location Tags:</strong> Transparent pricing with no hidden checkout surcharges.</li>
                <li><strong>Health Badges:</strong> Identifies vaccinated, microchipped, and health-certified pets.</li>
                <li><strong>Responsive Grid:</strong> Automatically reflows from 3-column desktop layout to intuitive single-column mobile view.</li>
            </ul>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 4 -->
        <div>
            <h1>4. Pet Details, Health Guarantees &amp; Purchase Requests</h1>
            <p>Selecting any pet listing opens its detailed profile (<code>/listings/{id}</code>), providing comprehensive photos, seller biography, and direct purchase inquiry capabilities.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/01-public-website/PUB-03-pet-details-view.png')}" alt="Pet Details">
                <div class="screenshot-caption">Figure 4.1: Detailed Pet Listing Page with Photo Gallery and Seller Bio</div>
            </div>

            <h2>Submitting a Commercial Purchase Request</h2>
            <ol>
                <li>Review the pet's age, vaccination status, and dietary requirements.</li>
                <li>Click <strong>Request to Purchase</strong> to open the inquiry form.</li>
                <li>Provide your contact telephone number, delivery or collection preferences, and any special care questions.</li>
                <li>Submit the request. The order is recorded in your Customer Dashboard and dispatched to the seller's queue.</li>
            </ol>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/08-marketplace-workflow/FLOW-01-submit-purchase-request.png')}" alt="Purchase Request">
                <div class="screenshot-caption">Figure 4.2: Direct Purchase Request Action Card on Pet Details Page</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 5 -->
        <div>
            <h1>5. Pet Supplies &amp; Nutrition E-Commerce Shop</h1>
            <p>PawHaven integrates a full-featured pet supplies e-commerce storefront (<code>#pet-shop</code>) offering high-grade pet nutrition, dental care treats, calming beds, and ergonomic harnesses.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/01-public-website/PUB-04-pet-shop-products.png')}" alt="Pet Shop">
                <div class="screenshot-caption">Figure 5.1: Pet Shop Supplies &amp; Premium Nutrition Product Grid</div>
            </div>

            <h2>Product Features</h2>
            <ul>
                <li><strong>Low Stock &amp; Inventory Badges:</strong> Transparent real-time inventory displays remaining item count.</li>
                <li><strong>Ingredient Transparency:</strong> Complete nutritional compositions and feeding instructions listed for foods.</li>
                <li><strong>Free Delivery Calculator:</strong> Dynamic progress bar showing threshold requirements for complimentary shipping.</li>
            </ul>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 6 -->
        <div>
            <h1>6. Community Adoption &amp; Ethical Rehoming Workflows</h1>
            <p>For individuals, foster homes, and rescue shelters seeking loving environments for rescued pets, PawHaven provides zero-fee <strong>Adoption &amp; Rescue</strong> workflows (<code>#adoption-rescue</code>).</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/01-public-website/PUB-05-adoption-rescue-highlights.png')}" alt="Adoption Highlights">
                <div class="screenshot-caption">Figure 6.1: Community Adoption &amp; Foster Rehoming Section</div>
            </div>

            <h2>Adoption Inquiries</h2>
            <p>Adoption posts are strictly non-commercial. Prospective adopters can contact foster families directly to schedule home visits or ask compatibility questions.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/09-adoption-workflow/FLOW-04-adoption-inquiry-view.png')}" alt="Adoption Inquiry">
                <div class="screenshot-caption">Figure 6.2: Public Adoption Inquiry Detail View</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 7 -->
        <div>
            <h1>7. Customer Portal: Order Tracking &amp; Saved Pets</h1>
            <p>Authenticated customers manage their entire PawHaven experience through the <strong>Customer Dashboard</strong> (<code>/dashboard</code>).</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/05-customer-portal/CUST-01-dashboard-overview.png')}" alt="Customer Dashboard">
                <div class="screenshot-caption">Figure 7.1: Customer Dashboard Overview with Active Order Counters</div>
            </div>

            <h2>Customer Portal Modules</h2>
            <ul>
                <li><strong>Purchase Requests Tab (<code>?tab=requests</code>):</strong> Track real-time progress on pet inquiries (Pending, Accepted, Shipped, Completed) with seller messaging.</li>
                <li><strong>Saved Pets Tab (<code>?tab=saved</code>):</strong> Bookmark listings to compare breeds or monitor price adjustments.</li>
                <li><strong>My Rehoming Posts (<code>?tab=posts</code>):</strong> Create, pause, or mark adoption listings as successfully adopted.</li>
            </ul>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/05-customer-portal/CUST-02-purchase-requests-tab.png')}" alt="Requests Tab">
                <div class="screenshot-caption">Figure 7.2: Purchase Requests Timeline Tab showing Status Progression</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 8 -->
        <div>
            <h1>8. Seller Portal: Merchant Onboarding &amp; Metric Cards</h1>
            <p>Verified pet breeders and licensed shopkeepers operate through the dedicated <strong>Seller Dashboard</strong> (<code>/seller/dashboard</code>).</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/06-seller-portal/SELL-01-dashboard-metrics.png')}" alt="Seller Metrics">
                <div class="screenshot-caption">Figure 8.1: Seller Dashboard with 5 Computed Real-Time Performance Cards</div>
            </div>

            <h2>The 5 Core Performance Metrics</h2>
            <table class="data-table">
                <thead>
                    <tr><th>Metric Card</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Total Revenue</strong></td><td>Accumulated sales volume across verified fulfilled orders</td></tr>
                    <tr><td><strong>Active Pet Listings</strong></td><td>Published commercial listings currently live in the marketplace</td></tr>
                    <tr><td><strong>Pending Requests</strong></td><td>New buyer purchase requests awaiting seller approval</td></tr>
                    <tr><td><strong>Awaiting Verification</strong></td><td>Orders requiring payment verification before fulfillment</td></tr>
                    <tr><td><strong>Catalog Views</strong></td><td>Total cumulative page views generated by seller listings</td></tr>
                </tbody>
            </table>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 9 -->
        <div>
            <h1>9. Seller Operations: Creating &amp; Managing Pet Listings</h1>
            <p>Under <code>/seller/listings</code>, sellers maintain their commercial pet catalog, adjust pricing, and toggle listings active or paused.</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/06-seller-portal/SELL-02-listings-management.png')}" alt="Listings Management">
                <div class="screenshot-caption">Figure 9.1: Seller Inventory Management with Status Pills and Action Dropdowns</div>
            </div>

            <h2>Creating a New Listing</h2>
            <p>Clicking <strong>Add New Pet Listing</strong> (<code>/seller/listings/create</code>) opens the listing creation builder:</p>
            <ul>
                <li><strong>Category &amp; Breed Selection:</strong> Automatically filters available breeds based on species.</li>
                <li><strong>Pricing &amp; Location:</strong> Sets price in local currency and specifies city/state.</li>
                <li><strong>Photo Upload:</strong> Upload high-resolution images showcasing the pet in natural lighting.</li>
                <li><strong>Health &amp; Welfare Notes:</strong> Record microchip numbers, vaccination history, and temperaments.</li>
            </ul>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/06-seller-portal/SELL-03-create-listing-form.png')}" alt="Create Listing Form">
                <div class="screenshot-caption">Figure 9.2: Multi-Attribute Pet Listing Creation Form</div>
            </div>
        </div>
        <div class="page-break"></div>

        <!-- SECTION 10 -->
        <div>
            <h1>10. Seller Operations: Incoming Purchase Requests &amp; Fulfillment</h1>
            <p>When prospective buyers submit purchase requests, they appear in the seller's <strong>Requests Queue</strong> (<code>/seller/requests</code>).</p>

            <div class="screenshot-container">
                <img src="${getBase64Image('docs/qa/screenshots/06-seller-portal/SELL-04-incoming-requests-queue.png')}" alt="Requests Queue">
                <div class="screenshot-caption">Figure 10.1: Incoming Buyer Purchase Requests Queue</div>
            </div>

            <h2>Processing Requests</h2>
            <ol>
                <li>Inspect buyer contact phone number, delivery address, and notes.</li>
                <li>Click <strong>Accept Request</strong> to confirm availability.</li>
                <li>Review submitted payment proofs (e.g. Bank Transfer reference or COD agreement) and click <strong>Verify Payment</strong>.</li>
                <li>Coordinate safe pet transport or in-person handover and mark the order <strong>Completed</strong>.</li>
            </ol>
        </div>
    </body>
    </html>
    `;
}

async function generatePdfs() {
    console.log('====================================================');
    console.log('PAWHAVEN PROFESSIONAL ILLUSTRATED PDF GENERATOR');
    console.log('====================================================\n');

    const browser = await puppeteer.launch({
        ...(CHROME_PATH ? { executablePath: CHROME_PATH } : {}),
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu']
    });

    try {
        const page = await browser.newPage();

        // 1. Owner & Administrator Guide
        console.log('Compiling PawHaven Owner & Administrator Guide PDF ...');
        const adminHtml = buildAdminGuideHtml();
        await page.setContent(adminHtml, { waitUntil: 'load', timeout: 60000 });

        const adminOutWebapp = path.join(WEBAPP_ROOT, 'docs', 'guides', 'PawHaven_Owner_Administrator_Guide.pdf');
        const adminOutRepo = path.join(REPO_ROOT, 'docs', 'guides', 'PawHaven_Owner_Administrator_Guide.pdf');

        await page.pdf({
            path: adminOutWebapp,
            format: 'A4',
            printBackground: true,
            margin: { top: '15mm', bottom: '15mm', left: '15mm', right: '15mm' }
        });
        fs.copyFileSync(adminOutWebapp, adminOutRepo);
        const adminStat = fs.statSync(adminOutWebapp);
        console.log(`[DONE] Administrator Guide PDF generated: ${adminStat.size} bytes`);

        // 2. User & Buyer Guide
        console.log('\nCompiling PawHaven User & Buyer Guide PDF ...');
        const userHtml = buildUserGuideHtml();
        await page.setContent(userHtml, { waitUntil: 'load', timeout: 60000 });

        const userOutWebapp = path.join(WEBAPP_ROOT, 'docs', 'guides', 'PawHaven_User_and_Buyer_Guide.pdf');
        const userOutRepo = path.join(REPO_ROOT, 'docs', 'guides', 'PawHaven_User_and_Buyer_Guide.pdf');

        await page.pdf({
            path: userOutWebapp,
            format: 'A4',
            printBackground: true,
            margin: { top: '15mm', bottom: '15mm', left: '15mm', right: '15mm' }
        });
        fs.copyFileSync(userOutWebapp, userOutRepo);
        const userStat = fs.statSync(userOutWebapp);
        console.log(`[DONE] User & Buyer Guide PDF generated: ${userStat.size} bytes`);

        // 3. Generate GUIDE_CONTENT.md
        console.log('\nGenerating GUIDE_CONTENT.md markdown transcript ...');
        let guideMd = `# PAWHAVEN — PROFESSIONAL DOCUMENTATION & USER GUIDES TRANSCRIPT\n\n`;
        guideMd += `This document contains the verified text content, instructional workflows, and structural outlines of both official illustrated PDF guides generated for PawHaven.\n\n`;
        guideMd += `---\n\n`;
        guideMd += `## Guide 1: PawHaven Owner & Administrator Operational Guide\n`;
        guideMd += `- **PDF Artifact:** [\`docs/guides/PawHaven_Owner_Administrator_Guide.pdf\`](file:///${adminOutWebapp.replace(/\\/g, '/')})\n`;
        guideMd += `- **Size:** ${adminStat.size} bytes\n`;
        guideMd += `- **Target Audience:** Source-code license buyers, platform owners, server administrators\n\n`;
        guideMd += `### Table of Contents\n`;
        guideMd += `1. Executive Overview & Architecture\n2. Installation, Environment & First-Time Bootstrap\n3. Dedicated Admin Console & Two-Factor Security (2FA)\n4. Integrated Admin Dashboard Overview\n5. Website Manager: Live Customization & Draft Publishing\n6. Brand Identity, Logos & Visual Palette Settings\n7. Marketplace Commercial Listing Moderation Queue\n8. Platform Order Management & Transaction Oversight\n9. Immutable Staff Audit Trails & Security Logs\n10. System Health Diagnostics, Backups & Production Hardening\n\n`;
        guideMd += `---\n\n`;
        guideMd += `## Guide 2: PawHaven User & Buyer Companion Guide\n`;
        guideMd += `- **PDF Artifact:** [\`docs/guides/PawHaven_User_and_Buyer_Guide.pdf\`](file:///${userOutWebapp.replace(/\\/g, '/')})\n`;
        guideMd += `- **Size:** ${userStat.size} bytes\n`;
        guideMd += `- **Target Audience:** Prospective pet parents, adopters, community rehomers, sellers/merchants\n\n`;
        guideMd += `### Table of Contents\n`;
        guideMd += `1. Welcome to PawHaven: Ethical Pet Care\n2. Exploring the Public Platform (Hero, Breeds & Trust)\n3. Browsing the Commercial Marketplace Catalog\n4. Pet Details, Health Guarantees & Purchase Requests\n5. Pet Supplies & Nutrition E-Commerce Shop\n6. Community Adoption & Ethical Rehoming Workflows\n7. Customer Portal: Order Tracking & Saved Pets\n8. Seller Portal: Merchant Onboarding & Metric Cards\n9. Seller Operations: Creating & Managing Pet Listings\n10. Seller Operations: Incoming Purchase Requests & Fulfillment\n\n`;

        fs.writeFileSync(path.join(WEBAPP_ROOT, 'docs', 'guides', 'GUIDE_CONTENT.md'), guideMd, 'utf8');
        fs.writeFileSync(path.join(REPO_ROOT, 'docs', 'guides', 'GUIDE_CONTENT.md'), guideMd, 'utf8');
        console.log('[DONE] GUIDE_CONTENT.md written successfully.');

    } finally {
        await browser.close();
        console.log('\nGenerator browser closed cleanly.');
    }
}

generatePdfs().catch(err => {
    console.error('PDF generation failure:', err);
    process.exit(1);
});
