<?php

namespace Tests\Feature\Performance;

use App\Facades\Settings;
use App\Mail\LoginOtpMail;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class PerformanceCharacterizationTest extends TestCase
{
    use CreatesBusinessMemberships;
    use RefreshDatabase;

    /**
     * @var array{database_queries: int, database_ms: float}|null
     */
    private ?array $currentMeasurement = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'database',
            'session.driver' => 'database',
        ]);

        DB::listen(function (QueryExecuted $query): void {
            if ($this->currentMeasurement === null) {
                return;
            }

            $this->currentMeasurement['database_queries']++;
            $this->currentMeasurement['database_ms'] += $query->time;
        });
    }

    public function test_public_page_render_performance_with_cold_settings_cache(): void
    {
        foreach ([
            'home_page' => '/',
            'login_page' => '/login',
            'registration_page' => '/register',
            'admin_login_page' => '/admin/login',
        ] as $workflow => $path) {
            SettingsService::resetCache();

            $response = $this->measure($workflow, fn () => $this->get($path));

            $response->assertOk();
        }
    }

    public function test_customer_login_performance(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->measure('customer_login', fn () => $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]));

        $response->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
    }

    public function test_seller_login_performance(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['role' => 'seller']);

        $response = $this->measure('seller_login', fn () => $this->post(route('login.store'), [
            'email' => $seller->email,
            'password' => 'password',
        ]));

        $response->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
    }

    public function test_admin_login_performance_stops_at_otp_challenge(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->measure('admin_login_first_factor', fn () => $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]));

        $response->assertRedirect(route('admin.two-factor'));
        $this->assertGuest();
    }

    public function test_customer_registration_performance_and_seller_registration_is_disabled(): void
    {
        Mail::fake();

        $response = $this->measure('customer_registration', fn () => $this->post(route('register.store'), [
            'name' => 'Customer Performance Test',
            'email' => 'customer-'.uniqid('', true).'@example.test',
            'password' => 'Performance-Test-Password-12!',
            'password_confirmation' => 'Performance-Test-Password-12!',
        ]));

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('login.otp.show'));
        $this->assertGuest();

        $email = $response->getSession()->get('auth.email_otp');
        $registeredUser = User::query()->findOrFail($email['user_id']);
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($registeredUser, &$code): bool {
            $code = $mail->otp;

            return $mail->hasTo($registeredUser->email);
        });
        $this->post(route('login.otp.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($registeredUser);
        $this->post(route('logout'));

        $this->post(route('register.store'), [
            'name' => 'Seller Performance Test',
            'email' => 'seller-'.uniqid('', true).'@example.test',
            'password' => 'Performance-Test-Password-12!',
            'password_confirmation' => 'Performance-Test-Password-12!',
            'role' => 'seller',
        ])->assertSessionHasErrors('role');

        $this->assertGuest();
    }

    public function test_profile_update_performance_for_all_roles(): void
    {
        foreach (['customer', 'seller', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $email = "{$role}-updated@example.test";

            $response = $this->measure("{$role}_profile_update", fn () => Livewire::actingAs($user)
                ->test('pages::settings.profile')
                ->set('name', ucfirst($role).' Updated')
                ->set('email', $email)
                ->call('updateProfileInformation'));

            $response->assertHasNoErrors();
            $this->assertSame($email, $user->refresh()->email);
        }
    }

    public function test_product_create_and_update_performance(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($staff);
        $category = ProductCategory::create([
            'name' => 'Performance Test Category',
            'slug' => 'performance-test-category',
            'is_active' => true,
        ]);

        $createResponse = $this->measure('product_create', fn () => $this->actingAs($staff)
            ->post(route('admin.products.store'), [
                'name' => 'Performance Test Product',
                'product_category_id' => $category->id,
                'sku' => 'PERF-TEST-001',
                'description' => 'Temporary performance test record.',
                'price' => '24.50',
                'stock_quantity' => 50,
                'low_stock_threshold' => 5,
                'is_published' => '1',
            ]));

        $createResponse->assertRedirect(route('admin.products.index'));
        $product = Product::query()->where('sku', 'PERF-TEST-001')->firstOrFail();

        $updateResponse = $this->measure('product_update', fn () => $this->put(
            route('admin.products.update', $product),
            [
                'name' => 'Updated Performance Test Product',
                'product_category_id' => $category->id,
                'sku' => 'PERF-TEST-001',
                'description' => 'Updated temporary performance test record.',
                'price' => '25.00',
                'stock_quantity' => 45,
                'low_stock_threshold' => 5,
                'is_published' => '1',
            ]
        ));

        $updateResponse->assertRedirect(route('admin.products.index'));
        $this->assertSame('Updated Performance Test Product', $product->refresh()->name);
    }

    public function test_shop_and_brand_settings_update_performance(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $shopOwner = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($shopOwner);

        $shopResponse = $this->measure('shop_settings_update', fn () => $this->actingAs($shopOwner)
            ->put(route('admin.settings.shop.update'), [
                'currency' => 'USD',
                'currency_symbol' => '$',
                'delivery_fee' => '5.00',
                'free_shipping_threshold' => '50.00',
                'cod_enabled' => '1',
                'store_address' => 'Performance test address',
                'customer_support_phone' => '+1 555 0100',
                'tax_percentage' => '0',
            ]));

        $shopResponse->assertRedirect(route('admin.settings.shop'));
        $this->assertSame('USD', Settings::get('shop.currency'));

        $brandResponse = $this->measure('brand_settings_update', fn () => $this->actingAs($admin)->put(route('admin.brand.update'), [
            'brand_name' => 'PawHaven',
            'brand_tagline' => 'Find Pets. Give Hope. Shop with Love.',
            'brand_primary_color' => '#245C45',
            'brand_secondary_color' => '#173F32',
            'brand_accent_color' => '#F28C75',
            'brand_contact_email' => 'support@example.test',
            'brand_phone' => '',
            'brand_address' => '',
            'brand_social_facebook' => '',
            'brand_social_instagram' => '',
            'brand_social_x' => '',
            'brand_meta_description' => 'Performance test metadata.',
        ]));

        $brandResponse->assertRedirect(route('admin.brand.index'));
        $this->assertSame('PawHaven', Settings::get('brand.name'));

        $createdAt = DB::table('settings')->where('key', 'shop.currency')->value('created_at');
        Settings::setMany(['shop.currency' => 'CAD'], 'shop');

        $this->assertSame('CAD', Settings::get('shop.currency'));
        $this->assertSame(1, DB::table('settings')->where('key', 'shop.currency')->count());
        $this->assertSame($createdAt, DB::table('settings')->where('key', 'shop.currency')->value('created_at'));
    }

    private function measure(string $workflow, callable $operation): mixed
    {
        $this->currentMeasurement = [
            'database_queries' => 0,
            'database_ms' => 0.0,
        ];
        $startedAt = hrtime(true);

        try {
            return $operation();
        } finally {
            $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
            $metrics = $this->currentMeasurement;
            $this->currentMeasurement = null;

            fwrite(STDOUT, 'PAWHAVEN_PERF '.json_encode([
                'workflow' => $workflow,
                'laravel_ms' => round($elapsedMs, 2),
                'database_queries' => $metrics['database_queries'],
                'database_ms' => round($metrics['database_ms'], 2),
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }
}
