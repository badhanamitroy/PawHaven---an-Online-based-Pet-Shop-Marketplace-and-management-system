import puppeteer from 'puppeteer-core';

const baseUrl = process.env.PAWHAVEN_BASE_URL ?? 'http://127.0.0.1:8000';
const chromePath = process.env.PAWHAVEN_CHROME_PATH
    ?? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const coldTrialCount = Number(process.env.PAWHAVEN_COLD_TRIALS ?? 3);
const warmTrialCount = Number(process.env.PAWHAVEN_WARM_TRIALS ?? 5);
const routes = [
    { name: 'Home', path: '/' },
    { name: 'Login', path: '/login' },
    { name: 'Registration', path: '/register' },
    { name: 'Pet marketplace listing', path: '/listings' },
    { name: 'Adoption and rescue listing', path: '/listings?type=adoption' },
    { name: 'Customer dashboard (guest redirect)', path: '/dashboard' },
    { name: 'Seller dashboard (guest redirect)', path: '/seller/dashboard' },
    { name: 'Admin dashboard (guest redirect)', path: '/admin' },
    { name: 'Admin community (guest redirect)', path: '/admin/community' },
    { name: 'Admin login', path: '/admin/login' },
];

function median(values) {
    if (values.length === 0) {
        return null;
    }

    const sorted = [...values].sort((a, b) => a - b);
    const middle = Math.floor(sorted.length / 2);

    return sorted.length % 2 === 0
        ? Number(((sorted[middle - 1] + sorted[middle]) / 2).toFixed(1))
        : sorted[middle];
}

function summarize(samples) {
    const numericFields = ['ttfbMs', 'domContentLoadedMs', 'loadEventMs', 'lcpMs', 'cls'];
    const metrics = Object.fromEntries(numericFields.map(field => {
        const values = samples.map(sample => sample[field]).filter(value => value !== null);
        const sorted = [...values].sort((a, b) => a - b);

        return [field, {
            median: median(values),
            min: sorted.length ? sorted[0] : null,
            max: sorted.length ? sorted[sorted.length - 1] : null,
        }];
    }));

    const first = samples[0];

    return {
        metrics,
        finalUrl: first.finalUrl,
        status: first.status,
        failedRequests: [...new Map(samples.flatMap(sample => sample.failedRequests)
            .map(failure => [`${failure.status}:${failure.url}`, failure])).values()],
        consoleErrors: [...new Set(samples.flatMap(sample => sample.consoleErrors))],
        assets: first.assets,
        inpMs: null,
    };
}

async function findPublicListingDetailPath(browser) {
    const page = await browser.newPage();
    await page.goto(new URL('/listings', baseUrl).href, { waitUntil: 'domcontentloaded' });
    const path = await page.$$eval('a[href]', links => links
        .map(link => link.getAttribute('href'))
        .filter(Boolean)
        .find(href => {
            const url = new URL(href, window.location.origin);
            return url.pathname.startsWith('/listings/')
                && url.pathname.split('/').filter(Boolean).length === 2;
        }));
    await page.close();

    return path ? new URL(path, baseUrl).pathname : null;
}

