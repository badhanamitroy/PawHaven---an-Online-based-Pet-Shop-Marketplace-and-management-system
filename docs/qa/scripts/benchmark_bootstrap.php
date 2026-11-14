<?php

$t0 = hrtime(true);

require __DIR__ . '/../../../vendor/autoload.php';
$tAutoload = hrtime(true);

$app = require __DIR__ . '/../../../bootstrap/app.php';
$tBootstrapApp = hrtime(true);

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$tBootstrapped = hrtime(true);

$dbStart = hrtime(true);
\Illuminate\Support\Facades\DB::select('select 1');
$tDb = hrtime(true);

echo json_encode([
    'autoload_ms' => round(($tAutoload - $t0) / 1e6, 2),
    'bootstrap_app_ms' => round(($tBootstrapApp - $tAutoload) / 1e6, 2),
    'kernel_bootstrap_ms' => round(($tBootstrapped - $tBootstrapApp) / 1e6, 2),
    'total_framework_boot_ms' => round(($tBootstrapped - $t0) / 1e6, 2),
    'db_select_1_ms' => round(($tDb - $dbStart) / 1e6, 2),
    'php_version' => PHP_VERSION,
]) . PHP_EOL;
