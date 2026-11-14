import http from 'http';

function get(path) {
    return new Promise((resolve) => {
        const t0 = Date.now();
        http.get(`http://127.0.0.1:8000${path}`, (res) => {
            res.on('data', () => {});
            res.on('end', () => {
                resolve({ path, status: res.statusCode, elapsed: Date.now() - t0 });
            });
        }).on('error', (err) => {
            resolve({ path, error: err.message, elapsed: Date.now() - t0 });
        });
    });
}

async function testConcurrency() {
    console.log('Sending 6 parallel requests to php artisan serve:');
    const t0 = Date.now();
    const results = await Promise.all([
        get('/'),
        get('/login'),
        get('/register'),
        get('/admin/login'),
        get('/build/assets/app-apqfmIIB.css'),
        get('/images/pawhaven-logo-green.png')
    ]);
    const totalWall = Date.now() - t0;
    console.log('Total wall time for 6 parallel requests:', totalWall, 'ms');
    console.log('Individual results:', results);
}

testConcurrency();
