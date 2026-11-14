import http from 'http';

const ENDPOINTS = [
    { name: 'Home', path: '/' },
    { name: 'Login', path: '/login' },
    { name: 'Register', path: '/register' },
    { name: 'Admin Login', path: '/admin/login' }
];

function requestTiming(path) {
    return new Promise((resolve, reject) => {
        const tStart = process.hrtime.bigint();
        let tSocket, tFirstByte, tEnd;

        const req = http.request({
            hostname: '127.0.0.1',
            port: 8000,
            path: path,
            method: 'GET',
            headers: {
                'User-Agent': 'PawHaven-Benchmark/1.0',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
            }
        }, (res) => {
            res.once('data', () => {
                tFirstByte = process.hrtime.bigint();
            });

            let body = '';
            res.on('data', chunk => { body += chunk; });
            res.on('end', () => {
                tEnd = process.hrtime.bigint();
                const totalMs = Number(tEnd - tStart) / 1e6;
                const socketMs = tSocket ? Number(tSocket - tStart) / 1e6 : 0;
                const ttfbMs = tFirstByte ? Number(tFirstByte - tStart) / 1e6 : totalMs;

                resolve({
                    status: res.statusCode,
                    bodyLength: body.length,
                    connectMs: Math.round(socketMs * 100) / 100,
                    ttfbMs: Math.round(ttfbMs * 100) / 100,
                    totalMs: Math.round(totalMs * 100) / 100
                });
            });
        });

        req.on('socket', socket => {
            socket.on('connect', () => {
                tSocket = process.hrtime.bigint();
            });
        });

        req.on('error', reject);
        req.end();
    });
}

function stats(arr) {
    const sorted = [...arr].sort((a, b) => a - b);
    const min = sorted[0];
    const max = sorted[sorted.length - 1];
    const median = sorted[Math.floor(sorted.length / 2)];
    return { median, min, max };
}

async function run() {
    console.log('--- Measuring Raw HTTP Server Timing ---');
    const allResults = {};

    for (const ep of ENDPOINTS) {
        console.log(`Testing endpoint: ${ep.name} (${ep.path})`);
        const timings = [];

        // Run 7 trials per endpoint
        for (let i = 0; i < 7; i++) {
            const res = await requestTiming(ep.path);
            timings.push(res);
            // small delay between warm requests
            await new Promise(r => setTimeout(r, 100));
        }

        const ttfbList = timings.map(t => t.ttfbMs);
        const totalList = timings.map(t => t.totalMs);

        allResults[ep.name] = {
            path: ep.path,
            first_request_ttfb: timings[0].ttfbMs,
            first_request_total: timings[0].totalMs,
            warm_ttfb: stats(ttfbList.slice(1)),
            warm_total: stats(totalList.slice(1)),
            all_trials: timings
        };
    }

    console.log(JSON.stringify(allResults, null, 2));
}

run().catch(console.error);
