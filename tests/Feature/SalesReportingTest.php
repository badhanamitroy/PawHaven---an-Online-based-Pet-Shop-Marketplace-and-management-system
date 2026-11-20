<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesReportingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $this->owner = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $this->owner->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    public function test_employee_seller_sees_only_own_sales_and_cannot_open_another_employees_order(): void
    {
        $branch = $this->createBranch('REPORT-SELLER');
        $seller = $this->createStaffMember($branch, 'seller');
        $otherSeller = $this->createStaffMember($branch, 'seller');
        $ownRecent = $this->createOrder($branch, $seller, [
            'order_number' => 'REPORT-OWN-RECENT',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'sales_channel' => SalesChannel::PointOfSale,
            'total_minor' => 1250,
            'created_at' => now()->subDay(),
        ]);
        $ownOld = $this->createOrder($branch, $seller, [
            'order_number' => 'REPORT-OWN-OLD',
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'sales_channel' => SalesChannel::Online,
            'total_minor' => 2500,
            'created_at' => now()->subDays(30),
        ]);
        $otherSale = $this->createOrder($branch, $otherSeller, [
            'order_number' => 'REPORT-OTHER-SELLER',
            'sales_channel' => SalesChannel::PointOfSale,
            'total_minor' => 9000,
        ]);

        $this->actingAs($seller)->get(route('admin.sales-reports.my'))
            ->assertOk()->assertSee($ownRecent->order_number)->assertSee($ownOld->order_number)
            ->assertDontSee($otherSale->order_number)->assertSee('POS')->assertSee('Online');

        $this->get(route('admin.sales-reports.my.show', $otherSale->id))->assertNotFound();
        $this->get(route('admin.sales-reports.branch'))->assertForbidden();
        $this->get(route('admin.sales-reports.business'))->assertForbidden();
        $this->get(route('admin.sales-reports.my', ['from' => now()->subDays(2)->toDateString(), 'to' => now()->toDateString(), 'status' => 'processing', 'channel' => 'pos']))
            ->assertOk()->assertSee($ownRecent->order_number)->assertDontSee($ownOld->order_number)->assertDontSee($otherSale->order_number);
    }

    public function test_branch_manager_report_is_limited_to_the_assigned_branch(): void
    {
        $branch = $this->createBranch('REPORT-MANAGER');
        $otherBranch = $this->createBranch('REPORT-MANAGER-OTHER');
        $manager = $this->createStaffMember($branch, 'manager');
        $seller = $this->createStaffMember($branch, 'seller');
        $otherSeller = $this->createStaffMember($otherBranch, 'seller');
        $branchSale = $this->createOrder($branch, $seller, [
            'order_number' => 'REPORT-BRANCH-LOCAL',
            'sales_channel' => SalesChannel::PointOfSale,
        ]);
        $otherSale = $this->createOrder($otherBranch, $otherSeller, [
            'order_number' => 'REPORT-BRANCH-REMOTE',
            'sales_channel' => SalesChannel::Online,
        ]);

        $this->actingAs($manager)->get(route('admin.sales-reports.branch', ['branch_id' => $otherBranch->id]))
            ->assertOk()->assertSee($branchSale->order_number)->assertSee($seller->name)
            ->assertDontSee($otherSale->order_number)->assertDontSee($otherBranch->name);
        $this->get(route('admin.sales-reports.business'))->assertForbidden();
        $this->get(route('admin.orders.show', $branchSale))->assertOk();
        $this->get(route('admin.orders.show', $otherSale))->assertNotFound();
    }

    public function test_shop_owner_sees_company_branches_employee_attribution_and_unattributed_online_orders(): void
    {
        $branchA = $this->createBranch('REPORT-OWNER-A');
        $branchB = $this->createBranch('REPORT-OWNER-B');
        $sellerA = $this->createStaffMember($branchA, 'seller');
        $sellerB = $this->createStaffMember($branchB, 'seller');
        $branchASale = $this->createOrder($branchA, $sellerA, [
            'order_number' => 'REPORT-OWNER-POS',
            'sales_channel' => SalesChannel::PointOfSale,
            'total_minor' => 1000,
        ]);
        $branchBSale = $this->createOrder($branchB, $sellerB, [
            'order_number' => 'REPORT-OWNER-BRANCH-B',
            'sales_channel' => SalesChannel::Online,
            'total_minor' => 3000,
        ]);
        $unattributedOnline = $this->createOrder($branchB, null, [
            'order_number' => 'REPORT-OWNER-UNATTRIBUTED-ONLINE',
            'sales_channel' => SalesChannel::Online,
            'total_minor' => 2000,
        ]);

        $this->actingAs($this->owner)->get(route('admin.sales-reports.business'))
            ->assertOk()->assertSee($branchASale->order_number)->assertSee($branchBSale->order_number)
            ->assertSee($unattributedOnline->order_number)
            ->assertSee($branchA->name)->assertSee($branchB->name)
            ->assertSee($sellerA->name)->assertSee($sellerB->name)
            ->assertSee('Unattributed order')->assertSee('Online')->assertSee('POS');
        $this->get(route('admin.sales-reports.branch'))->assertForbidden();
    }

    public function test_legacy_paid_status_and_cancelled_values_are_not_reported_as_collected_revenue(): void
    {
        $branch = $this->createBranch('REPORT-FINANCIAL');
        $seller = $this->createStaffMember($branch, 'seller');
        $pendingOrder = $this->createOrder($branch, $seller, [
            'order_number' => 'REPORT-PENDING-ORDER',
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'total_minor' => 1000,
        ]);
        $cancelledOrder = $this->createOrder($branch, $seller, [
            'order_number' => 'REPORT-CANCELLED-ORDER',
            'status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Paid,
            'total_minor' => 500,
        ]);

        $this->actingAs($this->owner)->get(route('admin.sales-reports.business'))
            ->assertOk()->assertSee($pendingOrder->order_number)->assertSee($cancelledOrder->order_number)
            ->assertSee('Outstanding order balance')->assertSee('USD 10.00')
            ->assertSee('Cancelled order value')->assertSee('USD 5.00')
            ->assertSee('Gross verified collections')->assertSee('Failed / reversed payments')
            ->assertSee('Legacy order payment labels are not used to calculate collections');
        $reports = app(\App\Services\SalesReportingService::class);
        $membership = $reports->membershipFor($this->owner, \App\Services\BusinessAccessService::OWNER);
        $summary = $reports->summary($reports->companyOrders($membership));
        $this->assertSame(0, $summary['verified_collected_minor']);
        $this->assertSame(1000, $summary['outstanding_minor'], 'A legacy Paid order without a verified transaction remains outstanding in the new reporting model.');
    }

    public function test_marketplace_seller_cannot_access_employee_sales_reports(): void
    {
        $marketplaceSeller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);

        $this->actingAs($marketplaceSeller)->get(route('admin.sales-reports.my'))->assertForbidden();
        $this->get(route('admin.sales-reports.branch'))->assertForbidden();
        $this->get(route('admin.sales-reports.business'))->assertForbidden();

        $platformAdmin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($platformAdmin)->get(route('admin.sales-reports.business'))->assertForbidden();
    }

    private function createBranch(string $code): Branch
    {
        return Branch::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Branch '.$code,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function createStaffMember(Branch $branch, string $role): User
    {
        $user = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function createOrder(Branch $branch, ?User $employee, array $overrides = []): Order
    {
        $data = array_merge([
            'order_number' => 'REPORT-'.strtoupper(Str::random(10)),
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'customer_name' => 'Report Customer',
            'customer_email' => 'report-customer@example.test',
            'customer_phone' => '5550100',
            'shipping_address' => '1 Reporting Way',
            'shipping_city' => 'Report City',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 1000,
            'delivery_fee_minor' => 0,
            'total_minor' => 1000,
        ], $overrides);
        $createdAt = $data['created_at'] ?? now();
        unset($data['created_at'], $data['sales_channel']);

        $this->actingAs($this->owner);
        $order = Order::create($data);
        $order->forceFill([
            'employee_seller_id' => $employee?->id,
            'sales_channel' => $overrides['sales_channel'] ?? SalesChannel::Online,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order;
    }
}
