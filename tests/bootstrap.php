<?php

declare(strict_types=1);

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Tests\Support\TestDatabaseGuard;

$basePath = dirname(__DIR__);

require_once $basePath.'/vendor/autoload.php';

/*
 * Load only environment and configuration bootstrap stages. Do not register or
 * boot service providers here: the guard must run before application services,
 * test classes, or database traits can open a connection.
 */
$application = require $basePath.'/bootstrap/app.php';

(new LoadEnvironmentVariables)->bootstrap($application);
(new LoadConfiguration)->bootstrap($application);

TestDatabaseGuard::assertSafeApplication($application);
