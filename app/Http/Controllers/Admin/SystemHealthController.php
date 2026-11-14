<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Passkeys\Passkeys;

class SystemHealthController extends Controller
{
    /**
     * Display the system and security health status console.
     */
    public function index(): View
    {
        $dbConnected = false;
        $dbError = null;
        $tableCount = 0;

        try {
            DB::connection()->getPdo();
            $dbConnected = true;
            if (config('database.default') === 'sqlite') {
                $tableCount = (int) DB::selectOne("SELECT COUNT(*) as count FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")?->count;
            } else {
                $tableCount = count(DB::select('SHOW TABLES'));
            }
        } catch (\Throwable $e) {
            $dbConnected = false;
            $dbError = 'Database connection unavailable.';
            Log::warning('System health database check failed.', [
                'exception_type' => get_class($e),
            ]);
        }

        $storagePath = public_path('storage');
        $storageLinked = file_exists($storagePath) && (is_link($storagePath) || is_dir($storagePath));
        $storageWritable = is_writable(storage_path('app/public'));

        $checks = [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
                'locale' => config('app.locale'),
                'timezone' => config('app.timezone'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            'database' => [
                'driver' => config('database.default'),
                'connected' => $dbConnected,
                'tables' => $tableCount,
                'error' => $dbError,
            ],
            'storage' => [
                'linked' => $storageLinked,
                'writable' => $storageWritable,
                'disk' => config('filesystems.default'),
            ],
            'mail' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host') ? 'Configured' : 'Not configured',
                'from_address' => config('mail.from.address'),
            ],
            'security' => [
                'session_driver' => config('session.driver'),
                'session_lifetime' => config('session.lifetime'),
                'admin_2fa_enforced' => true,
                'passkeys_supported' => class_exists(Passkeys::class),
                'https_recommended' => ! app()->isLocal(),
            ],
            'cache_queue' => [
                'cache_driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
            ],
        ];

        return view('admin.system.index', compact('checks'));
    }
}
