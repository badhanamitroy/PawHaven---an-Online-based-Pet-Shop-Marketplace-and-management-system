<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $requiredSchema = [
            'companies' => ['id', 'is_active'],
            'branches' => ['company_id', 'is_active'],
            'business_memberships' => ['user_id', 'company_id', 'branch_id', 'role', 'is_active'],
            'product_categories' => ['company_id'],
            'products' => ['company_id', 'branch_id', 'currency', 'archived_at'],
            'orders' => [
                'company_id', 'branch_id', 'currency', 'employee_seller_id',
                'sales_channel', 'pos_submission_key', 'online_submission_key', 'fulfilment_status',
            ],
            'payment_transactions' => ['order_id', 'amount_minor', 'currency', 'status'],
            'payment_refund_transactions' => ['payment_transaction_id', 'amount_minor', 'currency', 'status'],
            'inventory_movements' => ['product_id', 'company_id', 'movement_type', 'quantity_delta'],
            'product_returns' => ['order_id', 'product_id', 'status', 'requested_quantity'],
            'order_fulfilment_events' => ['order_id', 'company_id', 'to_status'],
            'branch_product_prices' => ['product_id', 'company_id', 'branch_id', 'price_minor'],
            'promotions' => ['company_id', 'discount_type', 'discount_value'],
            'coupons' => ['company_id', 'code', 'discount_type', 'discount_value'],
        ];
        $missingTables = [];
        $missingColumns = [];
        $pendingMigrations = [];
        $unknownAppliedMigrations = [];
        $migrationState = 'unavailable';
        $schemaReady = false;

        try {
            DB::connection()->getPdo();
            $dbConnected = true;
            $tableNames = Schema::getTableListing();
            $tableCount = count($tableNames);

            foreach ($requiredSchema as $table => $columns) {
                if (! Schema::hasTable($table)) {
                    $missingTables[] = $table;

                    continue;
                }

                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        $missingColumns[] = "{$table}.{$column}";
                    }
                }
            }

            if (! Schema::hasTable('migrations')) {
                $migrationState = 'migration_history_missing';
            } else {
                $migrationFiles = array_map(
                    static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
                    glob(database_path('migrations/*.php')) ?: [],
                );
                $appliedMigrations = DB::table('migrations')->pluck('migration')->all();
                $pendingMigrations = array_values(array_diff($migrationFiles, $appliedMigrations));
                $unknownAppliedMigrations = array_values(array_diff($appliedMigrations, $migrationFiles));
                $migrationState = $unknownAppliedMigrations !== []
                    ? 'unknown_applied_migrations'
                    : ($pendingMigrations !== [] ? 'pending_migrations' : 'current');
            }

            $schemaReady = $missingTables === [] && $missingColumns === [] && $migrationState === 'current';
        } catch (\Throwable $e) {
            $schemaReady = false;
            $dbError = $dbConnected ? 'Database schema inspection failed.' : 'Database connection unavailable.';
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
                'schema_ready' => $schemaReady,
                'missing_tables' => $missingTables,
                'missing_columns' => $missingColumns,
                'migration_state' => $migrationState,
                'pending_migrations' => count($pendingMigrations),
                'unknown_applied_migrations' => count($unknownAppliedMigrations),
            ],
            'storage' => [
                'linked' => $storageLinked,
                'writable' => $storageWritable,
                'disk' => config('filesystems.default'),
            ],
            'mail' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host') ? 'Configured' : 'Not configured',
                'from_address_configured' => filled(config('mail.from.address')),
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
