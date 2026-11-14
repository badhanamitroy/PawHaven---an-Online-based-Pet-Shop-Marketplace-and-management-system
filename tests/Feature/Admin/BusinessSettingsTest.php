<?php

namespace Tests\Feature\Admin;

use App\Facades\Settings;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBusinessMemberships;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBusinessMemberships;

    public function test_shop_owner_can_update_shop_and_delivery_settings(): void
    {
        $owner = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($owner);

        $response = $this->actingAs($owner)->put(route('admin.settings.shop.update'), [
            'currency' => 'EUR',
            'currency_symbol' => '€',
            'delivery_fee' => '6.50',
            'free_shipping_threshold' => '55.00',
            'cod_enabled' => '1',
            'store_address' => '400 Pine St, Seattle, WA',
            'customer_support_phone' => '+1 (555) 019-2834',
            'tax_percentage' => '8.5',
        ]);

        $response->assertRedirect(route('admin.settings.shop'));
        $response->assertSessionHas('success');

        $this->assertSame('EUR', Settings::get('shop.currency'));
        $this->assertSame(650, Settings::get('shop.delivery_fee_minor'));
        $this->assertSame(5500, Settings::get('shop.free_shipping_threshold_minor'));
        $this->assertTrue(Settings::get('shop.cod_enabled'));
    }

    public function test_admin_can_update_platform_feature_flags_but_only_owner_can_manage_branches(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Toggle feature flags
        $response = $this->actingAs($admin)->put(route('admin.settings.features.update'), [
            'multi_staff_enabled' => '1',
            'multi_branch_enabled' => '1',
        ]);

        $response->assertRedirect(route('admin.settings.features'));
        $this->assertTrue(Settings::get('features.multi_staff_enabled'));
        $this->assertTrue(Settings::get('features.multi_branch_enabled'));

        // Platform Admin has no implicit Shop Owner authority.
        $adminBranchResponse = $this->actingAs($admin)->post(route('admin.settings.branches.store'), [
            'name' => 'Downtown Flagship Depot',
            'code' => 'br-sea-01',
            'address' => '1200 4th Ave',
            'city' => 'Seattle',
            'phone' => '+1 (555) 321-9876',
            'is_active' => '1',
        ]);

        $adminBranchResponse->assertForbidden();
        $this->assertDatabaseMissing('branches', ['code' => 'BR-SEA-01']);

        $owner = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $this->grantCompanyWideShopAccess($owner);
        $branchResponse = $this->actingAs($owner)->post(route('admin.settings.branches.store'), [
            'name' => 'Downtown Flagship Depot',
            'code' => 'br-sea-01',
            'address' => '1200 4th Ave',
            'city' => 'Seattle',
            'phone' => '+1 (555) 321-9876',
            'is_active' => '1',
        ]);

        $branchResponse->assertRedirect(route('admin.settings.features'));
        $this->assertDatabaseHas('branches', [
            'code' => 'BR-SEA-01',
            'name' => 'Downtown Flagship Depot',
        ]);

        $branch = Branch::where('code', 'BR-SEA-01')->firstOrFail();

        // Delete branch
        $deleteResponse = $this->actingAs($owner)->delete(route('admin.settings.branches.destroy', $branch));
        $deleteResponse->assertRedirect(route('admin.settings.features'));
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_platform_admin_cannot_assign_employee_roles_through_the_generic_user_editor(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        // Employee identities are assigned by the Shop Owner membership workflow.
        $promoteResponse = $this->actingAs($admin)->patch(route('admin.users.update-role', $customer), [
            'role' => 'staff',
        ]);
        $promoteResponse->assertRedirect(route('admin.users.index'));
        $promoteResponse->assertSessionHas('error');
        $customer->refresh();
        $this->assertSame('customer', $customer->role);

        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $staffRoleResponse = $this->actingAs($admin)->patch(route('admin.users.update-role', $staff), [
            'role' => 'customer',
        ]);
        $staffRoleResponse->assertSessionHas('error');
        $this->assertSame('staff', $staff->fresh()->role);

        // Admin cannot demote themselves.
        $selfDemoteResponse = $this->actingAs($admin)->patch(route('admin.users.update-role', $admin), [
            'role' => 'customer',
        ]);
        $selfDemoteResponse->assertSessionHas('error');
        $admin->refresh();
        $this->assertSame('admin', $admin->role);
    }
}
