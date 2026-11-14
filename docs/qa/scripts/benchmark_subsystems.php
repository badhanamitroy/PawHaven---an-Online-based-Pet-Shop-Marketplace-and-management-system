<?php

require __DIR__ . '/../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use App\Facades\Settings;

$results = [];

// 1. Database Connection & Query Latency
$t0 = hrtime(true);
DB::select('SELECT 1');
$dbConnectMs = (hrtime(true) - $t0) / 1e6;

$warmDbQueryTimes = [];
for ($i = 0; $i < 10; $i++) {
    $t = hrtime(true);
    DB::select('SELECT 1');
    $warmDbQueryTimes[] = round((hrtime(true) - $t) / 1e6, 3);
}

// 2. Settings Load (Cold query vs warm cache)
\App\Services\SettingsService::resetCache();
$t = hrtime(true);
$settings = Settings::all();
$coldSettingsMs = (hrtime(true) - $t) / 1e6;

$t = hrtime(true);
$settingsWarm = Settings::all();
$warmSettingsMs = (hrtime(true) - $t) / 1e6;

// 3. Cache read/write
$cacheKey = 'perf_test_' . uniqid();
$t = hrtime(true);
Cache::put($cacheKey, 'test_val', 60);
$cacheWriteMs = (hrtime(true) - $t) / 1e6;

$t = hrtime(true);
$cacheVal = Cache::get($cacheKey);
$cacheReadMs = (hrtime(true) - $t) / 1e6;
Cache::forget($cacheKey);

// 4. Session read/write (database session table)
$sessionId = 'test_session_' . bin2hex(random_bytes(16));
$t = hrtime(true);
DB::table('sessions')->insert([
    'id' => $sessionId,
    'user_id' => null,
    'ip_address' => '127.0.0.1',
    'user_agent' => 'perf_test',
    'payload' => serialize(['test' => 123]),
    'last_activity' => time(),
]);
$sessionWriteMs = (hrtime(true) - $t) / 1e6;

$t = hrtime(true);
$sessionRow = DB::table('sessions')->where('id', $sessionId)->first();
$sessionReadMs = (hrtime(true) - $t) / 1e6;
DB::table('sessions')->where('id', $sessionId)->delete();

// Output JSON
echo json_encode([
    'db_first_connect_select1_ms' => round($dbConnectMs, 2),
    'db_warm_select1_median_ms' => $warmDbQueryTimes[5],
    'db_warm_select1_range_ms' => min($warmDbQueryTimes) . ' - ' . max($warmDbQueryTimes),
    'cold_settings_load_ms' => round($coldSettingsMs, 2),
    'warm_settings_load_ms' => round($warmSettingsMs, 3),
    'cache_write_ms' => round($cacheWriteMs, 2),
    'cache_read_ms' => round($cacheReadMs, 2),
    'session_write_ms' => round($sessionWriteMs, 2),
    'session_read_ms' => round($sessionReadMs, 2),
], JSON_PRETTY_PRINT) . PHP_EOL;
