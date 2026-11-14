import puppeteer from 'puppeteer-core';

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = 'http://127.0.0.1:8000';

const PAGES = [
    { name: 'Home', path: '/' },
    { name: 'Login', path: '/login' },
    { name: 'Register', path: '/register' },
    { name: 'Admin Login', path: '/admin/login' }
];

async function measurePage(browser, pageUrl, isCold = false) {
    const context = isCold ? await browser.createBrowserContext() : browser.defaultBrowserContext();
    const page = await context.newPage();

    if (isCold) {
        await page.setCacheEnabled(false);
    }

    const failedRequests = [];
    const slowRequests = [];
    const consoleErrors = [];

    page.on('requestfailed', req => {
        failedRequests.push({ url: req.url(), failure: req.failure()?.errorText });
    });

    page.on('response', res => {
        if (res.status() >= 400) {
            failedRequests.push({ url: res.url(), status: res.status() });
        }
    });

    page.on('console', msg => {
        if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
        }
    });

    const tStart = Date.now();
    await page.goto(pageUrl, { waitUntil: 'load', timeout: 45000 });
    const wallMs = Date.now() - tStart;

    const perfData = await page.evaluate(() => {
        const nav = performance.getEntriesByType('navigation')[0];
        const resources = performance.getEntriesByType('resource').map(r => ({
            name: r.name.split('/').pop().split('?')[0],
            url: r.name,
            initiatorType: r.initiatorType,
            durationMs: Math.round(r.duration * 100) / 100,
            transferSize: r.transferSize
        }));

        return {
            ttfbMs: Math.round((nav.responseStart - nav.startTime) * 100) / 100,
            domContentLoadedMs: Math.round((nav.domContentLoadedEventEnd - nav.startTime) * 100) / 100,
            loadEventMs: Math.round((nav.loadEventEnd - nav.startTime) * 100) / 100,
            resources: resources
        };
    });

    await page.close();
    if (isCold) {
        await context.close();
    }

    return {
        wallMs,
        ...perfData,
        failedRequests,
        consoleErrors
    };
}

function stats(arr) {
    const sorted = [...arr].sort((a, b) => a - b);
    return {
        median: sorted[Math.floor(sorted.length / 2)],
        min: sorted[0],
        max: sorted[sorted.length - 1]
    };
}

async function main() {
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu']
    });

    try {
        console.log('=== Benchmarking Browser Navigation ===');
        const report = {};

        for (const p of PAGES) {
            const url = `${BASE_URL}${p.path}`;
            console.log(`\nTesting ${p.name} (${url})`);

            // 1. Cold navigation (cache disabled, fresh context)
            console.log('  Running 3 cold trials...');
            const coldTrials = [];
            for (let i = 0; i < 3; i++) {
                const res = await measurePage(browser, url, true);
                coldTrials.push(res);
                console.log(`    Trial ${i + 1}: TTFB=${res.ttfbMs}ms, DCL=${res.domContentLoadedMs}ms, Load=${res.loadEventMs}ms, Wall=${res.wallMs}ms`);
            }

            // 2. Warm navigation (cache enabled, shared context)
            console.log('  Running 5 warm trials...');
            const warmTrials = [];
            for (let i = 0; i < 5; i++) {
                const res = await measurePage(browser, url, false);
                warmTrials.push(res);
                console.log(`    Trial ${i + 1}: TTFB=${res.ttfbMs}ms, DCL=${res.domContentLoadedMs}ms, Load=${res.loadEventMs}ms, Wall=${res.wallMs}ms`);
            }

            report[p.name] = {
                path: p.path,
                cold: {
                    ttfb: stats(coldTrials.map(t => t.ttfbMs)),
                    domContentLoaded: stats(coldTrials.map(t => t.domContentLoadedMs)),
                    loadEvent: stats(coldTrials.map(t => t.loadEventMs)),
                    wallTime: stats(coldTrials.map(t => t.wallMs)),
                    sample_resources: coldTrials[0].resources,
                    failedRequests: coldTrials.flatMap(t => t.failedRequests),
                    consoleErrors: coldTrials.flatMap(t => t.consoleErrors)
                },
                warm: {
                    ttfb: stats(warmTrials.map(t => t.ttfbMs)),
                    domContentLoaded: stats(warmTrials.map(t => t.domContentLoadedMs)),
                    loadEvent: stats(warmTrials.map(t => t.loadEventMs)),
                    wallTime: stats(warmTrials.map(t => t.wallMs)),
                    failedRequests: warmTrials.flatMap(t => t.failedRequests),
                    consoleErrors: warmTrials.flatMap(t => t.consoleErrors)
                }
            };
        }

        console.log('\n=== Summary Results ===');
        console.log(JSON.stringify(report, null, 2));

    } finally {
        await browser.close();
    }
}

main().catch(console.error);
