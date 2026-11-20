# PawHeaven Phase D1.1 — Test Database Isolation Foundation

**Date:** 2026-11-18
**Repository:** D:/PawHaven/PH Web Version/WebApp
**Status:** Guard and CI wiring implemented; runtime acceptance validation incomplete
**Database activity:** Active business database was not opened or modified

## 1. Summary

A fail-closed policy now protects the default PHPUnit suite. It accepts only an effective Laravel environment of testing with the default SQLite connection, an explicit :memory: database, and an empty database URL. Database paths, non-SQLite defaults, URLs, unexpected SQLite host/read/write overrides, and conflicting inherited environment values are rejected. Diagnostics identify setting names only and omit values.

The guard is checked at three ordered points:

1. The Artisan test entry checks inherited process database settings before Laravel starts and sets safe variables only in that PHP process and its child processes.
2. bootstrap/app.php registers a test-runner callback for Artisan tests and direct PHPUnit processes after Laravel loads configuration and before service providers boot. It checks the actual resolved application configuration, including cached configuration.
3. PHPUnit uses tests/bootstrap.php, which loads only Laravel's environment and configuration stages, then checks the resolved configuration before PHPUnit loads test classes or Laravel can run RefreshDatabase or related traits.

The CI job now defines APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and an empty DB_URL at the job process boundary. This applies to both composer setup and composer ci:check. The workflow is configured so the setup migration will target the runner process's ephemeral in-memory SQLite database, not a persistent SQLite file; the subsequent test command is a separate process with its own in-memory database.

Twenty-one focused guard tests were added. They were not executed: the targeted PHPUnit invocation stopped before the custom bootstrap ran with PHPUnit 12.5.38 reporting that it could not open tests/bootstrap.php. This matches the known Windows PHPUnit bootstrap blocker. No runtime workaround was attempted. PHP syntax and bootstrap ordering were checked statically. D1.1 is not fully acceptance-validated until the isolated tests run successfully in the approved Linux workflow or another authorized Linux test environment.

No currency, Owner, reporting, or commerce concurrency implementation was started.

## 2. Git baseline and preservation

Before edits:

- Branch: master.
- HEAD: 1dcaf456b78626469eb60455ab6bf48be944fc19.
- 102 tracked entries already modified.
- 83 untracked entries already present.
- 0 staged entries.

The existing working tree contained substantial PawHeaven development and the Phase D1 documents. None was overwritten, staged, discarded, or otherwise changed by this phase.

Files changed in this phase:

**Tracked modifications**

- artisan
- bootstrap/app.php
- phpunit.xml
- .github/workflows/tests.yml

**New untracked files**

- tests/bootstrap.php
- tests/Support/TestDatabaseGuard.php
- tests/Unit/TestDatabaseGuardTest.php
- docs/audit/PHASE_D1_1_TEST_DATABASE_ISOLATION_2026-11-18.md

The final status counts are stated in the completion message after the final check. No reset, clean, stash, restore, rebase, stage, commit, or push was performed. Composer metadata, dependencies, vendor files, tests/TestCase.php, environment files, and database files were not modified.

## 3. Existing execution path and guard placement

### PHPUnit configuration and environment precedence

Before this change, phpunit.xml bootstrapped only vendor/autoload.php and declared APP_ENV/testing plus SQLite/:memory: DB variables using ordinary PHPUnit <env> entries without force="true".

PHPUnit 12.5.38's vendor/phpunit/phpunit/src/TextUI/Configuration/PhpHandler.php, handleEnvVariables(), calls putenv only when the XML entry is forced or getenv() is false. Thus an inherited process value can win over the XML setting. This is why the guard checks the process environment and resolved Laravel configuration rather than trusting XML declarations.

PHPUnit's TextUI/Application.php runs PhpHandler before BootstrapLoader. The configured tests/bootstrap.php therefore sees PHPUnit's environment values and is executed before PHPUnit discovers/loads the test classes.

### Laravel trait timing

tests/TestCase.php calls parent::setUp() before its own SettingsService cache reset. Laravel's Foundation Testing TestCase enters setUpTheTestEnvironment(), which calls setUpTraits(). InteractsWithTestCaseLifecycle::setUpTraits() invokes RefreshDatabase, DatabaseMigrations, DatabaseTruncation, or DatabaseTransactions before the project TestCase's own setUp() continues. A guard only in tests/TestCase::setUp() would be too late.

