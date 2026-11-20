<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

/**
 * Fail-closed database policy for PawHeaven's default PHPUnit functional suite.
 *
 * This class deliberately accepts only SQLite in-memory configuration. File
 * SQLite and MySQL integration profiles need separate, explicitly designed
 * bootstrap policies rather than exceptions to this functional-test guard.
 */
final class TestDatabaseGuard
{
    /**
     * Capture every visible representation so conflicting process-level values
     * cannot be hidden by PHPUnit's non-forced XML environment declarations.
     *
     * @return array<string, list<mixed>>
     */
    public static function processEnvironmentSnapshot(): array
    {
        $snapshot = [];

        foreach (['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL'] as $name) {
            $values = [];

            foreach ([$_ENV, $_SERVER] as $source) {
                if (array_key_exists($name, $source)) {
                    $values[] = $source[$name];
                }
            }

            $processValue = getenv($name);

            if ($processValue !== false) {
                $values[] = $processValue;
            }

            $snapshot[$name] = $values;
        }

        return $snapshot;
    }

    /**
     * Identify Artisan's test command, including supported global --env forms.
     *
     * @param  list<mixed>  $argv
     */
    public static function isArtisanTestCommand(array $argv): bool
    {
        $script = $argv[0] ?? null;

        if (! is_string($script) || basename(str_replace('\\', '/', $script)) !== 'artisan') {
            return false;
        }

        $arguments = array_slice($argv, 1);
        $skipNext = false;
        $parseOptions = true;

        foreach ($arguments as $argument) {
            if (! is_string($argument)) {
                return false;
            }

            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if ($parseOptions && $argument === '--') {
                $parseOptions = false;

                continue;
            }

            if ($parseOptions && ($argument === '--env' || $argument === '-e')) {
                $skipNext = true;

                continue;
            }

            if ($parseOptions && str_starts_with($argument, '--env=')) {
                continue;
            }

            if ($parseOptions && str_starts_with($argument, '-')) {
                continue;
            }

            return $argument === 'test';
        }

        return false;
    }

    /**
     * Identify PHPUnit processes that may bootstrap a Laravel test application.
     *
     * @param  list<mixed>  $argv
     */
    public static function isPhpUnitProcess(array $argv): bool
    {
        $script = $argv[0] ?? null;

        if (! is_string($script)) {
            return false;
        }

        $normalized = strtolower(str_replace('\\', '/', $script));
        $name = basename($normalized);

        return in_array($name, ['phpunit', 'phpunit.php', 'phpunit.phar'], true)
            || str_ends_with($normalized, '/vendor/phpunit/phpunit/phpunit');
    }

    /**
     * @param  list<mixed>  $argv
     */
    public static function isTestRunnerProcess(array $argv): bool
    {
        return self::isArtisanTestCommand($argv) || self::isPhpUnitProcess($argv);
    }

    /**
     * Reject unsafe values inherited by the Artisan parent process.
     *
     * Missing values are allowed here because PHPUnit's configured test
     * environment supplies them in the child process. Any supplied value must
     * already be a safe functional-test value.
     *
     * @param  array<string, list<mixed>>  $snapshot
     */
    public static function assertSafeProcessOverrides(array $snapshot): void
    {
        $violations = [];
        $expected = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ];

        foreach ($expected as $name => $expectedValue) {
            foreach ($snapshot[$name] ?? [] as $value) {
                if ($value !== $expectedValue) {
                    $violations[] = $name;
                    break;
                }
            }
        }

        foreach ($snapshot['DB_URL'] ?? [] as $value) {
            if (! self::isEmptyUrl($value)) {
                $violations[] = 'DB_URL';
                break;
            }
        }

        if ($violations !== []) {
            self::reject($violations);
        }
    }

    /**
     * Set safe values only in this test-runner process and its children.
     */
    public static function applyFunctionalTestEnvironment(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ] as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    /**
     * Validate the effective Laravel configuration and raw environment inputs.
     *
     * @param  array<string, mixed>  $effective
     * @param  array<string, list<mixed>>  $environment
     */
    public static function assertSafeConfiguration(array $effective, array $environment): void
    {
        $violations = [];

        if (($effective['application_environment'] ?? null) !== 'testing'
            || ($effective['resolved_environment'] ?? null) !== 'testing') {
            $violations[] = 'APP_ENV';
        }

        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ] as $name => $expectedValue) {
            $values = $environment[$name] ?? [];

            if (! is_array($values)) {
                $values = [$values];
            }

            if ($values === []) {
                $violations[] = $name;

                continue;
            }

            foreach ($values as $value) {
                if ($value !== $expectedValue) {
                    $violations[] = $name;
                    break;
                }
            }
        }

        foreach ($environment['DB_URL'] ?? [] as $value) {
            if (! self::isEmptyUrl($value)) {
                $violations[] = 'DB_URL';
                break;
            }
        }

        $database = $effective['database'] ?? null;

        if (! is_array($database)) {
            $violations[] = 'database configuration';
        } else {
            if (($database['default'] ?? null) !== 'sqlite') {
                $violations[] = 'default database connection';
            }

            $connections = $database['connections'] ?? null;
            $sqlite = is_array($connections) ? ($connections['sqlite'] ?? null) : null;

            if (! is_array($sqlite)) {
                $violations[] = 'SQLite connection configuration';
            } else {
                if (($sqlite['driver'] ?? null) !== 'sqlite') {
                    $violations[] = 'SQLite driver';
                }

                if (! array_key_exists('database', $sqlite)
                    || ! is_string($sqlite['database'])
                    || $sqlite['database'] !== ':memory:') {
                    $violations[] = 'SQLite database';
                }

                if (! array_key_exists('url', $sqlite) || ! self::isEmptyUrl($sqlite['url'])) {
                    $violations[] = 'SQLite URL';
                }

                foreach (['host', 'port', 'unix_socket'] as $key) {
                    if (array_key_exists($key, $sqlite) && ! self::isEmptyUrl($sqlite[$key])) {
                        $violations[] = 'SQLite '.$key;
                    }
                }

                foreach (['read', 'write'] as $key) {
                    if (array_key_exists($key, $sqlite)
                        && $sqlite[$key] !== null
                        && $sqlite[$key] !== []) {
                        $violations[] = 'SQLite '.$key.' configuration';
                    }
                }
            }
        }

        if ($violations !== []) {
            self::reject($violations);
        }
    }

    /**
     * Validate a bootstrapped application's effective configuration.
     *
     * Called after Laravel loads configuration and before it registers or boots
     * service providers in the Artisan test parent process.
     */
    public static function assertSafeApplication(Application $application): void
    {
        $configuration = $application->make('config');

        self::assertSafeConfiguration([
            'application_environment' => $configuration->get('app.env'),
            'resolved_environment' => $application->environment(),
            'database' => $configuration->get('database'),
        ], self::processEnvironmentSnapshot());
    }

    private static function isEmptyUrl(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Diagnostics name unsafe settings but never echo their values.
     *
     * @param  list<string>  $violations
     */
    private static function reject(array $violations): never
    {
        $violations = array_values(array_unique($violations));

        throw new RuntimeException(
            'Unsafe test database configuration. Functional tests require APP_ENV=testing, '
            .'DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and an empty DB_URL. '
            .'Rejected setting(s): '.implode(', ', $violations).'. Values are omitted.'
        );
    }
}
