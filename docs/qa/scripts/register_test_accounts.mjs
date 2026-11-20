import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';
import { BASE_URL, CHROME_PATH, qaCredentials, REPO_ROOT, WEBAPP_ROOT } from './qa-config.mjs';

const customerCredentials = qaCredentials('customer');
const sellerCredentials = qaCredentials('seller');

async function capture(page, category, filename, id, description) {
    const webappDir = path.join(WEBAPP_ROOT, 'docs', 'qa', 'screenshots', category);
    const repoDir = path.join(REPO_ROOT, 'docs', 'qa', 'screenshots', category);

    const webappFile = path.join(webappDir, filename);
    const repoFile = path.join(repoDir, filename);

    await page.screenshot({ path: webappFile, fullPage: false });
    fs.copyFileSync(webappFile, repoFile);

    const stat = fs.statSync(webappFile);
    console.log(`[PASS] Captured ${id}: ${filename} (${stat.size} bytes)`);
}

async function run() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu', '--window-size=1440,900']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1440, height: 900 });

        // 1. Customer registration using locally supplied QA credentials.
        console.log('Registering the configured QA customer account ...');
        await page.goto(`${BASE_URL}/register?role=customer`, { waitUntil: 'networkidle2' });
        await page.type('input[name="name"]', 'QA Customer');
        await page.type('input[name="email"]', customerCredentials.email);
        await page.type('input[name="password"]', customerCredentials.password);
        await page.type('input[name="password_confirmation"]', customerCredentials.password);
        
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]);

        console.log('Customer registration landed on:', page.url());
        await capture(page, '02-auth-customer', 'AUTH-CUST-02-email-verify-checkpoint.png', 'AUTH-CUST-02', 'Customer mandatory email verification barrier after registration');

        // Logout
        await page.evaluate(() => {
            const form = document.querySelector('form[action*="logout"]');
            if (form) form.submit();
        });
        await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});

        // 2. Seller registration using locally supplied QA credentials.
        console.log('Registering the configured QA seller account ...');
        await page.goto(`${BASE_URL}/register?role=seller`, { waitUntil: 'networkidle2' });
        await page.type('input[name="name"]', 'QA Seller');
        await page.type('input[name="email"]', sellerCredentials.email);
        await page.type('input[name="password"]', sellerCredentials.password);
        await page.type('input[name="password_confirmation"]', sellerCredentials.password);

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]);

        console.log('Seller registration landed on:', page.url());
        await capture(page, '03-auth-seller', 'AUTH-SELL-02-email-verify-checkpoint.png', 'AUTH-SELL-02', 'Seller mandatory email verification barrier after registration');

    } finally {
        await browser.close();
    }
}

run().catch(console.error);
