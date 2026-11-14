import puppeteer from 'puppeteer-core';
import path from 'path';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';

async function updateQAScreenshots() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1440, height: 900 });

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

        await new Promise(r => setTimeout(r, 1000));

        const baseDir = 'D:\\PawHaven\\PH Web Version\\WebApp\\docs\\qa\\screenshots\\05-customer-portal';

        // CUST-01: Overview
        await page.screenshot({ path: path.join(baseDir, 'CUST-01-dashboard-overview.png'), fullPage: false });
        console.log('Saved CUST-01');

        // Scroll to Orders
        await page.evaluate(() => {
            const el = Array.from(document.querySelectorAll('h2')).find(h => h.innerText.includes('Purchase Requests'));
            if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });
        });
        await new Promise(r => setTimeout(r, 300));
        await page.screenshot({ path: path.join(baseDir, 'CUST-02-purchase-requests-tab.png'), fullPage: false });
        console.log('Saved CUST-02');

        // Scroll to Saved Pets
        await page.evaluate(() => {
            const el = Array.from(document.querySelectorAll('h2')).find(h => h.innerText.includes('Saved Pets'));
            if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });
        });
        await new Promise(r => setTimeout(r, 300));
        await page.screenshot({ path: path.join(baseDir, 'CUST-04-saved-listings-tab.png'), fullPage: false });
        console.log('Saved CUST-04');

        // Scroll to Rehoming Posts
        await page.evaluate(() => {
            const el = Array.from(document.querySelectorAll('h2')).find(h => h.innerText.includes('Rehoming'));
            if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });
        });
        await new Promise(r => setTimeout(r, 300));
        await page.screenshot({ path: path.join(baseDir, 'CUST-05-rehoming-posts-tab.png'), fullPage: false });
        console.log('Saved CUST-05');

    } finally {
        await browser.close();
    }
}

updateQAScreenshots().catch(console.error);
