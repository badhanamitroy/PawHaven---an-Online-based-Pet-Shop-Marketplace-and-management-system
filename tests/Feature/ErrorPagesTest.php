<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_invalid_url_displays_the_branded_not_found_page(): void
    {
        $this->get('/page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('PAWHAVEN · 404');
    }

    public function test_authenticated_user_without_admin_access_sees_branded_forbidden_page(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('Access denied')
            ->assertSee('PAWHAVEN · 403');
    }

    public function test_session_expiration_displays_the_branded_page_and_login_action(): void
    {
        Route::get('/__test-errors/session-expired', function () {
            throw new TokenMismatchException;
        });

        $this->get('/__test-errors/session-expired')
            ->assertStatus(419)
            ->assertSee('Your session has expired')
            ->assertSee(route('login'), false);
    }

    public function test_rate_limit_displays_the_branded_page(): void
    {
        Route::get('/__test-errors/rate-limited', fn () => abort(429));

        $this->get('/__test-errors/rate-limited')
            ->assertStatus(429)
            ->assertSee('Too many requests')
            ->assertSee('PAWHAVEN · 429');
    }

    public function test_server_exception_displays_no_technical_details_when_debug_is_disabled(): void
    {
        Route::get('/__test-errors/server-error', function () {
            throw new RuntimeException('Private diagnostic marker');
        });

        $this->get('/__test-errors/server-error')
            ->assertServerError()
            ->assertSee('Internal server error')
            ->assertSee('PAWHAVEN · 500')
            ->assertDontSee('Private diagnostic marker')
            ->assertDontSee('Exception trace')
            ->assertDontSee('vendor\\laravel');
    }

    public function test_service_unavailable_displays_the_branded_page(): void
    {
        Route::get('/__test-errors/service-unavailable', fn () => abort(503));

        $this->get('/__test-errors/service-unavailable')
            ->assertStatus(503)
            ->assertSee('Service temporarily unavailable')
            ->assertSee('PAWHAVEN · 503');
    }

    public function test_debug_mode_keeps_framework_exception_details_available_to_developers(): void
    {
        config(['app.debug' => true]);

        Route::get('/__test-errors/debug-details', function () {
            throw new RuntimeException('Developer-only-debug-marker');
        });

        $this->get('/__test-errors/debug-details')
            ->assertServerError()
            ->assertSee('Developer-only-debug-marker');
    }

    public function test_production_configuration_disables_debug_even_if_app_debug_is_enabled(): void
    {
        $environment = $_ENV['APP_ENV'] ?? null;
        $serverEnvironment = $_SERVER['APP_ENV'] ?? null;
        $debug = $_ENV['APP_DEBUG'] ?? null;
        $serverDebug = $_SERVER['APP_DEBUG'] ?? null;

        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = 'true';

        try {
            $productionConfig = require base_path('config/app.php');

            $this->assertFalse($productionConfig['debug']);
        } finally {
            $this->restoreEnvironmentValue('APP_ENV', $environment, $serverEnvironment);
            $this->restoreEnvironmentValue('APP_DEBUG', $debug, $serverDebug);
        }
    }

    private function restoreEnvironmentValue(string $key, mixed $environment, mixed $server): void
    {
        if ($environment === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $environment;
        }

        if ($server === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $server;
        }
    }
}