async function measure(browser, context, url, disableCache) {
    const page = await context.newPage();
    if (disableCache) {
        await page.setCacheEnabled(false);
    }

    const failedRequests = [];
    const consoleErrors = [];
    page.on('requestfailed', request => {
        failedRequests.push({
            url: request.url(),
            status: null,
            error: request.failure()?.errorText ?? 'request failed',
        });
    });
    page.on('response', response => {
        if (response.status() >= 400) {
            failedRequests.push({ url: response.url(), status: response.status() });
        }
    });
    page.on('console', message => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    });

    const startedAt = process.hrtime.bigint();
    const response = await page.goto(url, { waitUntil: 'load', timeout: 45000 });
    const wallMs = Number(process.hrtime.bigint() - startedAt) / 1e6;
    const browserMetrics = await page.evaluate(() => {
        const navigation = performance.getEntriesByType('navigation')[0];
        const resources = performance.getEntriesByType('resource');
        const sameOriginResources = resources.filter(resource => resource.name.startsWith(location.origin));
        const resourceBytes = type => sameOriginResources
            .filter(resource => resource.initiatorType === type)
            .reduce((total, resource) => total + resource.transferSize, 0);
        const shifts = performance.getEntriesByType('layout-shift')
            .filter(entry => !entry.hadRecentInput);
        const largestContentfulPaint = performance.getEntriesByType('largest-contentful-paint').at(-1);

        return {
            ttfbMs: navigation ? navigation.responseStart - navigation.startTime : null,
            domContentLoadedMs: navigation ? navigation.domContentLoadedEventEnd - navigation.startTime : null,
            loadEventMs: navigation ? navigation.loadEventEnd - navigation.startTime : null,
            lcpMs: largestContentfulPaint?.startTime ?? null,
            cls: shifts.reduce((total, entry) => total + entry.value, 0),
            assets: {
                resourceCount: resources.length,
                sameOriginBytes: sameOriginResources.reduce((total, resource) => total + resource.transferSize, 0),
                javascriptBytes: resourceBytes('script'),
                cssBytes: resourceBytes('link'),
                imageBytes: resourceBytes('img'),
            },
        };
    });

    const result = {
        wallMs: Number(wallMs.toFixed(1)),
        ttfbMs: browserMetrics.ttfbMs === null ? null : Number(browserMetrics.ttfbMs.toFixed(1)),
        domContentLoadedMs: browserMetrics.domContentLoadedMs === null ? null : Number(browserMetrics.domContentLoadedMs.toFixed(1)),
        loadEventMs: browserMetrics.loadEventMs === null ? null : Number(browserMetrics.loadEventMs.toFixed(1)),
        lcpMs: browserMetrics.lcpMs === null ? null : Number(browserMetrics.lcpMs.toFixed(1)),
        cls: Number(browserMetrics.cls.toFixed(4)),
        assets: browserMetrics.assets,
        status: response?.status() ?? null,
        finalUrl: page.url(),
        failedRequests,
        consoleErrors,
    };

    await page.close();

    return result;
}

async function main() {
    const browser = await puppeteer.launch({
        executablePath: chromePath,
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu'],
    });

    try {
        const detailPath = await findPublicListingDetailPath(browser);
        if (detailPath) {
            routes.splice(5, 0, { name: 'Public pet listing detail', path: detailPath });
        }

        const report = {
            baseUrl,
            coldDefinition: 'new browser context with cache disabled; application/server cache is not reset',
            warmDefinition: 'shared browser context with cache enabled',
            authenticatedRoutes: 'not logged in; protected pages are measured only through their guest redirect',
            productDetailRoute: 'not present in the current public route table',
            interactionToNextPaintMs: null,
            routes: {},
        };

        for (const route of routes) {
            const url = new URL(route.path, baseUrl).href;
            const coldSamples = [];
            for (let trial = 0; trial < coldTrialCount; trial++) {
                const context = await browser.createBrowserContext();
                coldSamples.push(await measure(browser, context, url, true));
                await context.close();
            }

            const warmContext = browser.defaultBrowserContext();
            const warmSamples = [];
            for (let trial = 0; trial < warmTrialCount; trial++) {
                warmSamples.push(await measure(browser, warmContext, url, false));
            }

            report.routes[route.name] = {
                path: route.path,
                cold: summarize(coldSamples),
                warm: summarize(warmSamples),
                coldWallMs: {
                    median: median(coldSamples.map(sample => sample.wallMs)),
                    min: Math.min(...coldSamples.map(sample => sample.wallMs)),
                    max: Math.max(...coldSamples.map(sample => sample.wallMs)),
                },
                warmWallMs: {
                    median: median(warmSamples.map(sample => sample.wallMs)),
                    min: Math.min(...warmSamples.map(sample => sample.wallMs)),
                    max: Math.max(...warmSamples.map(sample => sample.wallMs)),
                },
            };
        }

        console.log(JSON.stringify(report, null, 2));
    } finally {
        await browser.close();
    }
}

main().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
