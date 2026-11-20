<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsSeller;
use App\Http\Middleware\EnsureUserIsStaffOrAdmin;
use App\Http\Middleware\RestoreUserDeviceSession;
use App\Console\Commands\BusinessPreflightCommand;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\TestDatabaseGuard;

$application = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([BusinessPreflightCommand::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [RestoreUserDeviceSession::class]);
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            RestoreUserDeviceSession::class,
        );

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin*')) {
                return route('admin.login');
            }

            if ($request->is('seller*')) {
                return route('seller.login');
            }

            return route('login');
        });

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'seller' => EnsureUserIsSeller::class,
            'customer' => EnsureUserIsCustomer::class,
            'staff_or_admin' => EnsureUserIsStaffOrAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'code',
            'token',
        ]);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (config('app.debug') || $request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            return response()->view('errors.page', ['status' => $status], $status);
        });
    })->create();

if (class_exists(TestDatabaseGuard::class)
    && TestDatabaseGuard::isTestRunnerProcess($_SERVER['argv'] ?? [])) {
    $application->afterBootstrapping(
        LoadConfiguration::class,
        static function (Application $application): void {
            TestDatabaseGuard::assertSafeApplication($application);
        },
    );
}

return $application;
