<?php

require __DIR__ . '/../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Hash;

function benchmarkCost(int $rounds, int $iterations = 5): array
{
    $makeTimes = [];
    $checkTimes = [];
    $dummyPassword = 'PawHaven-Benchmark-Pass-' . bin2hex(random_bytes(4));

    for ($i = 0; $i < $iterations; $i++) {
        $t = hrtime(true);
        $hashed = Hash::make($dummyPassword, ['rounds' => $rounds]);
        $makeTimes[] = (hrtime(true) - $t) / 1e6;

        $t = hrtime(true);
        $valid = Hash::check($dummyPassword, $hashed);
        $checkTimes[] = (hrtime(true) - $t) / 1e6;
    }

    sort($makeTimes);
    sort($checkTimes);

    return [
        'rounds' => $rounds,
        'iterations' => $iterations,
        'make_median_ms' => round($makeTimes[(int)floor($iterations / 2)], 2),
        'make_min_ms' => round(min($makeTimes), 2),
        'make_max_ms' => round(max($makeTimes), 2),
        'check_median_ms' => round($checkTimes[(int)floor($iterations / 2)], 2),
        'check_min_ms' => round(min($checkTimes), 2),
        'check_max_ms' => round(max($checkTimes), 2),
    ];
}

$results = [
    'configured_rounds' => config('hashing.bcrypt.rounds', 12),
    'cost_12' => benchmarkCost(12, 5),
    'cost_10' => benchmarkCost(10, 5),
    'cost_4' => benchmarkCost(4, 5),
];

echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
