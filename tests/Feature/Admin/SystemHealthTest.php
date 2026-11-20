<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_system_health(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.system.index'));

        $response->assertOk();
        $response->assertSee('System & Security Health');
        $response->assertSee('Application Stack');
        $response->assertSee('Database Engine');
        $response->assertSee('Media Storage');
        $response->assertSee('Application Schema');
        $response->assertSee('Migration History');
        $response->assertSee('Operational Readiness Notes');
    }

    public function test_staff_cannot_access_system_health(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($staff)->get(route('admin.system.index'));

        $response->assertForbidden();
    }

    public function test_customer_cannot_access_system_health(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer)->get(route('admin.system.index'));

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_from_system_health(): void
    {
        $response = $this->get(route('admin.system.index'));

        $response->assertRedirect(route('admin.login'));
    }
}