The current test tree contains 113 RefreshDatabase source matches. No DatabaseMigrations, DatabaseTransactions, DatabaseTruncation, direct migrate:fresh, migrate:refresh, migrate:reset, Artisan migration command, schema-drop, or truncate invocation was found in tests. tests/Pest.php is absent.

### Laravel configuration cache and Artisan test behavior

Laravel's LoadEnvironmentVariables::bootstrap() returns early when configuration is cached. LoadConfiguration then loads the cached configuration. A safe-looking process environment alone is not sufficient; the guard must validate the resolved config after LoadConfiguration.

The installed Collision Laravel TestCommand uses phpunit.xml as its PHPUnit configuration and clears Laravel-loaded dotenv variable names before launching its PHPUnit child. This can erase values that would otherwise reveal an inherited conflict. The Artisan entry therefore snapshots and checks inherited process values before Laravel boots or Collision clears them.

The application has two configured first-party service providers in bootstrap/providers.php. Source inspection of AppServiceProvider and FortifyServiceProvider found no database query during provider boot. The new Artisan parent guard additionally forces its process to SQLite memory before app boot, and bootstrap/app.php validates the resolved configuration after LoadConfiguration and before providers are registered/booted.

### Earliest reliable checks

- **Artisan command parent:** artisan detects the test command before requiring bootstrap/app.php, rejects any supplied APP_ENV, DB_CONNECTION, or DB_DATABASE value that is not the safe functional-test value, rejects a non-empty DB_URL, and then sets safe variables only in that PHP process.
- **Resolved Laravel application:** bootstrap/app.php registers an afterBootstrapping(LoadConfiguration::class) callback for Artisan test commands and direct PHPUnit processes. Application::bootstrapWith() dispatches the matching bootstrapped event after that bootstrapper and before proceeding to provider registration. The callback checks effective Laravel configuration before provider boot.
- **Direct PHPUnit and focused classes:** phpunit.xml selects tests/bootstrap.php. That script loads environment and configuration only, without registering or booting providers, then verifies the effective app environment, default connection, SQLite driver/database/URL, and raw environment values. This happens before the test suite can load a Laravel test class or run its traits.

The three checks use one policy implementation in tests/Support/TestDatabaseGuard.php.

## 4. Guard policy

### Accepted functional-test configuration

All must resolve to:

| Setting | Required value |
|---|---|
| Effective Laravel app environment | testing |
| Process APP_ENV | testing |
| Effective default connection | sqlite |
| Process DB_CONNECTION | sqlite |
| Effective SQLite driver | sqlite |
| Effective SQLite database | :memory: |
| Process DB_DATABASE | :memory: |
| Effective SQLite URL | null or empty |
| Process DB_URL | null or empty |

The active SQLite application database and every other file-backed SQLite path are rejected. The guard also rejects unexpected host, port, Unix socket, read connection, or write connection details on the effective SQLite connection. Error text names rejected settings and never includes their values.

### File-backed SQLite

No file-backed exception was added. The existing PHPUnit configuration already uses in-memory SQLite, and inspection found no test requirement for a shared persistent database file. Consequently no canonicalization, symlink, or path-traversal allowance is needed: every SQLite database value other than the literal :memory: is rejected before a connection is made. Tests simulate the active business path and an outside path as configuration strings; they do not open either.

### MySQL integration

No MySQL integration exception is operational in this phase, and no MySQL service was added. The functional-test policy rejects MySQL. A future integration suite needs a separate explicit profile/opt-in and a dedicated isolated CI service with fixed test host/database credentials and validation that refuses production or buyer hosts/databases. A generic host allowlist is not implemented.

## 5. Dedicated guard tests

tests/Unit/TestDatabaseGuardTest.php adds 21 focused methods using PHPUnit\Framework\TestCase directly. The suite does not use RefreshDatabase, database transactions, migrations, fixtures, or a PDO connection.

Coverage includes:

- Valid testing + SQLite + :memory: acceptance.
- Empty DB_URL acceptance.
- Production and missing environment rejection.
- MySQL and unexpected default connection rejection.
- Missing DB_DATABASE rejection.
- Non-empty effective/process DB_URL rejection without echoing it.
- Active database/database.sqlite and outside file path rejection.
- Conflicting process-level DB values rejection.
- Cached/effective configuration drift rejection.
- Unexpected SQLite host rejection.
- No opening/creation of a rejected random temporary path.
- PHPUnit bootstrap selection and its configuration-before-guard ordering.
- Artisan pre-bootstrap environment check and safe-process setup ordering.
- Effective-config callback placement and test-runner detection without capturing unrelated Artisan commands.

