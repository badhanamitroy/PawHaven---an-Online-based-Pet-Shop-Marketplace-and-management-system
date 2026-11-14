import puppeteer from 'puppeteer-core';

async function testItem() {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new',
        args: ['--no-sandbox']
    });
    const page = await browser.newPage();
    await page.goto('http://127.0.0.1:8000/login');
    await page.type('input[name="email"]', 'customer@pawhaven.com');
    await page.type('input[name="password"]', 'CustomerPassword123!');
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
