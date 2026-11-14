<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class DashboardOrderStatusTest extends TestCase
{
    use CreatesBusinessMemberships;
    use RefreshDatabase;

    public function test_dashboard_renders_every_existing_order_status_and_badge_style(): void
    {
        $owner = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($owner);
        $companyId = Company::query()->withoutGlobalScopes()->sole()->id;

        foreach (OrderStatus::cases() as $index => $status) {
            Order::create([
                'company_id' => $companyId,
                'order_number' => 'STATUS-TEST-'.$index,
                'customer_name' => 'Test Customer',
                'customer_email' => 'customer@example.com',
                'shipping_address' => '123 Test Street',
                'shipping_city' => 'Seattle',
                'status' => $status,
                'payment_method' => 'cod',
                'payment_status' => PaymentStatus::Pending,
                'subtotal_minor' => 1000,
                'total_minor' => 1000,
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
        }

        $response = $this->actingAs($owner)->get(route('admin.dashboard'));

        $response->assertOk();

        foreach (OrderStatus::cases() as $status) {
            $response->assertSee($status->label());
            $response->assertSee($status->badgeClasses());
            $this->assertNotSame('', $status->badgeClasses());
        }
    }
}
