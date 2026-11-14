<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_access_seller_dashboard(): void
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($seller)->get(route('seller.dashboard'));

        $response->assertOk();
        $response->assertSee('Seller Portal');
        $response->assertSee('Total Listings');
        $response->assertSee('Live & Published');
        $response->assertSee('Pending Review');
    }

    public function test_customer_cannot_access_seller_dashboard(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer)->get(route('seller.dashboard'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_seller_login_from_seller_dashboard(): void
    {
        $response = $this->get(route('seller.dashboard'));

        $response->assertRedirect(route('seller.login'));
    }
}
