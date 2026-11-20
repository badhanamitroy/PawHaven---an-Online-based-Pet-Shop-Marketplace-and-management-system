import puppeteer from 'puppeteer-core';
import path from 'path';
import { BASE_URL, CHROME_PATH, qaCredentials } from './qa-config.mjs';

const credentials = qaCredentials('customer');

async function capture() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1440, height: 900 });

        console.log('Navigating to login...');
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });

        await page.type('input[name="email"]', credentials.email);
        await page.type('input[name="password"]', credentials.password);

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]);

        console.log('Logged in, current URL:', page.url());
        if (!page.url().includes('/dashboard')) {
            await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle2' });
        }

        const outDesktop = 'C:\\Users\\Dell\\.gemini\\antigravity\\brain\\e09e10c7-1c9f-40bb-9012-7e9a39887894\\scratch\\customer_dashboard_before_desktop.png';
        await page.screenshot({ path: outDesktop, fullPage: true });
        console.log('Saved desktop screenshot:', outDesktop);

        await page.setViewport({ width: 375, height: 812, isMobile: true, hasTouch: true });
        await new Promise(r => setTimeout(r, 500));
        const outMobile = 'C:\\Users\\Dell\\.gemini\\antigravity\\brain\\e09e10c7-1c9f-40bb-9012-7e9a39887894\\scratch\\customer_dashboard_before_mobile.png';
        await page.screenshot({ path: outMobile, fullPage: true });
        console.log('Saved mobile screenshot:', outMobile);

    } finally {
        await browser.close();
    }
}

capture().catch(err => {
    console.error(err);
    process.exit(1);
});
