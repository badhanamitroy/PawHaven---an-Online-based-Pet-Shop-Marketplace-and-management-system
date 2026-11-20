import puppeteer from 'puppeteer-core';
import { CHROME_PATH, BASE_URL, qaCredentials } from './qa-config.mjs';

const credentials = qaCredentials('customer');

async function testItem() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox']
    });
    const page = await browser.newPage();
    await page.goto(`${BASE_URL}/login`);
    await page.type('input[name="email"]', credentials.email);
    await page.type('input[name="password"]', credentials.password);
    await Promise.all([
        page.waitForNavigation(),
        page.click('button[type="submit"]')
    ]);

    const items = await page.evaluate(() => {
        const els = Array.from(document.querySelectorAll('ui-sidebar a, ui-sidebar button, flux-sidebar-item'));
        return els.map(el => ({
            tag: el.tagName,
            text: el.innerText.trim(),
            classes: el.className,
            href: el.getAttribute('href'),
            dataCurrent: el.getAttribute('data-current'),
            outerHTML: el.outerHTML.slice(0, 300)
        }));
    });
    console.log(JSON.stringify(items, null, 2));
    await browser.close();
}

testItem().catch(console.error);