These tests simulate configuration arrays and inspect file paths as strings. The active business database is not used as a negative-test fixture.

## 6. CI integration

The existing .github/workflows/tests.yml and composer.json scripts were reinspected. The workflow still runs on pushes to main and pull requests. It still runs composer setup and then composer ci:check; Composer retains Pint, PHPStan, and artisan test. No failure suppression was added.

A job-level environment now forces safe values for both workflow steps. This protects the workflow setup migration as well as the later test process. Each command is a separate process; SQLite :memory: does not persist between them. The PHPUnit XML values remain non-forced intentionally so the guard can detect unsafe inherited settings during ordinary local/direct PHPUnit invocations.

No MySQL service, engine matrix, workflow_dispatch, branch, commit, pull request, or push was added. Hosted Actions cannot test this uncommitted working tree. GitHub CLI is unavailable in the environment, so no workflow run was inspected or triggered.

## 7. Validation and actual execution results

### Completed

- PHP syntax checks passed for artisan, bootstrap/app.php, tests/Support/TestDatabaseGuard.php, tests/bootstrap.php, and tests/Unit/TestDatabaseGuardTest.php.
- Static ordering checks confirmed:
  - Artisan checks and applies its test-only process environment before bootstrap/app.php is required.
  - PHPUnit selects tests/bootstrap.php.
  - The PHPUnit bootstrap loads configuration before calling the guard.
  - The test-runner callback is registered after LoadConfiguration for Artisan and direct PHPUnit processes.
- git diff --check reported no whitespace errors in the modified tracked files.
- Source inspection confirmed existing Composer scripts and workflow checks remain present.

### Attempted, blocked

One targeted invocation was attempted after the initial guard wiring and static checks. After this invocation, the Artisan configuration-cache validation hook was added and syntax/static ordering were rechecked; the PHPUnit bootstrap path itself was unchanged. The final tests therefore remain unexecuted.

- Command: php artisan test --without-tty --filter=TestDatabaseGuardTest
- Result: PHPUnit 12.5.38 started, then reported: “Cannot open bootstrap script” for the new tests/bootstrap.php path.
- Exit code: 1.
- The guard test methods did not execute. No passing PHPUnit result is claimed.
- The failure is consistent with the known Windows PHPUnit bootstrap/is_readable blocker. No runtime, ACL, configuration, dependency, or vendor workaround was attempted.

The full PHPUnit suite, Pint, PHPStan, browser checks, migrations, seeders, MySQL/MariaDB tests, and hosted Actions were not run.

## 8. Database and Git preservation

- The active database/database.sqlite was not opened, queried, migrated, copied, or modified.
- No database connection was initiated by the guard test class. The attempted test run failed at PHPUnit bootstrap before test discovery.
- No migration or seeder command was run.
- No backup, dependency, environment file, or generated build output was changed.
- Existing changes remain unstaged and preserved; final Git counts are reported after the last status check.

## 9. Acceptance status and remaining limitations

| Criterion | Status |
|---|---|
| Guard precedes Laravel RefreshDatabase and other framework database traits | Implemented; static ordering checked; PHPUnit test blocked before execution |
| Default suite requires effective testing + SQLite :memory: + empty DB_URL | Implemented; syntax/static checks pass; dedicated runtime tests unexecuted |
| Ordinary test configuration cannot select the active business SQLite file | Guard rejects all file-backed SQLite config; no DB access occurred; runtime acceptance test unexecuted |
| Unexpected process-level DB settings/non-empty DB_URL are rejected | Implemented in Artisan parent and PHPUnit bootstrap; runtime tests unexecuted |
| Dedicated acceptance/rejection tests exist | 21 methods added; not executed |
| Existing Linux workflow invokes guarded PHPUnit path | Configured through phpunit.xml bootstrap and job-level environment; no hosted run available |
| Existing user changes and database preserved | No unrelated files or active database were modified |

D1.1 therefore has an implemented safety boundary, but its acceptance remains incomplete until the focused guard tests execute successfully in an authorized Linux environment. The Windows PHPUnit failure was not bypassed. MariaDB/MySQL integration remains a separately designed future profile and is not enabled by this phase.

## 10. Next action

Run the focused TestDatabaseGuardTest class through the existing Linux CI workflow once an authorized mechanism can submit an exact source snapshot. Record the run identifier and commit SHA. Do not begin D2 until the guard tests have an actual successful result and the product owner authorizes the next workstream.

