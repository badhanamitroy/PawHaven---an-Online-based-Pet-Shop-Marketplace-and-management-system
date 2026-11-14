import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';
const WEBAPP_ROOT = 'D:\\PawHaven\\PH Web Version\\WebApp';
const REPO_ROOT = 'D:\\PawHaven';

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

        // 1. Customer Registration with badhancse28@gmail.com
        console.log('Registering Customer badhancse28@gmail.com ...');
        await page.goto(`${BASE_URL}/register?role=customer`, { waitUntil: 'networkidle2' });
        await page.type('input[name="name"]', 'Badhan Customer');
        await page.type('input[name="email"]', 'badhancse28@gmail.com');
        await page.type('input[name="password"]', 'CustomerPassword123!');
        await page.type('input[name="password_confirmation"]', 'CustomerPassword123!');
        
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

        // 2. Seller Registration with badhanamitroy2nd@gmail.com
        console.log('Registering Seller badhanamitroy2nd@gmail.com ...');
        await page.goto(`${BASE_URL}/register?role=seller`, { waitUntil: 'networkidle2' });
        await page.type('input[name="name"]', 'Badhan Seller');
        await page.type('input[name="email"]', 'badhanamitroy2nd@gmail.com');
        await page.type('input[name="password"]', 'SellerPassword123!');
        await page.type('input[name="password_confirmation"]', 'SellerPassword123!');

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
