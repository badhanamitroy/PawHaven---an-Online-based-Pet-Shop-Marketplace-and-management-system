import puppeteer from 'puppeteer-core';

async function testTheme() {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new',
        args: ['--no-sandbox']
    });
    const page = await browser.newPage();
    await page.goto('http://127.0.0.1:8000/login');
    const classes = await page.evaluate(() => document.documentElement.className);
    console.log('Login HTML classes:', classes);
    await browser.close();
}

testTheme().catch(console.error);
