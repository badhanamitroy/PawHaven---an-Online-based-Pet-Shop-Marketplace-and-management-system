<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

final class TestDatabaseGuardTest extends TestCase
{
    public function test_testing_sqlite_memory_configuration_is_accepted(): void
    {
        TestDatabaseGuard::assertSafeConfiguration($this->safeConfiguration(), $this->safeEnvironment());

        $this->assertSame('sqlite', $this->safeConfiguration()['database']['default']);
    }

    public function test_empty_database_url_is_accepted(): void
    {
        TestDatabaseGuard::assertSafeConfiguration($this->safeConfiguration(), $this->safeEnvironment());

        $this->assertSame([''], $this->safeEnvironment()['DB_URL']);
    }

    public function test_production_environment_is_rejected(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['application_environment'] = 'production';
        $configuration['resolved_environment'] = 'production';

        $this->assertRejected($configuration, $this->safeEnvironment(), 'APP_ENV');
    }

    public function test_missing_environment_is_rejected(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['application_environment'] = null;
        $configuration['resolved_environment'] = null;
        $environment = $this->safeEnvironment();
        $environment['APP_ENV'] = [];

        $this->assertRejected($configuration, $environment, 'APP_ENV');
    }

    public function test_mysql_is_rejected_by_the_functional_test_policy(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['database']['default'] = 'mysql';
        $environment = $this->safeEnvironment();
        $environment['DB_CONNECTION'] = ['mysql'];

        $this->assertRejected($configuration, $environment, 'DB_CONNECTION');
    }

    public function test_missing_database_name_is_rejected(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['database']['connections']['sqlite']['database'] = dirname(__DIR__, 2)
            .DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';
        $environment = $this->safeEnvironment();
        $environment['DB_DATABASE'] = [];

        $this->assertRejected($configuration, $environment, 'DB_DATABASE');
    }

    public function test_non_empty_database_url_is_rejected_without_echoing_it(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['database']['connections']['sqlite']['url'] = 'mysql://example.invalid/test';
        $environment = $this->safeEnvironment();
        $environment['DB_URL'] = ['mysql://example.invalid/test'];

        $exception = $this->assertRejected($configuration, $environment, 'DB_URL');

        $this->assertStringNotContainsString('example.invalid', $exception->getMessage());
    }

    public function test_active_business_sqlite_path_is_rejected_without_opening_it(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['database']['connections']['sqlite']['database'] = dirname(__DIR__, 2)
            .DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';

        $this->assertRejected($configuration, $this->safeEnvironment(), 'SQLite database');
    }

    public function test_file_backed_sqlite_outside_a_test_directory_is_rejected(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['database']['connections']['sqlite']['database'] = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'pawhaven-test-only.sqlite';

        $this->assertRejected($configuration, $this->safeEnvironment(), 'SQLite database');
    }

    public function test_unexpected_default_connection_is_rejected(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['database']['default'] = 'pgsql';

        $this->assertRejected($configuration, $this->safeEnvironment(), 'default database connection');
    }

    public function test_conflicting_process_environment_values_are_rejected(): void
    {
        $environment = $this->safeEnvironment();
        $environment['DB_CONNECTION'] = ['sqlite', 'mysql'];

        $this->assertRejected($this->safeConfiguration(), $environment, 'DB_CONNECTION');
    }

    public function test_unsafe_artisan_process_connection_is_rejected_before_it_is_overridden(): void
    {
        try {
            TestDatabaseGuard::assertSafeProcessOverrides(['DB_CONNECTION' => ['mysql']]);
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DB_CONNECTION', $exception->getMessage());

            return;
        }

        $this->fail('An unsafe inherited DB_CONNECTION was accepted.');
    }

    public function test_non_empty_artisan_process_database_url_is_rejected_without_echoing_it(): void
    {
        try {
            TestDatabaseGuard::assertSafeProcessOverrides(['DB_URL' => ['mysql://example.invalid/test']]);
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DB_URL', $exception->getMessage());
            $this->assertStringNotContainsString('example.invalid', $exception->getMessage());

            return;
        }

        $this->fail('A non-empty inherited DB_URL was accepted.');
    }

    public function test_cached_configuration_that_differs_from_safe_process_values_is_rejected(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['database']['connections']['sqlite']['database'] = dirname(__DIR__, 2)
            .DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';

        $this->assertRejected($configuration, $this->safeEnvironment(), 'SQLite database');
    }

    public function test_unexpected_host_configuration_is_rejected(): void
    {
        $configuration = $this->safeConfiguration();
        $configuration['database']['connections']['sqlite']['host'] = 'db.example.invalid';

        $exception = $this->assertRejected($configuration, $this->safeEnvironment(), 'SQLite host');

        $this->assertStringNotContainsString('db.example.invalid', $exception->getMessage());
    }

