import puppeteer from 'puppeteer-core';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';

async function verifySeller() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1440, height: 900 });

        await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
        await page.type('input[name="email"]', 'seller@pawhaven.com');
        await page.type('input[name="password"]', 'SellerPassword123!');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]);

        console.log('Seller logged in, URL:', page.url());

        const sellerStyles = await page.evaluate(() => {
            return {
                htmlClass: document.documentElement.className,
                bodyBg: window.getComputedStyle(document.body).backgroundColor,
                sidebarBg: document.querySelector('ui-sidebar') ? window.getComputedStyle(document.querySelector('ui-sidebar')).backgroundColor : 'none',
            };
        });

        console.log('Seller Styles:', JSON.stringify(sellerStyles, null, 2));

        const outSeller = 'C:\\Users\\Dell\\.gemini\\antigravity\\brain\\e09e10c7-1c9f-40bb-9012-7e9a39887894\\scratch\\seller_dashboard_check.png';
        await page.screenshot({ path: outSeller, fullPage: true });
        console.log('Saved seller screenshot:', outSeller);
    } finally {
        await browser.close();
    }
}

verifySeller().catch(console.error);
