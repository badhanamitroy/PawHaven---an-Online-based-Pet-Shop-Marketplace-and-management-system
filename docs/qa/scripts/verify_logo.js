import puppeteer from 'puppeteer-core';

const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

async function checkLogo() {
    const browser = await puppeteer.launch({
        executablePath: chromePath,
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu']
    });

    try {
        const page = await browser.newPage();
        await page.goto('http://127.0.0.1:8000/', { waitUntil: 'networkidle2' });

        const headerLogo = await page.evaluate(() => {
            const img = document.querySelector('header a img');
            return img ? {
                src: img.src,
                complete: img.complete,
                naturalWidth: img.naturalWidth,
                naturalHeight: img.naturalHeight
            } : null;
        });

        const footerLogo = await page.evaluate(() => {
            const img = document.querySelector('footer a img');
            return img ? {
                src: img.src,
                complete: img.complete,
                naturalWidth: img.naturalWidth,
                naturalHeight: img.naturalHeight
            } : null;
        });

        console.log('Header logo info:', JSON.stringify(headerLogo, null, 2));
        console.log('Footer logo info:', JSON.stringify(footerLogo, null, 2));
    } finally {
        await browser.close();
    }
}

checkLogo().catch(console.error);
