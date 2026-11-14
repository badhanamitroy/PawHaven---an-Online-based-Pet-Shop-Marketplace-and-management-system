import puppeteer from 'puppeteer-core';

async function main() {
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

    const info = await page.evaluate(() => {
        const body = document.body;
        const main = document.querySelector('ui-main') || document.querySelector('flux-main') || document.querySelector('main');
        const sidebar = document.querySelector('ui-sidebar') || document.querySelector('flux-sidebar') || document.querySelector('aside');
        const h1 = document.querySelector('h1');
        const activeNav = document.querySelector('[data-current="true"]') || document.querySelector('[aria-current="page"]');

        return {
            htmlClass: document.documentElement.className,
            bodyBg: window.getComputedStyle(body).backgroundColor,
            mainBg: main ? window.getComputedStyle(main).backgroundColor : 'none',
            mainTag: main ? main.tagName : 'none',
            sidebarBg: sidebar ? window.getComputedStyle(sidebar).backgroundColor : 'none',
            sidebarTag: sidebar ? sidebar.tagName : 'none',
            sidebarClass: sidebar ? sidebar.className : '',
            h1Color: h1 ? window.getComputedStyle(h1).color : 'none',
            activeNavBg: activeNav ? window.getComputedStyle(activeNav).backgroundColor : 'none',
            activeNavColor: activeNav ? window.getComputedStyle(activeNav).color : 'none',
        };
    });

    console.log(JSON.stringify(info, null, 2));
    await browser.close();
}

main().catch(console.error);
