<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MarketplaceSeeder;
use Database\Seeders\ShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_shop_seeder_is_repeatable_without_creating_accounts_or_orders(): void
    {
        $this->seed(ShopSeeder::class);
        $this->seed(ShopSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('products', 4);
    }

    public function test_production_seeders_do_not_create_demo_data_or_users(): void
    {
        config(['app.env' => 'production']);
        $this->app['env'] = 'production';

        app(DatabaseSeeder::class)->run();
        app(ShopSeeder::class)->run();
        app(MarketplaceSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('pet_listings', 0);
    }

    public function test_shop_seeder_does_not_change_existing_accounts_or_orders(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->seed(ShopSeeder::class);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'admin']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('orders', 0);
    }
}
