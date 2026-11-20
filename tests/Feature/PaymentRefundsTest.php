<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentSource;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentRefundTransaction;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\BusinessAccessService;
use App\Services\PaymentTransactionService;
use App\Services\SalesReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentRefundsTest extends TestCase
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
            'role' => BusinessAccessService::OWNER,
            'is_active' => true,
        ]);
    }

    public function test_partial_refund_is_reserved_then_completed_by_a_different_manager_and_reported_net(): void
    {
        $branch = $this->createBranch('REFUND-PARTIAL');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $requester = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $approver = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $order = $this->createOrder($branch, $seller, $customer, 5000);
        $payment = $this->verifiedPayment($order, $requester, 5000);
        $key = (string) Str::uuid();

        $this->actingAs($customer)->post(route('orders.refunds.store', $order), [
            'payment_transaction_id' => $payment->id,
            'refund_submission_key' => $key,
            'refund_amount' => '20.00',
            'reason' => 'One item was returned.',
        ])->assertRedirect();

        $refund = PaymentRefundTransaction::query()->sole();
        $this->assertSame(PaymentRefundStatus::Requested, $refund->status);
        $this->assertNull($refund->approved_by_user_id);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status, 'A request alone must not alter the order payment state.');

        $this->actingAs($approver)->post(route('admin.refunds.complete', $refund), [
            'approval_note' => 'Returned cash to the customer; counter slip 41.',
        ])->assertRedirect(route('admin.orders.show', $order));

        $refund->refresh();
        $this->assertSame(PaymentRefundStatus::Refunded, $refund->status);
        $this->assertSame($approver->id, $refund->approved_by_user_id);
        $this->assertNotNull($refund->approved_at);
        $this->assertNotNull($refund->refunded_at);
        $this->assertSame(PaymentTransactionStatus::Verified, $payment->fresh()->status, 'Refunds never overwrite original payment evidence.');
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_refund_events', [
            'payment_refund_transaction_id' => $refund->id,
            'from_status' => 'requested',
            'to_status' => 'refunded',
            'actor_user_id' => $approver->id,
        ]);

        $summary = app(SalesReportingService::class)->summary(
            app(SalesReportingService::class)->companyOrders(
                app(SalesReportingService::class)->membershipFor($this->owner, BusinessAccessService::OWNER),
            ),
        );
        $this->assertSame(5000, $summary['gross_verified_collections_minor']);
        $this->assertSame(2000, $summary['successful_refunds_minor']);
        $this->assertSame(3000, $summary['net_collected_minor']);
        $this->assertSame(2000, $summary['outstanding_minor']);
    }

    public function test_full_refund_updates_order_only_after_completion_and_preserves_history(): void
    {
        $branch = $this->createBranch('REFUND-FULL');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $order = $this->createOrder($branch, $seller, $customer, 5000);
        $payment = $this->verifiedPayment($order, $manager, 5000);
        $payload = [
            'payment_transaction_id' => $payment->id,
            'refund_submission_key' => (string) Str::uuid(),
            'refund_amount' => '50.00',
            'reason' => 'Full refund requested by the Shop Owner.',
        ];
        $this->actingAs($manager)->post(route('admin.orders.refunds.store', $order), $payload)->assertForbidden();
        $this->assertDatabaseCount('payment_refund_transactions', 0);

        $this->actingAs($this->owner)->post(route('admin.orders.refunds.store', $order), $payload)->assertRedirect();
        $refund = PaymentRefundTransaction::query()->sole();

        $this->actingAs($this->owner)->post(route('admin.refunds.complete', $refund), [
            'approval_note' => 'Self-approval attempt.',
        ])->assertForbidden();
        $this->assertSame(PaymentRefundStatus::Requested, $refund->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $this->actingAs($manager)->post(route('admin.refunds.complete', $refund), [
            'approval_note' => 'Refund sent; transfer reference recorded in branch book.',
        ])->assertRedirect();

        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
        $this->assertSame(5000, $payment->fresh()->amount_minor);
        $this->assertSame('verified', $payment->fresh()->status->value);
        $this->assertSame(2, $refund->events()->count());
    }

    public function test_refund_cannot_exceed_verified_collection_and_duplicate_request_is_idempotent(): void
    {
        $branch = $this->createBranch('REFUND-LIMIT');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $order = $this->createOrder($branch, $seller, $customer, 5000);
        $payment = $this->verifiedPayment($order, $manager, 3000);

        $this->actingAs($customer)->post(route('orders.refunds.store', $order), [
            'payment_transaction_id' => $payment->id,
            'refund_submission_key' => (string) Str::uuid(),
            'refund_amount' => '30.01',
            'reason' => 'Over the amount collected.',
        ])->assertSessionHasErrors('refund_amount');
        $this->assertDatabaseCount('payment_refund_transactions', 0);

        $key = (string) Str::uuid();
        $payload = [
            'payment_transaction_id' => $payment->id,
            'refund_submission_key' => $key,
            'refund_amount' => '20.00',
            'reason' => 'Partial return.',
        ];
        $this->post(route('orders.refunds.store', $order), $payload)->assertRedirect();
        $first = PaymentRefundTransaction::query()->sole();
        $this->post(route('orders.refunds.store', $order), $payload)->assertRedirect();
        $this->assertDatabaseCount('payment_refund_transactions', 1);
        $this->assertDatabaseCount('payment_refund_events', 1);
        $this->assertSame($first->id, PaymentRefundTransaction::query()->sole()->id);
    }

    public function test_pending_and_failed_payments_cannot_be_refunded_as_collected_money(): void
    {
        $branch = $this->createBranch('REFUND-UNVERIFIED');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $order = $this->createOrder($branch, $seller, $customer, 5000);

        $this->actingAs($manager);
        $payments = app(PaymentTransactionService::class);
        $pending = $payments->recordBusinessPayment($order, $manager, PaymentMethod::Cash, 2000, null, null, (string) Str::uuid());
        $this->actingAs($customer)->post(route('orders.refunds.store', $order), [
            'payment_transaction_id' => $pending->id,
            'refund_submission_key' => (string) Str::uuid(),
            'refund_amount' => '10.00',
            'reason' => 'Payment was not completed.',
        ])->assertSessionHasErrors('payment');

        $this->actingAs($this->owner);
        $payments->verify($pending, $this->owner, true, 'No corresponding cash received.');
        $this->actingAs($customer)->post(route('orders.refunds.store', $order), [
            'payment_transaction_id' => $pending->id,
            'refund_submission_key' => (string) Str::uuid(),
            'refund_amount' => '10.00',
            'reason' => 'Failed payments are not refundable.',
        ])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payment_refund_transactions', 0);
    }

    public function test_manager_cannot_review_a_refund_for_another_branch(): void
    {
        $branchA = $this->createBranch('REFUND-SCOPE-A');
        $branchB = $this->createBranch('REFUND-SCOPE-B');
        $seller = $this->createEmployee($branchA, BusinessAccessService::EMPLOYEE_SELLER);
        $requester = $this->createEmployee($branchA, BusinessAccessService::MANAGER);
        $foreignManager = $this->createEmployee($branchB, BusinessAccessService::MANAGER);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $order = $this->createOrder($branchA, $seller, $customer, 5000);
        $payment = $this->verifiedPayment($order, $requester, 5000);
        $refund = $this->requestAsCustomer($customer, $payment, $order, '10.00');

        $this->actingAs($foreignManager)->post(route('admin.refunds.complete', $refund), [
            'approval_note' => 'Wrong branch.',
        ])->assertForbidden();
        $this->assertSame(PaymentRefundStatus::Requested, $refund->fresh()->status);
        $this->assertNull($refund->fresh()->approved_by_user_id);
    }

    public function test_shop_owner_reviews_branchless_online_refund_and_manager_cannot(): void
    {
        $branch = $this->createBranch('REFUND-BRANCHLESS-OTHER');
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($this->owner);
        $order = Order::create([
            'company_id' => $this->company->id,
            'user_id' => $customer->id,
            'order_number' => 'REFUND-ONLINE-'.strtoupper(Str::random(8)),
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'shipping_address' => '1 Online Lane',
            'shipping_city' => 'Online City',
            'status' => OrderStatus::Pending,
            'payment_method' => 'online',
            'payment_status' => PaymentStatus::Paid,
            'subtotal_minor' => 5000,
            'delivery_fee_minor' => 0,
            'total_minor' => 5000,
        ]);
        // Simulate an already verified external/manual payment record; gateway work is out of scope.
        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'payment_method' => PaymentMethod::OnlineGateway,
            'amount_minor' => 5000,
            'currency' => $order->currency,
            'status' => PaymentTransactionStatus::Verified,
            'source' => PaymentSource::Online,
            'recorded_by_user_id' => $manager->id,
            'verified_by_user_id' => $this->owner->id,
            'verified_at' => now(),
        ]);
        $this->actingAs($this->owner)->post(route('admin.orders.refunds.store', $order), [
            'payment_transaction_id' => $payment->id,
            'refund_submission_key' => (string) Str::uuid(),
            'refund_amount' => '50.00',
            'reason' => 'Owner cannot request and self-approve a branchless refund.',
        ])->assertForbidden();
        $this->assertDatabaseCount('payment_refund_transactions', 0);

        $refund = $this->requestAsCustomer($customer, $payment, $order, '50.00');

        $this->actingAs($manager)->post(route('admin.refunds.complete', $refund), [
            'approval_note' => 'Manager cannot review a branchless order.',
        ])->assertForbidden();
        $this->actingAs($this->owner)->post(route('admin.refunds.complete', $refund), [
            'approval_note' => 'Refund issued to customer; bank confirmation recorded.',
        ])->assertRedirect();
        $this->assertSame(PaymentRefundStatus::Refunded, $refund->fresh()->status);
        $this->assertSame($this->owner->id, $refund->fresh()->approved_by_user_id);
    }

    public function test_platform_admin_cannot_approve_internal_business_refund(): void
    {
        $branch = $this->createBranch('REFUND-ADMIN-ISOLATION');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $order = $this->createOrder($branch, $seller, $customer, 5000);
        $payment = $this->verifiedPayment($order, $manager, 5000);
        $refund = $this->requestAsCustomer($customer, $payment, $order, '10.00');
        $platformAdmin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($platformAdmin)->post(route('admin.refunds.complete', $refund), [
            'approval_note' => 'Platform Admin must not manage business refunds.',
        ])->assertForbidden();
        $this->assertSame(PaymentRefundStatus::Requested, $refund->fresh()->status);
        $this->assertNull($refund->fresh()->approved_by_user_id);
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

    private function createEmployee(Branch $branch, string $role): User
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

    private function createOrder(Branch $branch, User $seller, User $customer, int $totalMinor): Order
    {
        $this->actingAs($this->owner);
        $order = Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'user_id' => $customer->id,
            'order_number' => 'REFUND-'.strtoupper(Str::random(10)),
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'shipping_address' => '1 Refund Lane',
            'shipping_city' => 'Refund City',
            'status' => OrderStatus::Pending,
            'payment_method' => 'cash',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => $totalMinor,
            'delivery_fee_minor' => 0,
            'total_minor' => $totalMinor,
        ]);
        $order->forceFill(['employee_seller_id' => $seller->id])->save();

        return $order;
    }

    private function verifiedPayment(Order $order, User $recorder, int $amountMinor): PaymentTransaction
    {
        $this->actingAs($recorder);
        $payment = app(PaymentTransactionService::class)->recordBusinessPayment(
            $order,
            $recorder,
            PaymentMethod::Cash,
            $amountMinor,
            null,
            'Cash recorded by branch staff.',
            (string) Str::uuid(),
        );
        $this->actingAs($this->owner);
        app(PaymentTransactionService::class)->verify($payment, $this->owner, false, 'Shop Owner confirmed independent count.');

        return $payment->fresh();
    }

    private function requestAsCustomer(User $customer, PaymentTransaction $payment, Order $order, string $amount): PaymentRefundTransaction
    {
        $this->actingAs($customer)->post(route('orders.refunds.store', $order), [
            'payment_transaction_id' => $payment->id,
            'refund_submission_key' => (string) Str::uuid(),
            'refund_amount' => $amount,
            'reason' => 'Customer refund request.',
        ])->assertRedirect();

        return PaymentRefundTransaction::query()->where('payment_transaction_id', $payment->id)->latest('id')->firstOrFail();
    }
}
