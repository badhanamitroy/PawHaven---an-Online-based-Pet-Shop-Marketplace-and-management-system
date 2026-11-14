<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemoAccountsSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, email: string, role: string, password_env: string}>
     */
    private const ACCOUNTS = [
        'admin' => [
            'name' => 'PawHaven Admin',
            'email' => 'badhanamitroy571@gmail.com',
            'role' => 'admin',
            'password_env' => 'PAWHAVEN_DEMO_ADMIN_PASSWORD',
        ],
        'seller1' => [
            'name' => 'Jashim Uddin',
            'email' => 'amitroyspsc@gmail.com',
            'role' => 'seller',
            'password_env' => 'PAWHAVEN_DEMO_SELLER1_PASSWORD',
        ],
        'seller2' => [
            'name' => 'Shimontini Saha',
            'email' => 'badhancse28@gmail.com',
            'role' => 'seller',
            'password_env' => 'PAWHAVEN_DEMO_SELLER2_PASSWORD',
        ],
        'customer1' => [
            'name' => 'Badhan Roy',
            'email' => 'badhanamitroy@gmail.com',
            'role' => 'customer',
            'password_env' => 'PAWHAVEN_DEMO_CUSTOMER1_PASSWORD',
        ],
        'customer2' => [
            'name' => 'Payel Chy',
            'email' => 'pcc40993@gmail.com',
            'role' => 'customer',
            'password_env' => 'PAWHAVEN_DEMO_CUSTOMER2_PASSWORD',
        ],
    ];

    public function run(): void
    {
        $this->assertLocalDevelopmentDatabase();

        $passwords = [];
        foreach (self::ACCOUNTS as $key => $account) {
            $password = getenv($account['password_env']);
            if (! is_string($password) || $password === '') {
                throw new RuntimeException("Set {$account['password_env']} for this one-time local setup.");
            }

            $passwords[$key] = $password;
        }

        $result = DB::transaction(function () use ($passwords): array {
            $targetEmails = array_map(
                static fn (array $account): string => strtolower($account['email']),
                self::ACCOUNTS,
            );
            $removed = 0;
            $skipped = [];

            $existingAccounts = User::query()
                ->where(function ($query): void {
                    $query->whereIn('role', ['admin', 'seller', 'customer', 'user'])
                        ->orWhereNull('role')
                        ->orWhere('role', '');
                })
                ->get();

            foreach ($existingAccounts as $user) {
                if (in_array(strtolower($user->email), $targetEmails, true)) {
                    continue;
                }

                $references = $this->userReferences($user);
                if ($references !== []) {
                    $skipped[] = [
                        'id' => $user->id,
                        'role' => $user->role ?? 'customer',
                        'references' => $references,
                    ];

                    continue;
                }

                $user->delete();
                $removed++;
            }

            foreach (self::ACCOUNTS as $key => $account) {
                $user = User::query()->where('email', $account['email'])->first();
                if ($user !== null
                    && $user->role !== $account['role']
                    && $this->userReferences($user) !== []) {
                    throw new RuntimeException(
                        "A requested demo email belongs to an account with linked records and a different role; account id {$user->id} was not changed."
                    );
                }

                $user ??= new User;
                $attributes = [
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                ];

                if (! $user->exists || ! Hash::check($passwords[$key], $user->password)) {
                    $attributes['password'] = Hash::make($passwords[$key]);
                }

                if ($user->email_verified_at === null) {
                    $attributes['email_verified_at'] = now();
                }

                $user->forceFill($attributes)->save();
            }

            return ['removed' => $removed, 'skipped' => $skipped];
        });

        $this->command?->info("Prepared five local demo accounts; removed {$result['removed']} unreferenced legacy accounts.");
        foreach ($result['skipped'] as $account) {
            $references = implode(', ', $account['references']);
            $this->command?->warn("Skipped {$account['role']} account id {$account['id']} with linked records: {$references}.");
        }
    }

    private function assertLocalDevelopmentDatabase(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Demo account setup is restricted to the local environment.');
        }

        $connection = DB::connection();
        $expectedDatabase = realpath(database_path('database.sqlite'));
        $configuredDatabase = realpath($connection->getDatabaseName());

        if ($connection->getDriverName() !== 'sqlite'
            || $expectedDatabase === false
            || $configuredDatabase !== $expectedDatabase) {
            throw new RuntimeException('Demo account setup requires the repository-local SQLite database.');
        }

        if ((int) $connection->selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
            throw new RuntimeException('SQLite foreign-key enforcement must be enabled for demo account setup.');
        }
    }

    /**
     * Find rows that could be lost or orphaned by deleting a user.
     *
     * @return list<string>
     */
    private function userReferences(User $user): array
    {
        $references = [];
        $seenColumns = [];
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();
        $tables = $connection->select(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
        );

        foreach ($tables as $tableRow) {
            $table = $tableRow->name;
            $wrappedTable = $grammar->wrapTable($table);
            $columns = $connection->select("PRAGMA table_info({$wrappedTable})");

            foreach ($connection->select("PRAGMA foreign_key_list({$wrappedTable})") as $foreignKey) {
                if ($foreignKey->table !== 'users') {
                    continue;
                }

                $column = $foreignKey->from;
                $seenColumns[$table.'.'.$column] = true;
                $count = DB::table($table)->where($column, $user->id)->count();
                if ($count > 0) {
                    $references[] = "{$table}.{$column} ({$count}, {$foreignKey->on_delete})";
                }
            }

            foreach ($columns as $column) {
                $columnName = $column->name;
                if (isset($seenColumns[$table.'.'.$columnName])
                    || ! in_array($columnName, ['user_id', 'seller_id', 'admin_user_id', 'owner_id', 'created_by', 'updated_by'], true)
                    && ! str_ends_with($columnName, '_user_id')) {
                    continue;
                }

                $count = DB::table($table)->where($columnName, $user->id)->count();
                if ($count > 0) {
                    $references[] = "{$table}.{$columnName} ({$count}, no foreign key)";
                }
            }
        }

        if (Schema::hasTable('password_reset_tokens')
            && Schema::hasColumn('password_reset_tokens', 'email')
            && DB::table('password_reset_tokens')->where('email', $user->email)->exists()) {
            $references[] = 'password_reset_tokens.email (1, no foreign key)';
        }

        return array_values(array_unique($references));
    }
}
