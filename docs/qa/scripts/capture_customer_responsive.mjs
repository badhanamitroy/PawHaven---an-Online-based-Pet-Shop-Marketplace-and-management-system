import puppeteer from 'puppeteer-core';
import path from 'path';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';

async function captureResponsive() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', 'customer@pawhaven.com');
        await page.type('input[name="password"]', 'CustomerPassword123!');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]);

        if (!page.url().includes('/dashboard')) {
            await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle2' });
        }

        const outDir = 'D:\\PawHaven\\PH Web Version\\WebApp\\docs\\qa\\screenshots\\11-responsive-views';

        // Tablet 768x1024
        await page.setViewport({ width: 768, height: 1024 });
        await new Promise(r => setTimeout(r, 400));
        await page.screenshot({ path: path.join(outDir, 'RESP-06-customer-tablet-768.png'), fullPage: false });
        console.log('Saved RESP-06-customer-tablet-768.png');

        // Mobile 375x812
        await page.setViewport({ width: 375, height: 812, isMobile: true, hasTouch: true });
        await new Promise(r => setTimeout(r, 400));
        await page.screenshot({ path: path.join(outDir, 'RESP-07-customer-mobile-375.png'), fullPage: false });
        console.log('Saved RESP-07-customer-mobile-375.png');

    } finally {
        await browser.close();
    }
}

captureResponsive().catch(console.error);
