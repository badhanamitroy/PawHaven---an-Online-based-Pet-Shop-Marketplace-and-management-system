<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\BusinessAccessService;
use App\Services\BusinessMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessAccessIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_only_sees_inventory_for_their_branch(): void
    {
        $company = $this->installationCompany();
        $branchA = $this->createBranch($company, 'NORTH-01', 'North Branch');
        $branchA2 = $this->createBranch($company, 'NORTH-02', 'North Annex');
        $owner = $this->createOwner($company);

        $category = $this->createCategory($company, 'branch-supplies', 'Branch Supplies');
        $this->actingAs($owner);
        $this->createProduct($company, $branchA, $category, 'North Branch Product');
        $this->createProduct($company, $branchA2, $category, 'North Annex Product');

        $manager = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->assign($manager, $company, $branchA, 'manager');

        $response = $this->actingAs($manager)->get(route('admin.inventory.index'));

        $response->assertOk()
            ->assertSee('North Branch Product')
            ->assertDontSee('North Annex Product');
    }

    public function test_shop_owner_can_see_products_across_all_branches_in_the_installation_company(): void
    {
        $company = $this->installationCompany();
        $branchA = $this->createBranch($company, 'OWNER-01', 'Owner Branch A');
        $branchB = $this->createBranch($company, 'OWNER-02', 'Owner Branch B');
        $owner = $this->createOwner($company);
        $category = $this->createCategory($company, 'owner-supplies', 'Owner Supplies');

        $this->actingAs($owner);
        $this->createProduct($company, $branchA, $category, 'Owner Branch A Product');
        $this->createProduct($company, $branchB, $category, 'Owner Branch B Product');

        $this->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('Owner Branch A Product')
            ->assertSee('Owner Branch B Product');
    }

    public function test_manager_and_employee_seller_cannot_modify_products_categories_or_inventory(): void
    {
        $company = $this->installationCompany();
        $branch = $this->createBranch($company, 'LIMIT-01', 'Limited Branch');
        $otherBranch = $this->createBranch($company, 'LIMIT-02', 'Other Limited Branch');
        $owner = $this->createOwner($company);
        $manager = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $seller = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->assign($manager, $company, $branch, 'manager');
        $this->assign($seller, $company, $branch, 'seller');
        $category = $this->createCategory($company, 'limited-supplies', 'Limited Supplies');

        $this->actingAs($owner);
        $product = $this->createProduct($company, $branch, $category, 'Limited Product');
        $otherBranchProduct = $this->createProduct($company, $otherBranch, $category, 'Other Branch Product');

        $this->actingAs($manager)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Limited Product')
            ->assertDontSee('Other Branch Product');
        $this->get(route('admin.products.edit', $otherBranchProduct))->assertNotFound();
        $this->patch(route('admin.inventory.update', $otherBranchProduct), [])->assertNotFound();
        $this->post(route('admin.products.store'), [])->assertForbidden();
        $this->put(route('admin.products.update', $product), [])->assertForbidden();
        $this->patch(route('admin.inventory.update', $product), [])->assertForbidden();
        $this->put(route('admin.categories.update', $category), [])->assertForbidden();

        $this->actingAs($manager);
        try {
            Product::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'product_category_id' => $category->id,
                'name' => 'Model Bypass Product',
                'slug' => 'model-bypass-product',
                'sku' => 'MODEL-BYPASS-PRODUCT',
                'price_minor' => 1000,
                'stock_quantity' => 1,
                'low_stock_threshold' => 0,
            ]);
            $this->fail('Branch Manager model creation must not bypass catalogue authorization.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('products', ['sku' => 'MODEL-BYPASS-PRODUCT']);
        }

        $this->actingAs($seller)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Limited Product')
            ->assertDontSee('Other Branch Product')
            ->assertDontSee('Add Product')
            ->assertDontSee(route('admin.products.edit', $product));
        $this->get(route('admin.products.create'))->assertForbidden();
        $this->post(route('admin.products.store'), [])->assertForbidden();
        $this->get(route('admin.products.edit', $product))->assertForbidden();
        $this->put(route('admin.products.update', $product), [])->assertForbidden();
        $this->delete(route('admin.products.destroy', $product))->assertForbidden();
        $this->put(route('admin.categories.update', $category), [])->assertForbidden();
        $this->get(route('admin.inventory.index'))->assertForbidden();
        $this->get(route('admin.business.index'))->assertForbidden();
        $this->patch(route('admin.inventory.update', $product), [])->assertForbidden();
    }

    public function test_unassigned_staff_cannot_enter_shop_operations(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);

        $this->actingAs($staff)
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    public function test_branch_manager_cannot_open_an_order_assigned_to_another_branch(): void
    {
        $company = $this->installationCompany();
        $branchA = $this->createBranch($company, 'ORDER-01', 'Order Branch A');
        $branchB = $this->createBranch($company, 'ORDER-02', 'Order Branch B');
        $owner = $this->createOwner($company);
        $manager = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->assign($manager, $company, $branchA, 'manager');

        $this->actingAs($owner);
        $order = Order::create([
            'company_id' => $company->id,
            'branch_id' => $branchB->id,
            'order_number' => 'PH-BRANCH-ORDER',
            'customer_name' => 'Demo Customer',
            'customer_email' => 'customer@example.test',
            'shipping_address' => '1 Example Road',
            'shipping_city' => 'Example City',
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal_minor' => 1000,
            'delivery_fee_minor' => 0,
            'total_minor' => 1000,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.orders.show', $order))
            ->assertNotFound();
    }

    public function test_branch_manager_cannot_assign_memberships_even_within_their_branch(): void
    {
        $company = $this->installationCompany();
        $branch = $this->createBranch($company, 'MANAGER-01', 'Manager Branch');
        $this->createOwner($company);
        $manager = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $target = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $this->assign($manager, $company, $branch, 'manager');

        $this->actingAs($manager)
            ->post(route('admin.business.memberships.store'), [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'user_id' => $target->id,
                'role' => 'seller',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('business_memberships', [
            'user_id' => $target->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_branch_manager_employee_directory_is_limited_to_assigned_branch(): void
    {
        $company = $this->installationCompany();
        $branchA = $this->createBranch($company, 'DIR-01', 'Directory Branch A');
        $branchB = $this->createBranch($company, 'DIR-02', 'Directory Branch B');
        $this->createOwner($company);
        $manager = User::factory()->create(['name' => 'Assigned Branch Manager', 'role' => 'staff']);
        $assignedSeller = User::factory()->create(['name' => 'Assigned Branch Seller', 'role' => 'staff']);
        $otherManager = User::factory()->create(['name' => 'Other Branch Manager', 'role' => 'staff']);
        $this->assign($manager, $company, $branchA, 'manager');
        $this->assign($assignedSeller, $company, $branchA, 'seller');
        $this->assign($otherManager, $company, $branchB, 'manager');

        $this->actingAs($manager)
            ->get(route('admin.business.index'))
            ->assertOk()
            ->assertSee('Assigned Branch Seller')
            ->assertDontSee('Other Branch Manager');
    }

    public function test_database_rejects_a_second_installation_company(): void
    {
        $company = $this->installationCompany();
        $branch = $this->createBranch($company, 'AMB-01', 'Ambiguity Branch');
        $this->createOwner($company);
        $manager = User::factory()->create(['role' => 'staff']);
        $this->assign($manager, $company, $branch, 'manager');

        try {
            DB::table('companies')->insert([
                'name' => 'Unexpected Company',
                'slug' => 'unexpected-company',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The company singleton constraint must reject a second company row.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('companies')->count());
        }

        $this->assertTrue(app(BusinessAccessService::class)->hasBusinessAccess($manager));
    }

    public function test_employee_cannot_have_a_second_active_branch_assignment(): void
    {
        $company = $this->installationCompany();
        $branchA = $this->createBranch($company, 'ASSIGN-01', 'Assignment Branch A');
        $branchB = $this->createBranch($company, 'ASSIGN-02', 'Assignment Branch B');
        $owner = $this->createOwner($company);
        $manager = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        $membership = $this->assign($manager, $company, $branchA, 'manager');

        try {
            app(BusinessMembershipService::class)->assign($owner, $manager, $company, $branchB->id, 'seller');
            $this->fail('A second active branch assignment should be rejected.');
        } catch (ValidationException) {
            $this->assertSame($branchA->id, $membership->fresh()->branch_id);
            $this->assertSame('manager', $membership->fresh()->role);
        }
    }

    public function test_only_initial_setup_can_assign_the_single_shop_owner(): void
    {
        $company = $this->installationCompany();
        $owner = $this->createOwner($company);
        $secondOwner = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);

        $this->expectException(AuthorizationException::class);
        app(BusinessMembershipService::class)->assign($owner, $secondOwner, $company, null, 'owner');
    }

    public function test_platform_admin_can_assign_the_first_owner_during_initial_setup_only(): void
    {
        $company = $this->installationCompany();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.business.memberships.store'), [
                'company_id' => $company->id,
                'branch_id' => '',
                'user_id' => $customer->id,
                'role' => 'owner',
            ])
            ->assertRedirect(route('admin.business.index'));

        $this->assertSame('staff', $customer->fresh()->role);
        $this->assertDatabaseHas('business_memberships', [
            'user_id' => $customer->id,
            'company_id' => $company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $this->assertFalse(app(BusinessAccessService::class)->hasBusinessAccess($admin));
    }

    public function test_inactive_branch_cannot_receive_an_active_employee_membership(): void
    {
        $company = $this->installationCompany();
        $branch = $this->createBranch($company, 'INACTIVE-01', 'Inactive Branch');
        $branch->update(['is_active' => false]);
        $employee = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->assign($employee, $company, $branch, 'manager');
    }

    private function installationCompany(): Company
    {
        return Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
    }

    private function createBranch(Company $company, string $code, string $name): Branch
    {
        return Branch::create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'address' => '1 Example Road',
            'city' => 'Example City',
            'is_active' => true,
        ]);
    }

    private function assign(User $user, Company $company, Branch $branch, string $role): BusinessMembership
    {
        return BusinessMembership::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createOwner(Company $company): User
    {
        $owner = User::factory()->create([
            'name' => 'Shop Owner',
            'email' => 'owner@example.test',
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        BusinessMembership::create([
            'user_id' => $owner->id,
            'company_id' => $company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);

        return $owner;
    }

    private function createCategory(Company $company, string $slug, string $name): ProductCategory
    {
        return ProductCategory::create([
            'company_id' => $company->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createProduct(
        Company $company,
        Branch $branch,
        ProductCategory $category,
        string $name,
    ): Product {
        return Product::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => $name,
            'slug' => str()->slug($name),
            'sku' => strtoupper(str()->slug($name)),
            'price_minor' => 1000,
            'stock_quantity' => 5,
            'low_stock_threshold' => 1,
            'is_published' => true,
            'is_featured' => false,
        ]);
    }
}
