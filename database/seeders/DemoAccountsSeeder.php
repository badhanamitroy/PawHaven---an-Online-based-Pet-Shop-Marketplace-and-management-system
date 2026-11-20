<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoAccountsSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, email: string, role: string, password_env: string}>
     */
    private const ACCOUNTS = [
        'admin' => [
            'name' => 'PawHaven Local Admin',
            'email' => 'admin@pawhaven.test',
            'role' => 'admin',
            'password_env' => 'PAWHAVEN_DEMO_ADMIN_PASSWORD',
        ],
        'seller1' => [
            'name' => 'Demo Seller One',
            'email' => 'seller@example.test',
            'role' => 'seller',
            'password_env' => 'PAWHAVEN_DEMO_SELLER1_PASSWORD',
        ],
        'seller2' => [
            'name' => 'Demo Seller Two',
            'email' => 'seller2@pawhaven.test',
            'role' => 'seller',
            'password_env' => 'PAWHAVEN_DEMO_SELLER2_PASSWORD',
        ],
        'customer1' => [
            'name' => 'Demo Customer One',
            'email' => 'customer1@pawhaven.test',
            'role' => 'customer',
            'password_env' => 'PAWHAVEN_DEMO_CUSTOMER1_PASSWORD',
        ],
        'customer2' => [
            'name' => 'Demo Customer Two',
            'email' => 'customer2@pawhaven.test',
            'role' => 'customer',
            'password_env' => 'PAWHAVEN_DEMO_CUSTOMER2_PASSWORD',
        ],
    ];

    public function run(): void
    {
        $this->assertIsolatedTestDatabase();

        $passwords = [];
        foreach (self::ACCOUNTS as $key => $account) {
            $password = getenv($account['password_env']);
            if (! is_string($password) || $password === '') {
                throw new RuntimeException("Set {$account['password_env']} for isolated demo setup.");
            }

            $passwords[$key] = $password;
        }

        $created = DB::transaction(function () use ($passwords): int {
            foreach (self::ACCOUNTS as $account) {
                if (User::query()->where('email', $account['email'])->exists()) {
                    throw new RuntimeException('A demo email is already in use; no accounts were changed.');
                }
            }

            foreach (self::ACCOUNTS as $key => $account) {
                $user = new User;
                $user->forceFill([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'role' => $account['role'],
                    'password' => Hash::make($passwords[$key]),
                    'email_verified_at' => now(),
                ])->save();
            }

            return count(self::ACCOUNTS);
        });

        $this->command?->info("Created {$created} isolated demo accounts. Existing accounts were not modified.");
    }

    private function assertIsolatedTestDatabase(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Demo account setup is restricted to an isolated testing environment.');
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Demo account setup requires an in-memory SQLite database.');
        }

        if ((int) $connection->selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
            throw new RuntimeException('SQLite foreign-key enforcement must be enabled for demo account setup.');
        }
    }
}
