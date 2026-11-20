import puppeteer from 'puppeteer-core';
import { BASE_URL, CHROME_PATH, qaCredentials } from './qa-config.mjs';

const credentials = qaCredentials('admin');

async function testAdmin() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu']
    });

    try {
        const page = await browser.newPage();
        await page.goto(`${BASE_URL}/admin/login`, { waitUntil: 'networkidle2' });
        console.log('On page:', page.url());

        await page.type('input[name="email"]', credentials.email);
        await page.type('input[name="password"]', credentials.password);
        
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2' }),
            page.click('button[type="submit"]')
        ]);

        console.log('After submit URL:', page.url());
        const title = await page.title();
        console.log('Title:', title);
        
        const errors = await page.$$eval('.text-rose-800, [role="alert"]', els => els.map(e => e.innerText));
        console.log('Errors:', errors);
    } finally {
        await browser.close();
    }
}

testAdmin().catch(console.error);
