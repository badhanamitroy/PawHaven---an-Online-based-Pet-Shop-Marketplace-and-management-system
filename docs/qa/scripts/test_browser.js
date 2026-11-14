import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

async function run() {
    console.log('Launching Chrome from:', chromePath);
    const browser = await puppeteer.launch({
        executablePath: chromePath,
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1440,900'
        ]
    });

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1440, height: 900 });
        console.log('Navigating to http://127.0.0.1:8000/ ...');
        const res = await page.goto('http://127.0.0.1:8000/', { waitUntil: 'networkidle2', timeout: 30000 });
        console.log('Page loaded with status:', res.status());

        const title = await page.title();
        console.log('Page title:', title);

        const outDir = 'D:\\PawHaven\\PH Web Version\\WebApp\\docs\\qa\\screenshots\\00-environment';
        const outFile = path.join(outDir, 'ENV-01-app-boot-desktop.png');
        await page.screenshot({ path: outFile, fullPage: false });
        console.log('Screenshot saved to:', outFile);
    } finally {
        await browser.close();
        console.log('Browser closed cleanly.');
    }
}

run().catch(err => {
    console.error('Browser automation error:', err);
    process.exit(1);
});