    public function test_rejection_does_not_open_or_create_the_rejected_database_path(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pawhaven-guard-'.bin2hex(random_bytes(8)).'.sqlite';
        $this->assertFileDoesNotExist($path);

        $configuration = $this->safeConfiguration();
        $configuration['database']['connections']['sqlite']['database'] = $path;

        $this->assertRejected($configuration, $this->safeEnvironment(), 'SQLite database');
        $this->assertFileDoesNotExist($path);
    }

    public function test_guard_is_selected_before_test_classes_and_refresh_database_traits(): void
    {
        $root = dirname(__DIR__, 2);
        $phpunit = file_get_contents($root.DIRECTORY_SEPARATOR.'phpunit.xml');
        $bootstrap = file_get_contents($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'bootstrap.php');

        $this->assertIsString($phpunit);
        $this->assertStringContainsString('bootstrap="tests/bootstrap.php"', $phpunit);
        $this->assertIsString($bootstrap);

        $configurationLoad = strpos($bootstrap, 'new LoadConfiguration');
        $guardCall = strpos($bootstrap, 'TestDatabaseGuard::assertSafeConfiguration');

        $this->assertNotFalse($configurationLoad);
        $this->assertNotFalse($guardCall);
        $this->assertLessThan($guardCall, $configurationLoad);
    }

    public function test_artisan_test_guard_runs_before_laravel_application_bootstrap(): void
    {
        $artisan = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'artisan');

        $this->assertIsString($artisan);

        $processGuard = strpos($artisan, 'assertSafeProcessOverrides');
        $environmentSetup = strpos($artisan, 'applyFunctionalTestEnvironment');
        $applicationBootstrap = strpos($artisan, "require_once __DIR__.'/bootstrap/app.php'");

        $this->assertNotFalse($processGuard);
        $this->assertNotFalse($environmentSetup);
        $this->assertNotFalse($applicationBootstrap);
        $this->assertLessThan($applicationBootstrap, $processGuard);
        $this->assertLessThan($applicationBootstrap, $environmentSetup);
    }

    public function test_artisan_effective_configuration_is_checked_before_service_provider_boot(): void
    {
        $bootstrap = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php');

        $this->assertIsString($bootstrap);

        $configurationHook = strpos($bootstrap, 'afterBootstrapping(');
        $guardCall = strpos($bootstrap, 'TestDatabaseGuard::assertSafeApplication');
        $loadConfiguration = strpos($bootstrap, 'LoadConfiguration::class');

        $this->assertNotFalse($configurationHook);
        $this->assertNotFalse($guardCall);
        $this->assertNotFalse($loadConfiguration);
        $this->assertLessThan($guardCall, $configurationHook);
        $this->assertLessThan($guardCall, $loadConfiguration);
    }

    public function test_artisan_test_detection_does_not_capture_other_commands(): void
    {
        $this->assertTrue(TestDatabaseGuard::isArtisanTestCommand(['artisan', 'test']));
        $this->assertTrue(TestDatabaseGuard::isArtisanTestCommand(['artisan', '--env=testing', 'test']));
        $this->assertTrue(TestDatabaseGuard::isArtisanTestCommand(['artisan', '--env', 'testing', 'test']));
        $this->assertFalse(TestDatabaseGuard::isArtisanTestCommand(['artisan', 'migrate', '--path=test']));
        $this->assertFalse(TestDatabaseGuard::isArtisanTestCommand(['phpunit', 'tests/Unit']));
    }

    public function test_direct_phpunit_process_is_detected_for_application_bootstrap_guard(): void
    {
        $this->assertTrue(TestDatabaseGuard::isPhpUnitProcess(['vendor/phpunit/phpunit/phpunit', 'tests/Feature']));
        $this->assertTrue(TestDatabaseGuard::isPhpUnitProcess(['phpunit', '--filter=ExampleTest']));
        $this->assertFalse(TestDatabaseGuard::isPhpUnitProcess(['artisan', 'route:list']));
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, list<mixed>>  $environment
     */
    private function assertRejected(array $configuration, array $environment, string $expectedSetting): RuntimeException
    {
        try {
            TestDatabaseGuard::assertSafeConfiguration($configuration, $environment);
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($expectedSetting, $exception->getMessage());

            return $exception;
        }

        $this->fail('The unsafe configuration was accepted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function safeConfiguration(): array
    {
        return [
            'application_environment' => 'testing',
            'resolved_environment' => 'testing',
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'url' => '',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function safeEnvironment(): array
    {
        return [
            'APP_ENV' => ['testing'],
            'DB_CONNECTION' => ['sqlite'],
            'DB_DATABASE' => [':memory:'],
            'DB_URL' => [''],
        ];
    }
}
