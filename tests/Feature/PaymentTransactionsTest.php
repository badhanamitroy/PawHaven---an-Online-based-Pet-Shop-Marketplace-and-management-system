<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SalesChannel;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\BusinessAccessService;
use App\Services\SalesReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTransactionsTest extends TestCase
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

    public function test_employee_cash_or_mobile_payment_submission_is_unverified_until_authorized_review(): void
    {
        $branch = $this->createBranch('PAYMENT-VERIFY');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $reviewer = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $order = $this->createOrder($branch, $seller, 10000);

        $this->actingAs($seller)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'bkash',
            'payment_amount' => '60.00',
            'payment_reference' => 'BKASH-PAYMENT-6000',
        ])->assertNotFound();

        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'payment_amount' => '60.00',
            'note' => 'Cash counted at branch till.',
        ])->assertRedirect(route('admin.orders.show', $order));

        $payment = PaymentTransaction::query()->where('order_id', $order->id)->sole();
        $this->assertSame(PaymentTransactionStatus::Submitted, $payment->status);
        $this->assertSame('admin_manual', $payment->source->value);
        $this->assertSame($manager->id, $payment->recorded_by_user_id);
        $this->assertNull($payment->verified_by_user_id);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);

        $this->actingAs($manager)->post(route('admin.payments.verify', $payment), [
            'note' => 'Self-verification attempt.',
        ])->assertForbidden();
        $this->assertSame(PaymentTransactionStatus::Submitted, $payment->fresh()->status);

        $this->actingAs($reviewer)->post(route('admin.payments.verify', $payment), [
            'note' => 'Cash counted against the branch till.',
        ])->assertRedirect(route('admin.orders.show', $order));

        $payment->refresh();
        $this->assertSame(PaymentTransactionStatus::Verified, $payment->status);
        $this->assertSame($reviewer->id, $payment->verified_by_user_id);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status, 'A partial verified payment does not mark the order fully paid.');
        $this->assertDatabaseHas('payment_transaction_events', ['payment_transaction_id' => $payment->id, 'from_status' => null, 'to_status' => 'submitted']);
        $this->assertDatabaseHas('payment_transaction_events', ['payment_transaction_id' => $payment->id, 'from_status' => 'submitted', 'to_status' => 'verified']);
    }

    public function test_shop_owner_cannot_self_verify_a_submitted_payment(): void
    {
        $branch = $this->createBranch('PAYMENT-OWNER-SELF');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $order = $this->createOrder($branch, $seller, 1000);

        $this->actingAs($this->owner)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'payment_amount' => '10.00',
        ])->assertRedirect();
        $payment = PaymentTransaction::query()->where('order_id', $order->id)->sole();

        $this->post(route('admin.payments.verify', $payment), ['note' => 'Owner self-verification attempt.'])->assertForbidden();
        $this->assertSame(PaymentTransactionStatus::Submitted, $payment->fresh()->status);
        $this->actingAs($manager)->post(route('admin.payments.verify', $payment), ['note' => 'Branch review of the Owner submission.'])->assertRedirect();
        $this->assertSame(PaymentTransactionStatus::Verified, $payment->fresh()->status);
    }

    public function test_owner_can_verify_full_cash_payment_and_report_counts_only_verified_collections(): void
    {
        $branch = $this->createBranch('PAYMENT-REPORT');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $order = $this->createOrder($branch, $seller, 5000);
        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'payment_amount' => '50.00',
            'note' => 'Cash entered by the assigned manager.',
        ])->assertRedirect();
        $payment = PaymentTransaction::query()->where('order_id', $order->id)->sole();

        $this->actingAs($this->owner)->get(route('admin.sales-reports.business', ['transaction_status' => 'submitted']))
            ->assertOk()->assertSee('USD 50.00')->assertSee('Recorded payment amount');
        $reports = app(SalesReportingService::class);
        $membership = $reports->membershipFor($this->owner, BusinessAccessService::OWNER);
        $summary = $reports->summary($reports->companyOrders($membership));
        $this->assertSame(5000, $summary['recorded_payment_minor']);
        $this->assertSame(0, $summary['verified_collected_minor']);
        $this->assertSame(5000, $summary['outstanding_minor']);

        $this->actingAs($this->owner)->post(route('admin.payments.verify', $payment), ['note' => 'Till count confirmed.'])->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $summary = $reports->summary($reports->companyOrders($membership));
        $this->assertSame(5000, $summary['verified_collected_minor']);
        $this->assertSame(0, $summary['outstanding_minor']);

        try {
            $payment->update(['amount_minor' => 1]);
            $this->fail('Verified transaction changes must be rejected.');
        } catch (\LogicException) {
            // The model exposes no normal update path for payment records.
        }
        try {
            $payment->delete();
            $this->fail('Payment transaction deletion must be rejected.');
        } catch (\LogicException) {
            // Payment evidence remains in the history.
        }
        $this->assertSame(5000, $payment->fresh()->amount_minor);
        $this->assertDatabaseHas('payment_transactions', ['id' => $payment->id, 'status' => 'verified']);
    }

    public function test_partial_payment_reserves_balance_and_prevents_overpayment(): void
    {
        $branch = $this->createBranch('PAYMENT-BALANCE');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $order = $this->createOrder($branch, $seller, 10000);

        $this->actingAs($this->owner)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'nagad', 'payment_amount' => '60.00', 'payment_reference' => 'NAGAD-REF-100',
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'rocket', 'payment_amount' => '40.01', 'payment_reference' => 'ROCKET-REF-101', 'note' => 'Branch recording note.',
        ])->assertSessionHasErrors('payment_amount');
        $this->assertDatabaseCount('payment_transactions', 1);

        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'rocket', 'payment_amount' => '40.00', 'payment_reference' => 'ROCKET-REF-102', 'note' => 'Second payment recording.',
        ])->assertRedirect();
        $second = PaymentTransaction::query()->where('order_id', $order->id)->where('payment_method', 'rocket')->sole();
        $this->actingAs($this->owner)->post(route('admin.payments.verify', $second), ['note' => 'Verified at branch.'])->assertRedirect();
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_repeated_cash_payment_form_submission_returns_the_original_transaction(): void
    {
        $branch = $this->createBranch('PAYMENT-IDEMPOTENCY');
        $employee = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $order = $this->createOrder($branch, $employee, 10000);
        $key = (string) Str::uuid();
        $payload = ['payment_submission_key' => $key, 'payment_method' => 'cash', 'payment_amount' => '20.00'];

        $this->actingAs($this->owner)->post(route('admin.orders.payments.store', $order), $payload)->assertRedirect();
        $first = PaymentTransaction::query()->where('order_id', $order->id)->sole();
        $this->post(route('admin.orders.payments.store', $order), $payload)->assertRedirect();

        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertDatabaseCount('payment_transaction_events', 1);
        $this->assertSame($first->id, PaymentTransaction::query()->sole()->id);
    }

    public function test_authorized_failed_transition_is_audited_and_releases_the_reserved_balance(): void
    {
        $branch = $this->createBranch('PAYMENT-FAILED');
        $employee = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $order = $this->createOrder($branch, $employee, 10000);
        $this->actingAs($this->owner)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'cash',
            'payment_amount' => '40.00',
        ])->assertRedirect();
        $transaction = PaymentTransaction::query()->where('order_id', $order->id)->sole();

        $this->actingAs($manager)->post(route('admin.payments.fail', $transaction), ['note' => 'Cash count did not match.'])->assertRedirect();
        $transaction->refresh();
        $this->assertSame(PaymentTransactionStatus::Failed, $transaction->status);
        $this->assertNull($transaction->verified_by_user_id);
        $this->assertDatabaseHas('payment_transaction_events', [
            'payment_transaction_id' => $transaction->id,
            'actor_user_id' => $manager->id,
            'from_status' => 'submitted',
            'to_status' => 'failed',
            'note' => 'Cash count did not match.',
        ]);
        $totals = app(\App\Services\PaymentTransactionService::class)->totalsForOrder($order->fresh());
        $this->assertSame(0, $totals['recorded_minor']);
        $this->assertSame(10000, $totals['available_to_record_minor']);
    }

    public function test_reference_is_unique_and_manager_cannot_verify_another_branch_payment(): void
    {
        $branchA = $this->createBranch('PAYMENT-SCOPE-A');
        $branchB = $this->createBranch('PAYMENT-SCOPE-B');
        $managerA = $this->createEmployee($branchA, BusinessAccessService::MANAGER);
        $sellerA = $this->createEmployee($branchA, BusinessAccessService::EMPLOYEE_SELLER);
        $sellerB = $this->createEmployee($branchB, BusinessAccessService::EMPLOYEE_SELLER);
        $orderA = $this->createOrder($branchA, $sellerA, 5000);
        $orderB = $this->createOrder($branchB, $sellerB, 5000);

        $this->actingAs($this->owner)->post(route('admin.orders.payments.store', $orderA), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'bkash', 'payment_amount' => '10.00', 'payment_reference' => 'GLOBAL-BKASH-REF',
        ])->assertRedirect();
        $first = PaymentTransaction::query()->where('order_id', $orderA->id)->sole();

        $this->post(route('admin.orders.payments.store', $orderB), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'nagad', 'payment_amount' => '10.00', 'payment_reference' => 'NAGAD-PAYMENT-REF',
        ])->assertRedirect();
        $second = PaymentTransaction::query()->where('order_id', $orderB->id)->sole();
        $this->post(route('admin.orders.payments.store', $orderA), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'bkash', 'payment_amount' => '10.00', 'payment_reference' => 'global-bkash-ref',
        ])->assertSessionHasErrors('payment_reference');
        $this->assertDatabaseCount('payment_transactions', 2);

        $this->actingAs($managerA)->post(route('admin.payments.verify', $second), ['note' => 'Wrong branch attempt.'])->assertForbidden();
        $this->assertSame(PaymentTransactionStatus::Submitted, $second->fresh()->status);
        $this->get(route('admin.orders.show', $orderB))->assertNotFound();
    }

    public function test_marketplace_seller_cannot_use_internal_payment_routes(): void
    {
        $branch = $this->createBranch('PAYMENT-MARKETPLACE-ISOLATION');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $order = $this->createOrder($branch, $seller, 1000);
        $marketplaceSeller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);

        $this->actingAs($marketplaceSeller)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'cash', 'payment_amount' => '10.00',
        ])->assertForbidden();
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_marketplace_seller_receipt_stays_outside_internal_branch_payment_reports(): void
    {
        $marketplaceSeller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
        $this->actingAs($this->owner);
        $order = Order::create([
            'company_id' => $this->company->id,
            'seller_id' => $marketplaceSeller->id,
            'order_number' => 'MARKETPLACE-'.strtoupper(Str::random(8)),
            'customer_name' => 'Marketplace Customer',
            'customer_email' => 'marketplace-customer@example.test',
            'shipping_address' => '1 Marketplace Way',
            'shipping_city' => 'Marketplace City',
            'status' => OrderStatus::Delivered,
            'payment_method' => 'cod',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => 7500,
            'delivery_fee_minor' => 0,
            'total_minor' => 7500,
        ]);

        $this->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'cash', 'payment_amount' => '75.00',
        ])->assertForbidden();
        $this->assertDatabaseCount('payment_transactions', 0);

        $this->actingAs($marketplaceSeller)->patch(route('seller.requests.payment', $order), [
            'payment_status' => 'paid', 'payment_reference' => 'MARKETPLACE-CASH-75',
        ])->assertRedirect();
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'recorded_by_user_id' => $marketplaceSeller->id,
            'verified_by_user_id' => $marketplaceSeller->id,
            'status' => 'verified',
        ]);

        $this->actingAs($this->owner)->get(route('admin.sales-reports.business'))
            ->assertOk()->assertSee('No orders match these filters.');
        $reports = app(SalesReportingService::class);
        $membership = $reports->membershipFor($this->owner, BusinessAccessService::OWNER);
        $this->assertSame(0, $reports->companyOrders($membership)->count());
        $this->assertSame(0, $reports->summary($reports->companyOrders($membership))['verified_collected_minor']);
    }

    public function test_cancelled_order_collection_is_separated_from_active_collected_and_outstanding_values(): void
    {
        $branch = $this->createBranch('PAYMENT-CANCELLED');
        $employee = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $order = $this->createOrder($branch, $employee, 2500);
        $this->actingAs($manager)->post(route('admin.orders.payments.store', $order), [
            'payment_submission_key' => (string) Str::uuid(),
            'payment_method' => 'cash', 'payment_amount' => '25.00', 'note' => 'Cash entered by the assigned manager.',
        ])->assertRedirect();
        $transaction = PaymentTransaction::query()->where('order_id', $order->id)->sole();
        $this->actingAs($this->owner)->post(route('admin.payments.verify', $transaction), ['note' => 'Cash counted before cancellation.'])->assertRedirect();
        $this->patch(route('admin.orders.update-status', $order), [
            'status' => 'cancelled', 'cancelled_reason' => 'Cancelled after verified collection.',
        ])->assertRedirect();

        $reports = app(SalesReportingService::class);
        $membership = $reports->membershipFor($this->owner, BusinessAccessService::OWNER);
        $summary = $reports->summary($reports->companyOrders($membership));
        $this->assertSame(0, $summary['verified_collected_minor']);
        $this->assertSame(2500, $summary['cancelled_verified_minor']);
        $this->assertSame(0, $summary['outstanding_minor']);
        $this->assertSame(2500, $summary['cancelled_order_value_minor']);
    }

    public function test_configured_currency_is_snapshotted_and_cannot_reinterpret_existing_amounts(): void
    {
        $branch = $this->createBranch('PAYMENT-CURRENCY');
        $seller = $this->createEmployee($branch, BusinessAccessService::EMPLOYEE_SELLER);
        $manager = $this->createEmployee($branch, BusinessAccessService::MANAGER);
        $this->actingAs($this->owner)->put(route('admin.settings.shop.update'), [
            'currency' => 'BDT',
            'currency_symbol' => '৳',
            'delivery_fee' => '50.00',
            'free_shipping_threshold' => '500.00',
            'cod_enabled' => '1',
        ])->assertRedirect(route('admin.settings.shop'));

        $order = $this->createOrder($branch, $seller, 5000);
        $this->assertSame('BDT', $order->currency);
        $this->actingAs($manager);
        $payment = app(\App\Services\PaymentTransactionService::class)->recordBusinessPayment(
            $order,
            $manager,
            \App\Enums\PaymentMethod::Cash,
            5000,
            null,
            'Cash recorded in configured business currency.',
            (string) Str::uuid(),
        );
        $this->assertSame('BDT', $payment->currency);
        $this->actingAs($this->owner);
        app(\App\Services\PaymentTransactionService::class)->verify($payment, $this->owner, false, 'Independent cash count.');

        $this->put(route('admin.settings.shop.update'), [
            'currency' => 'USD',
            'currency_symbol' => '$',
            'delivery_fee' => '5.00',
            'free_shipping_threshold' => '50.00',
            'cod_enabled' => '1',
        ])->assertSessionHasErrors('currency');
        $this->assertSame('BDT', \App\Facades\Settings::get('shop.currency'));
        $this->assertSame(5000, $payment->fresh()->amount_minor, 'Changing presentation currency must not alter stored minor units.');
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

    private function createOrder(Branch $branch, User $employee, int $totalMinor): Order
    {
        $this->actingAs($this->owner);
        $order = Order::create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'order_number' => 'PAYMENT-'.strtoupper(Str::random(10)),
            'customer_name' => 'Payment Customer',
            'customer_email' => 'payment@example.test',
            'customer_phone' => '5550100',
            'shipping_address' => '1 Payment Lane',
            'shipping_city' => 'Payment City',
            'status' => OrderStatus::Pending,
            'payment_method' => 'pos_pending',
            'payment_status' => PaymentStatus::Pending,
            'subtotal_minor' => $totalMinor,
            'delivery_fee_minor' => 0,
            'total_minor' => $totalMinor,
        ]);
        $order->forceFill([
            'employee_seller_id' => $employee->id,
            'sales_channel' => SalesChannel::PointOfSale,
        ])->save();

        return $order;
    }
}
