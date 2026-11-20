import puppeteer from 'puppeteer-core';
import path from 'path';
import { BASE_URL, CHROME_PATH, qaCredentials, WEBAPP_ROOT } from './qa-config.mjs';

const credentials = qaCredentials('customer');

async function main() {
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

        await new Promise(r => setTimeout(r, 1000));

        // Evaluate styles
        const styles = await page.evaluate(() => {
            const body = document.body;
            const sidebar = document.querySelector('ui-sidebar') || document.querySelector('flux-sidebar') || document.querySelector('aside');
            const main = document.querySelector('ui-main') || document.querySelector('flux-main') || document.querySelector('main');
            const activeNav = document.querySelector('[data-current="true"]') || document.querySelector('[data-current="data-current"]') || document.querySelector('[data-current]');

            return {
                htmlClasses: document.documentElement.className,
                bodyBg: window.getComputedStyle(body).backgroundColor,
                sidebarBg: sidebar ? window.getComputedStyle(sidebar).backgroundColor : 'none',
                mainBg: main ? window.getComputedStyle(main).backgroundColor : 'none',
                activeNavBg: activeNav ? window.getComputedStyle(activeNav).backgroundColor : 'none',
                activeNavColor: activeNav ? window.getComputedStyle(activeNav).color : 'none',
                activeNavBorderLeft: activeNav ? window.getComputedStyle(activeNav).borderLeft : 'none'
            };
        });

        console.log('Styles:', JSON.stringify(styles, null, 2));

        const outDesktop = 'C:\\Users\\Dell\\.gemini\\antigravity\\brain\\e09e10c7-1c9f-40bb-9012-7e9a39887894\\scratch\\customer_dashboard_after_desktop.png';
        await page.screenshot({ path: outDesktop, fullPage: true });
        console.log('Saved desktop screenshot:', outDesktop);

        const outQADesktop = path.join(WEBAPP_ROOT, 'docs', 'qa', 'screenshots', '05-customer-portal', 'CUST-01-dashboard-overview.png');
        await page.screenshot({ path: outQADesktop, fullPage: true });
        console.log('Updated QA screenshot:', outQADesktop);

        // Mobile viewport
        await page.setViewport({ width: 375, height: 812, isMobile: true, hasTouch: true });
        await new Promise(r => setTimeout(r, 500));
        const outMobile = 'C:\\Users\\Dell\\.gemini\\antigravity\\brain\\e09e10c7-1c9f-40bb-9012-7e9a39887894\\scratch\\customer_dashboard_after_mobile.png';
        await page.screenshot({ path: outMobile, fullPage: true });
        console.log('Saved mobile screenshot:', outMobile);

    } finally {
        await browser.close();
    }
}

main().catch(err => {
    console.error(err);
    process.exit(1);
});
