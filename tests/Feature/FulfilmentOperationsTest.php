<?php

namespace Tests\Feature;

use App\Enums\FulfilmentAction;
use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderFulfilmentEvent;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\EmployeeSalesDeskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Support\SignsOnlineOrderQuote;

class FulfilmentOperationsTest extends TestCase
{
    use RefreshDatabase;
    use SignsOnlineOrderQuote;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $this->owner = $this->createStaffMembership('owner', null);
    }

    public function test_external_delivery_progresses_in_order_without_second_stock_deduction_or_payment_claim(): void
    {
        [$branch, $seller, $product] = $this->branchSellerAndProduct('FULFIL-COURIER');
        $order = $this->createPosOrder($seller, $product, 2);
        $stockAfterReservation = $product->fresh()->stock_quantity;
        $manager = $this->createStaffMembership('manager', $branch);

        $confirmed = $this->transition($manager, $order, FulfilmentAction::Confirm, [
            'delivery_method' => 'external_courier', 'note' => 'Checked customer address.',
        ]);
        $confirmed->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);

        $this->transition($manager, $order, FulfilmentAction::StartPicking, ['note' => 'Picking started.']);
        $this->transition($manager, $order, FulfilmentAction::Pack, [
            'items_verified' => '1', 'note' => 'Item and quantity checked.',
        ]);
        $this->assertSame(FulfilmentStatus::Packed, $order->fresh()->fulfilment_status);
        $this->assertSame($stockAfterReservation, $product->fresh()->stock_quantity, 'Packing must not reserve or deduct stock a second time.');

        $this->transition($manager, $order, FulfilmentAction::MarkReady, ['note' => 'Parcel sealed.']);
        $this->transition($manager, $order, FulfilmentAction::Dispatch, [
            'delivery_provider' => 'Test Courier', 'tracking_reference' => 'TRACK-123', 'note' => 'Handed to courier.',
        ]);
        $this->transition($manager, $order, FulfilmentAction::OutForDelivery, ['note' => 'Courier confirmed route start.']);
        $this->transition($manager, $order, FulfilmentAction::RecordFailedAttempt, ['note' => 'Customer unavailable.']);

        $this->assertSame(FulfilmentStatus::DeliveryFailed, $order->fresh()->fulfilment_status);
        $this->assertSame(1, $order->fresh()->delivery_attempt_count);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame($stockAfterReservation, $product->fresh()->stock_quantity);

        $this->transition($manager, $order, FulfilmentAction::RetryDelivery, ['note' => 'Customer requested a later attempt.']);
        $this->transition($manager, $order, FulfilmentAction::Deliver, ['note' => 'Delivery confirmed by staff.']);

        $order->refresh();
        $this->assertSame(FulfilmentStatus::Delivered, $order->fulfilment_status);
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(2, $order->delivery_attempt_count);
        $this->assertSame($stockAfterReservation, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('payment_refund_transactions', 0);
        $this->assertDatabaseHas('order_fulfilment_events', [
            'order_id' => $order->id,
            'event_type' => 'record_failed_attempt',
            'internal_note' => 'Customer unavailable.',
            'buyer_message' => 'A delivery attempt was unsuccessful. The shop will review the next steps.',
        ]);
    }

    public function test_pickup_handover_completes_only_after_a_recorded_branch_handover(): void
    {
        [$branch, $seller, $product] = $this->branchSellerAndProduct('FULFIL-PICKUP');
        $order = $this->createPosOrder($seller, $product, 1);
        $owner = $this->owner;

        $this->transition($owner, $order, FulfilmentAction::Confirm, ['delivery_method' => 'branch_pickup']);
        $this->transition($owner, $order, FulfilmentAction::StartPicking);
        $this->transition($owner, $order, FulfilmentAction::Pack, ['items_verified' => '1']);
        $this->transition($owner, $order, FulfilmentAction::MarkReady);
        $this->assertSame(FulfilmentStatus::ReadyForPickup, $order->fresh()->fulfilment_status);
        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);

        $this->transition($owner, $order, FulfilmentAction::ConfirmHandover);
        $order->refresh();
        $this->assertSame(FulfilmentStatus::Delivered, $order->fulfilment_status);
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertDatabaseHas('order_fulfilment_events', [
            'order_id' => $order->id,
            'event_type' => 'confirm_handover',
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_legacy_processing_order_can_record_delivery_method_before_picking(): void
    {
        [$branch, $seller, $product] = $this->branchSellerAndProduct('FULFIL-LEGACY');
        $order = $this->createPosOrder($seller, $product, 1);
        $order->forceFill([
            'status' => OrderStatus::Processing,
            'fulfilment_status' => FulfilmentStatus::Confirmed,
            'delivery_method' => null,
        ])->save();

        $this->transition($this->owner, $order, FulfilmentAction::StartPicking, [
            'delivery_method' => 'branch_pickup',
        ])->assertRedirect();

        $this->assertSame(FulfilmentStatus::Picking, $order->fresh()->fulfilment_status);
        $this->assertSame(\App\Enums\DeliveryMethod::BranchPickup, $order->fresh()->delivery_method);
    }

    public function test_legacy_order_status_transition_matrix_is_preserved(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Processing));
        $this->assertTrue(OrderStatus::Processing->canTransitionTo(OrderStatus::Shipped));
        $this->assertFalse(OrderStatus::Processing->canTransitionTo(OrderStatus::Delivered));
        $this->assertTrue(OrderStatus::Shipped->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Delivered->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_duplicate_transition_submission_is_idempotent_and_invalid_jumps_are_rejected(): void
    {
        [$branch, $seller, $product] = $this->branchSellerAndProduct('FULFIL-IDEMPOTENCY');
        $order = $this->createPosOrder($seller, $product, 1);
        $key = (string) Str::uuid();
        $payload = ['action' => 'confirm', 'submission_key' => $key, 'delivery_method' => 'internal_delivery'];

        $this->actingAs($this->owner)->post(route('admin.fulfilment.transition', $order), $payload)->assertRedirect();
        $this->actingAs($this->owner)->post(route('admin.fulfilment.transition', $order), $payload)->assertRedirect();
        $this->assertSame(1, OrderFulfilmentEvent::query()->where('order_id', $order->id)->where('event_type', 'confirm')->count());

        $this->transition($this->owner, $order, FulfilmentAction::Dispatch, ['delivery_provider' => 'Courier']);
        $this->assertSame(FulfilmentStatus::Confirmed, $order->fresh()->fulfilment_status);
        $this->assertSame(1, OrderFulfilmentEvent::query()->where('order_id', $order->id)->where('event_type', 'confirm')->count());
    }

    public function test_pre_dispatch_cancellation_releases_stock_once_and_dispatched_cancellation_is_rejected(): void
    {
        [$branch, $seller, $product] = $this->branchSellerAndProduct('FULFIL-CANCEL');
        $order = $this->createPosOrder($seller, $product, 2);
        $afterReservation = $product->fresh()->stock_quantity;
        $key = (string) Str::uuid();
        $cancelPayload = [
            'action' => 'cancel',
            'submission_key' => $key,
            'note' => 'Customer requested cancellation before dispatch.',
        ];

        $this->actingAs($this->owner)->post(route('admin.fulfilment.transition', $order), $cancelPayload)->assertRedirect();
        $this->actingAs($this->owner)->post(route('admin.fulfilment.transition', $order), $cancelPayload)->assertRedirect();
        $this->assertSame($afterReservation + 2, $product->fresh()->stock_quantity);
        $this->assertSame(1, InventoryMovement::query()->where('order_id', $order->id)->where('movement_type', 'cancellation_release')->count());
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(1, OrderFulfilmentEvent::query()->where('order_id', $order->id)->where('event_type', 'cancel')->count());

        [$secondBranch, $secondSeller, $secondProduct] = $this->branchSellerAndProduct('FULFIL-DISPATCH-CANCEL');
        $dispatchedOrder = $this->createPosOrder($secondSeller, $secondProduct, 1);
        $this->transition($this->owner, $dispatchedOrder, FulfilmentAction::Confirm, ['delivery_method' => 'external_courier']);
        $this->transition($this->owner, $dispatchedOrder, FulfilmentAction::StartPicking);
        $this->transition($this->owner, $dispatchedOrder, FulfilmentAction::Pack, ['items_verified' => '1']);
        $this->transition($this->owner, $dispatchedOrder, FulfilmentAction::MarkReady);
        $this->transition($this->owner, $dispatchedOrder, FulfilmentAction::Dispatch, ['delivery_provider' => 'Courier']);

        $this->transition($this->owner, $dispatchedOrder, FulfilmentAction::Cancel)->assertSessionHasErrors('action');
        $this->assertSame(FulfilmentStatus::Dispatched, $dispatchedOrder->fresh()->fulfilment_status);
        $this->assertSame(0, InventoryMovement::query()->where('order_id', $dispatchedOrder->id)->where('movement_type', 'cancellation_release')->count());
        $this->assertSame(PaymentStatus::Pending, $dispatchedOrder->fresh()->payment_status);
    }

    public function test_owner_and_manager_scope_fulfilment_while_employee_seller_marketplace_seller_and_platform_admin_are_denied(): void
    {
        [$branch, $seller, $product] = $this->branchSellerAndProduct('FULFIL-SCOPE-A');
        $order = $this->createPosOrder($seller, $product, 1);
        [$otherBranch, $otherSeller, $otherProduct] = $this->branchSellerAndProduct('FULFIL-SCOPE-B');
        $otherOrder = $this->createPosOrder($otherSeller, $otherProduct, 1);
        $manager = $this->createStaffMembership('manager', $branch);

        $this->actingAs($this->owner)->get(route('admin.fulfilment.index'))->assertOk()->assertSee($order->order_number)->assertSee($otherOrder->order_number);
        $this->actingAs($manager)->get(route('admin.fulfilment.index'))->assertOk()->assertSee($order->order_number)->assertDontSee($otherOrder->order_number);
        $this->actingAs($manager)->get(route('admin.fulfilment.index', ['branch_id' => $otherBranch->id]))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.fulfilment.transition', $otherOrder), [
            'action' => 'confirm', 'submission_key' => (string) Str::uuid(), 'delivery_method' => 'branch_pickup', 'note' => 'Attempted cross branch action.',
        ])->assertNotFound();

        $employeeSeller = $seller;
        $marketplaceSeller = User::factory()->create(['role' => 'seller', 'email_verified_at' => now()]);
        $platformAdmin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        foreach ([$employeeSeller, $marketplaceSeller, $platformAdmin] as $unauthorized) {
            $this->actingAs($unauthorized)->get(route('admin.fulfilment.index'))->assertForbidden();
        }
    }

    public function test_buyer_tracking_is_limited_to_the_buyer_and_excludes_internal_notes(): void
    {
        [$branch, $seller, $product] = $this->branchSellerAndProduct('FULFIL-BUYER');
        $buyer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($buyer)->post(route('orders.request'), [
            'product_id' => $product->id,
            'submission_key' => (string) Str::uuid(),
            'quantity' => 1,
            ...$this->onlinePricingFields($product, 1, null, $buyer),
            'customer_phone' => '+1 555 111 2222',
            'shipping_address' => '15 Buyer Street',
            'shipping_city' => 'Seattle',
        ])->assertRedirect(route('dashboard'));
        $order = Order::query()->where('user_id', $buyer->id)->sole();
        $manager = $this->createStaffMembership('manager', $branch);

        $this->transition($manager, $order, FulfilmentAction::Confirm, [
            'delivery_method' => 'external_courier', 'note' => 'PRIVATE-INTERNAL-ADDRESS-CHECK',
        ]);
        $this->transition($manager, $order, FulfilmentAction::StartPicking, ['note' => 'Internal item handling note.']);
        $this->transition($manager, $order, FulfilmentAction::Pack, ['items_verified' => '1', 'note' => 'PRIVATE-PACKING-NOTE']);
        $this->transition($manager, $order, FulfilmentAction::MarkReady, ['note' => 'Ready for courier.']);
        $this->transition($manager, $order, FulfilmentAction::Dispatch, [
            'delivery_provider' => 'Manual Courier', 'tracking_reference' => 'SAFE-TRACK-99', 'note' => 'Internal dispatch note.',
        ]);

        $this->actingAs($buyer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fulfilment tracking')
            ->assertSee('SAFE-TRACK-99')
            ->assertSee('Your order was confirmed.')
            ->assertDontSee('PRIVATE-INTERNAL-ADDRESS-CHECK')
            ->assertDontSee('PRIVATE-PACKING-NOTE')
            ->assertDontSee($manager->name);

        $otherBuyer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($otherBuyer)->get(route('dashboard'))->assertOk()->assertDontSee($order->order_number);
    }

    private function transition(User $actor, Order $order, FulfilmentAction $action, array $values = [])
    {
        return $this->actingAs($actor)->post(route('admin.fulfilment.transition', $order), [
            'action' => $action->value,
            'submission_key' => (string) Str::uuid(),
            ...$values,
        ]);
    }

    /** @return array{Branch, User, Product} */
    private function branchSellerAndProduct(string $code): array
    {
        $this->actingAs($this->owner);
        $branch = Branch::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Branch '.$code,
            'code' => $code,
            'city' => 'Seattle',
            'address' => '100 PawHaven Way',
            'is_active' => true,
        ]);
        $seller = $this->createStaffMembership('seller', $branch);
        $category = ProductCategory::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Category '.$code,
            'slug' => Str::slug($code),
            'is_active' => true,
        ]);

        $this->actingAs($this->owner);
        $product = Product::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'product_category_id' => $category->id,
            'name' => 'Product '.$code,
            'slug' => Str::slug('product '.$code),
            'sku' => 'SKU-'.$code,
            'price_minor' => 1000,
            'stock_quantity' => 12,
            'low_stock_threshold' => 0,
            'is_published' => true,
        ]);

        return [$branch, $seller, $product];
    }

    private function createStaffMembership(string $role, ?Branch $branch): User
    {
        $user = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);
        BusinessMembership::query()->create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'branch_id' => $branch?->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }

    private function createPosOrder(User $seller, Product $product, int $quantity): Order
    {
        $this->actingAs($seller);

        return app(EmployeeSalesDeskService::class)->createPosOrder(
            $seller,
            [$product->id => $quantity],
            [
                'customer_name' => 'Fulfilment Test Customer',
                'customer_email' => 'walkin+'.Str::random(8).'@example.test',
                'customer_phone' => '5550100',
            ],
            (string) Str::uuid(),
        );
    }
}
