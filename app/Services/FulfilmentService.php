<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\FulfilmentAction;
use App\Enums\FulfilmentStatus;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderFulfilmentEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FulfilmentService
{
    public function __construct(
        private readonly BusinessAccessService $businessAccess,
        private readonly OrderInventoryService $inventory,
    ) {}

    /** @param array<string, mixed> $input */
    public function transition(Order $order, User $actor, FulfilmentAction $action, array $input): OrderFulfilmentEvent
    {
        $this->assertActorIdentity($actor);
        $key = (string) ($input['submission_key'] ?? '');
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['submission_key' => 'This fulfilment form has expired. Reload the order before trying again.']);
        }

        $data = $this->normalizeInput($action, $input);
        $payloadHash = $this->payloadHash($action, $data);

        try {
            return DB::transaction(function () use ($order, $actor, $action, $key, $data, $payloadHash): OrderFulfilmentEvent {
                $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                $membership = $this->authorizeBusinessAction($actor, $lockedOrder, $action !== FulfilmentAction::Cancel);
                $this->assertInternalOrder($lockedOrder);

                $existing = OrderFulfilmentEvent::query()->where('submission_key', $key)->first();
                if ($existing) {
                    $this->assertSameSubmission($existing, $lockedOrder, $actor, $action, $payloadHash);

                    return $existing;
                }

                $from = $lockedOrder->fulfilment_status;
                $fromOrderStatus = $lockedOrder->status;
                if (! $from instanceof FulfilmentStatus) {
                    throw new AuthorizationException('This order has no usable fulfilment state.');
                }

                if ($membership->role === BusinessAccessService::MANAGER && $data['note'] === null) {
                    throw ValidationException::withMessages(['note' => 'Branch Managers must record a reason for each fulfilment action.']);
                }

                $this->applyAction($lockedOrder, $actor, $membership, $action, $data);
                $lockedOrder->save();

                $event = OrderFulfilmentEvent::create([
                    'order_id' => $lockedOrder->getKey(),
                    'order_item_id' => $data['order_item_id'] ?? null,
                    'company_id' => $lockedOrder->company_id,
                    'branch_id' => $lockedOrder->branch_id,
                    'actor_user_id' => $actor->getKey(),
                    'event_type' => $action->value,
                    'from_status' => $from,
                    'to_status' => $lockedOrder->fulfilment_status,
                    'submission_key' => $key,
                    'payload_hash' => $payloadHash,
                    'internal_note' => $data['note'] ?? null,
                    'buyer_message' => $this->buyerMessage($action, $lockedOrder),
                ]);

                if ($from !== $lockedOrder->fulfilment_status) {
                    AdminAuditLog::record(
                        'order.status_update',
                        "Order #{$lockedOrder->order_number} moved from {$fromOrderStatus->label()} to {$lockedOrder->status->label()} during fulfilment action '{$action->value}'.",
                        [
                            'order_id' => $lockedOrder->getKey(),
                            'order_number' => $lockedOrder->order_number,
                            'from_fulfilment_status' => $from->value,
                            'to_fulfilment_status' => $lockedOrder->fulfilment_status->value,
                            'company_id' => $lockedOrder->company_id,
                            'branch_id' => $lockedOrder->branch_id,
                        ],
                    );
                }

                return $event;
            }, 3);
        } catch (QueryException $exception) {
            $existing = OrderFulfilmentEvent::query()->where('submission_key', $key)->first();
            if ($existing) {
                $orderForCheck = Order::query()->withoutGlobalScopes()->findOrFail($order->getKey());
                $this->assertSameSubmission($existing, $orderForCheck, $actor, $action, $payloadHash);

                return $existing;
            }

            throw $exception;
        }
    }

    /** Cancel an internal shop order from its owning customer's account before confirmation. */
    public function cancelByBuyer(Order $order, User $buyer): bool
    {
        $this->assertActorIdentity($buyer);

        return DB::transaction(function () use ($order, $buyer): bool {
            $lockedOrder = Order::query()->withoutGlobalScopes()
                ->whereKey($order->getKey())
                ->where('user_id', $buyer->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || $lockedOrder->company_id === null
                || $lockedOrder->seller_id !== null || $lockedOrder->pet_listing_id !== null) {
                throw new AuthorizationException('This customer cancellation is outside the internal order workflow.');
            }

            if ($lockedOrder->status !== OrderStatus::Pending
                || $lockedOrder->fulfilment_status !== FulfilmentStatus::AwaitingConfirmation) {
                return false;
            }

            $from = $lockedOrder->fulfilment_status;
            $lockedOrder->status = OrderStatus::Cancelled;
            $lockedOrder->fulfilment_status = FulfilmentStatus::Cancelled;
            $lockedOrder->cancelled_reason = 'Cancelled by the customer before confirmation.';
            $this->inventory->restoreReservedInventory($lockedOrder, $buyer);

            OrderFulfilmentEvent::create([
                'order_id' => $lockedOrder->getKey(),
                'company_id' => $lockedOrder->company_id,
                'branch_id' => $lockedOrder->branch_id,
                'actor_user_id' => $buyer->getKey(),
                'event_type' => 'customer_cancelled',
                'from_status' => $from,
                'to_status' => FulfilmentStatus::Cancelled,
                'submission_key' => (string) Str::uuid(),
                'payload_hash' => hash('sha256', 'customer_cancelled:'.$lockedOrder->getKey().':'.$buyer->getKey()),
                'buyer_message' => 'Your order was cancelled before confirmation.',
            ]);

            return true;
        }, 3);
    }

    public function recordPlaced(Order $order, User $actor): OrderFulfilmentEvent
    {
        $this->assertActorIdentity($actor);
        $expectedActor = $order->employee_seller_id ?? $order->user_id;
        if ($expectedActor === null || (int) $expectedActor !== (int) $actor->getKey()) {
            throw new AuthorizationException('The order placement actor does not match this order.');
        }

        return OrderFulfilmentEvent::create([
            'order_id' => $order->getKey(),
            'company_id' => $order->company_id,
            'branch_id' => $order->branch_id,
            'actor_user_id' => $actor->getKey(),
            'event_type' => 'placed',
            'from_status' => null,
            'to_status' => FulfilmentStatus::AwaitingConfirmation,
            'submission_key' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', 'placed:'.$order->getKey().':'.$actor->getKey()),
            'buyer_message' => 'Your order was received and is awaiting confirmation.',
        ]);
    }

    /** @return list<FulfilmentAction> */
    public function availableActions(Order $order): array
    {
        $status = $order->fulfilment_status;
        if (! $status instanceof FulfilmentStatus || $order->seller_id !== null || $order->pet_listing_id !== null) {
            return [];
        }

        return match ($status) {
            FulfilmentStatus::AwaitingConfirmation => [FulfilmentAction::Confirm, FulfilmentAction::Cancel],
            FulfilmentStatus::Confirmed => [FulfilmentAction::StartPicking, FulfilmentAction::Cancel],
            FulfilmentStatus::Picking => [FulfilmentAction::ReportShortage, FulfilmentAction::Pack, FulfilmentAction::Cancel],
            FulfilmentStatus::ShortageReview => [FulfilmentAction::ResumePicking, FulfilmentAction::Cancel],
            FulfilmentStatus::Packed => [FulfilmentAction::MarkReady, FulfilmentAction::Cancel],
            FulfilmentStatus::ReadyForPickup => [FulfilmentAction::ConfirmHandover, FulfilmentAction::Cancel],
            FulfilmentStatus::ReadyForDispatch => [FulfilmentAction::Dispatch, FulfilmentAction::Cancel],
            FulfilmentStatus::Dispatched => $order->delivery_method === null
                ? [FulfilmentAction::Deliver]
                : [FulfilmentAction::OutForDelivery, FulfilmentAction::Deliver],
            FulfilmentStatus::OutForDelivery => [FulfilmentAction::RecordFailedAttempt, FulfilmentAction::Deliver],
            FulfilmentStatus::DeliveryFailed => [FulfilmentAction::RetryDelivery, FulfilmentAction::ReturnToSender],
            FulfilmentStatus::ReturnedToSender, FulfilmentStatus::Delivered, FulfilmentStatus::Cancelled => [],
        };
    }

    /** @param array<string, mixed> $data */
    private function applyAction(Order $order, User $actor, BusinessMembership $membership, FulfilmentAction $action, array $data): void
    {
        $status = $order->fulfilment_status;

        switch ($action) {
            case FulfilmentAction::Confirm:
                $this->requireState($status, [FulfilmentStatus::AwaitingConfirmation]);
                if ($order->status !== OrderStatus::Pending) {
                    throw ValidationException::withMessages(['action' => 'Only a pending order can be confirmed.']);
                }
                $method = DeliveryMethod::tryFrom((string) ($data['delivery_method'] ?? ''));
                if (! $method) {
                    throw ValidationException::withMessages(['delivery_method' => 'Select pickup, internal delivery, or an external courier.']);
                }
                $order->delivery_method = $method;
                $order->fulfilment_status = FulfilmentStatus::Confirmed;
                $order->confirmed_at = now();
                $this->advanceOrderStatus($order, OrderStatus::Processing);
                break;

            case FulfilmentAction::StartPicking:
                $this->requireState($status, [FulfilmentStatus::Confirmed]);
                if ($order->delivery_method === null) {
                    $method = DeliveryMethod::tryFrom((string) ($data['delivery_method'] ?? ''));
                    if (! $method) {
                        throw ValidationException::withMessages(['delivery_method' => 'Select a delivery method for this existing order before picking.']);
                    }
                    $order->delivery_method = $method;
                } elseif ($data['delivery_method'] !== null && (string) $data['delivery_method'] !== $order->delivery_method->value) {
                    throw ValidationException::withMessages(['delivery_method' => 'The delivery method cannot be changed after order confirmation.']);
                }
                $order->fulfilment_status = FulfilmentStatus::Picking;
                $order->picking_started_at = now();
                break;

            case FulfilmentAction::ReportShortage:
                $this->requireState($status, [FulfilmentStatus::Picking]);
                $this->requireNote($data, 'Describe the unavailable item so the order can be reviewed.');
                $item = OrderItem::query()->whereKey($data['order_item_id'] ?? 0)
                    ->where('order_id', $order->getKey())->first();
                if (! $item) {
                    throw ValidationException::withMessages(['order_item_id' => 'Select an item from this order.']);
                }
                $order->fulfilment_status = FulfilmentStatus::ShortageReview;
                break;

            case FulfilmentAction::ResumePicking:
                $this->requireState($status, [FulfilmentStatus::ShortageReview]);
                $this->requireNote($data, 'Record the decision that allows picking to resume.');
                $order->fulfilment_status = FulfilmentStatus::Picking;
                $order->picking_started_at ??= now();
                break;

            case FulfilmentAction::Pack:
                $this->requireState($status, [FulfilmentStatus::Picking]);
                if (! ($data['items_verified'] ?? false)) {
                    throw ValidationException::withMessages(['items_verified' => 'Confirm that every ordered item and quantity was picked.']);
                }
                $this->assertReservedItemsCanBePacked($order);
                $order->fulfilment_status = FulfilmentStatus::Packed;
                $order->picked_at = now();
                $order->packed_at = now();
                $order->packing_notes = $data['note'] ?? null;
                break;

            case FulfilmentAction::MarkReady:
                $this->requireState($status, [FulfilmentStatus::Packed]);
                if (! $order->delivery_method instanceof DeliveryMethod) {
                    throw ValidationException::withMessages(['delivery_method' => 'Confirm the delivery method before marking this order ready.']);
                }
                $order->fulfilment_status = $order->delivery_method === DeliveryMethod::BranchPickup
                    ? FulfilmentStatus::ReadyForPickup
                    : FulfilmentStatus::ReadyForDispatch;
                $order->ready_at = now();
                break;

            case FulfilmentAction::Dispatch:
                $this->requireState($status, [FulfilmentStatus::ReadyForDispatch]);
                if (! $order->delivery_method instanceof DeliveryMethod
                    || $order->delivery_method === DeliveryMethod::BranchPickup) {
                    throw ValidationException::withMessages(['action' => 'Pickup orders must be handed over at the branch, not dispatched.']);
                }
                if ($order->delivery_method === DeliveryMethod::ExternalCourier) {
                    $provider = trim((string) ($data['delivery_provider'] ?? ''));
                    if ($provider === '') {
                        throw ValidationException::withMessages(['delivery_provider' => 'Enter the courier name before dispatch.']);
                    }
                    $order->delivery_provider = $provider;
                    $order->tracking_reference = $data['tracking_reference'] ?? null;
                }
                $this->advanceOrderStatus($order, OrderStatus::Shipped);
                $order->fulfilment_status = FulfilmentStatus::Dispatched;
                $order->dispatched_at = now();
                break;

            case FulfilmentAction::OutForDelivery:
            case FulfilmentAction::RetryDelivery:
                $this->requireState($status, $action === FulfilmentAction::RetryDelivery
                    ? [FulfilmentStatus::DeliveryFailed]
                    : [FulfilmentStatus::Dispatched]);
                if ($order->delivery_method === null || $order->delivery_method === DeliveryMethod::BranchPickup) {
                    throw ValidationException::withMessages(['action' => 'Only a delivery order can be sent out for delivery.']);
                }
                $order->fulfilment_status = FulfilmentStatus::OutForDelivery;
                $order->out_for_delivery_at = now();
                $order->delivery_attempt_count = (int) $order->delivery_attempt_count + 1;
                break;

            case FulfilmentAction::RecordFailedAttempt:
                $this->requireState($status, [FulfilmentStatus::OutForDelivery]);
                $this->requireNote($data, 'Record the reason for the failed delivery attempt.');
                $order->fulfilment_status = FulfilmentStatus::DeliveryFailed;
                $order->delivery_failed_at = now();
                $order->delivery_failed_reason = $data['note'];
                break;

            case FulfilmentAction::ReturnToSender:
                $this->requireState($status, [FulfilmentStatus::DeliveryFailed]);
                $this->requireNote($data, 'Record why the carrier returned the parcel.');
                $order->fulfilment_status = FulfilmentStatus::ReturnedToSender;
                break;

            case FulfilmentAction::ConfirmHandover:
                $this->requireState($status, [FulfilmentStatus::ReadyForPickup]);
                if ($order->delivery_method !== DeliveryMethod::BranchPickup) {
                    throw ValidationException::withMessages(['action' => 'Only a pickup order can be completed as a branch handover.']);
                }
                $this->completeDelivery($order, pickup: true);
                break;

            case FulfilmentAction::Deliver:
                $this->requireState($status, [FulfilmentStatus::Dispatched, FulfilmentStatus::OutForDelivery]);
                if ($order->delivery_method === DeliveryMethod::BranchPickup) {
                    throw ValidationException::withMessages(['action' => 'Pickup orders require an in-branch handover confirmation.']);
                }
                $this->completeDelivery($order);
                break;

            case FulfilmentAction::Cancel:
                $this->requireState($status, [
                    FulfilmentStatus::AwaitingConfirmation,
                    FulfilmentStatus::Confirmed,
                    FulfilmentStatus::Picking,
                    FulfilmentStatus::ShortageReview,
                    FulfilmentStatus::Packed,
                    FulfilmentStatus::ReadyForPickup,
                    FulfilmentStatus::ReadyForDispatch,
                ]);
                $this->requireNote($data, 'Provide a reason for cancelling before dispatch.');
                $this->advanceOrderStatus($order, OrderStatus::Cancelled);
                $order->cancelled_reason = $data['note'];
                $order->fulfilment_status = FulfilmentStatus::Cancelled;
                $this->inventory->restoreReservedInventory($order, $actor);
                break;
        }
    }

    private function completeDelivery(Order $order, bool $pickup = false): void
    {
        if ($pickup && $order->status === OrderStatus::Processing) {
            // Legacy OrderStatus deliberately preserves its original transition matrix;
            // only a verified branch handover may complete a processing shop order.
            $order->status = OrderStatus::Delivered;
        } else {
            $this->advanceOrderStatus($order, OrderStatus::Delivered);
        }
        $order->fulfilment_status = FulfilmentStatus::Delivered;
        $order->delivered_at = now();
    }

    private function advanceOrderStatus(Order $order, OrderStatus $next): void
    {
        if (! $order->status->canTransitionTo($next)) {
            throw ValidationException::withMessages(['action' => "Order status cannot transition from {$order->status->label()} to {$next->label()}."]);
        }
        $order->status = $next;
    }

    /** @param list<FulfilmentStatus> $expected */
    private function requireState(FulfilmentStatus $actual, array $expected): void
    {
        if (! in_array($actual, $expected, true)) {
            throw ValidationException::withMessages(['action' => 'That fulfilment action is not valid at the order’s current stage.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function requireNote(array $data, string $message): void
    {
        if (trim((string) ($data['note'] ?? '')) === '') {
            throw ValidationException::withMessages(['note' => $message]);
        }
    }

    private function assertReservedItemsCanBePacked(Order $order): void
    {
        if ($order->inventory_reserved_at === null) {
            throw ValidationException::withMessages(['items_verified' => 'This order has no recorded stock reservation and cannot be packed until the inventory discrepancy is reviewed.']);
        }

        $items = OrderItem::query()->where('order_id', $order->getKey())->orderBy('id')->lockForUpdate()->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items_verified' => 'An order with no item lines cannot be marked packed.']);
        }

        foreach ($items as $item) {
            if ($item->product_id === null) {
                throw ValidationException::withMessages(['items_verified' => "The ordered item '{$item->product_name}' is no longer linked to an inventory product. Report it for review."]);
            }
            $product = Product::query()->withoutGlobalScopes()->whereKey($item->product_id)->lockForUpdate()->first();
            if (! $product
                || (int) $product->company_id !== (int) $order->company_id
                || ($product->branch_id !== null && (int) $product->branch_id !== (int) $order->branch_id)) {
                throw ValidationException::withMessages(['items_verified' => "The ordered item '{$item->product_name}' no longer matches this order’s company and branch scope."]);
            }
            $reserved = InventoryMovement::query()
                ->where('order_item_id', $item->getKey())
                ->whereIn('movement_type', [InventoryMovementType::PosReservation->value, InventoryMovementType::OnlineReservation->value])
                ->exists();
            if (! $reserved) {
                throw ValidationException::withMessages(['items_verified' => "The stock reservation for '{$item->product_name}' is not recorded. Report it for review before packing."]);
            }
        }
    }

    private function authorizeBusinessAction(User $actor, Order $order, bool $requireActiveBranch): BusinessMembership
    {
        $membership = $this->businessAccess->activeMemberships($actor)->first();
        if (! $actor->isStaff()
            || ! $membership
            || ! in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)
            || (int) $membership->company_id !== (int) $order->company_id
            || ! $this->businessAccess->canManageOrders($actor, (int) $order->company_id, $order->branch_id)) {
            throw new AuthorizationException('An active Shop Owner or assigned Branch Manager is required for fulfilment.');
        }

        $lockedMembership = BusinessMembership::query()->whereKey($membership->getKey())->lockForUpdate()->first();
        $company = Company::query()->withoutGlobalScopes()->whereKey($order->company_id)->lockForUpdate()->first();
        if (! $lockedMembership?->is_active || ! $company?->is_active) {
            throw new AuthorizationException('The fulfilment business assignment or company is no longer active.');
        }
        $this->businessAccess->installationCompany((int) $company->getKey());

        if (! $lockedMembership
            || ! $lockedMembership->is_active
            || (int) $lockedMembership->company_id !== (int) $order->company_id
            || $lockedMembership->role !== $membership->role
            || $lockedMembership->branch_id !== $membership->branch_id
            || ! in_array($lockedMembership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)) {
            throw new AuthorizationException('The fulfilment membership changed while this action was being authorized.');
        }

        if ($lockedMembership->role === BusinessAccessService::MANAGER) {
            if ($order->branch_id === null || (int) $membership->branch_id !== (int) $order->branch_id) {
                throw new AuthorizationException('Branch Managers can act only on orders for their assigned branch.');
            }
        }

        if ($order->branch_id !== null) {
            $branch = Branch::query()->withoutGlobalScopes()->whereKey($order->branch_id)
                ->where('company_id', $order->company_id)->lockForUpdate()->first();
            if (! $branch || ($requireActiveBranch && ! $branch->is_active)) {
                throw new AuthorizationException('This order’s branch is unavailable for fulfilment.');
            }
        }

        return $lockedMembership;
    }

    private function assertInternalOrder(Order $order): void
    {
        if ($order->company_id === null || $order->seller_id !== null || $order->pet_listing_id !== null) {
            throw new AuthorizationException('Marketplace and pet-listing orders do not use PawHaven branch fulfilment.');
        }
    }

    private function assertActorIdentity(User $actor): void
    {
        if (Auth::id() !== $actor->getKey()) {
            throw new AuthorizationException('Fulfilment actions must use the authenticated staff account.');
        }
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function normalizeInput(FulfilmentAction $action, array $input): array
    {
        $note = trim((string) ($input['note'] ?? $input['cancelled_reason'] ?? ''));
        if (mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['note' => 'Notes may not exceed 1,000 characters.']);
        }

        $method = $input['delivery_method'] ?? null;
        if ($method !== null && ! DeliveryMethod::tryFrom((string) $method)) {
            throw ValidationException::withMessages(['delivery_method' => 'Choose a supported delivery method.']);
        }
        $provider = trim((string) ($input['delivery_provider'] ?? ''));
        $tracking = trim((string) ($input['tracking_reference'] ?? ''));
        if (mb_strlen($provider) > 120 || mb_strlen($tracking) > 120) {
            throw ValidationException::withMessages(['delivery_provider' => 'Provider and tracking references may not exceed 120 characters.']);
        }

        $itemId = $input['order_item_id'] ?? null;
        if (in_array($action, [FulfilmentAction::ReportShortage], true)
            && (! is_numeric($itemId) || (int) $itemId < 1)) {
            throw ValidationException::withMessages(['order_item_id' => 'Select the unavailable item from this order.']);
        }

        return [
            'note' => $note !== '' ? $note : null,
            'delivery_method' => $method,
            'delivery_provider' => $provider !== '' ? $provider : null,
            'tracking_reference' => $tracking !== '' ? $tracking : null,
            'order_item_id' => $itemId !== null ? (int) $itemId : null,
            'items_verified' => filter_var($input['items_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** @param array<string, mixed> $data */
    private function payloadHash(FulfilmentAction $action, array $data): string
    {
        return hash('sha256', json_encode([
            'action' => $action->value,
            'note' => $data['note'],
            'delivery_method' => $data['delivery_method'],
            'delivery_provider' => $data['delivery_provider'],
            'tracking_reference' => $data['tracking_reference'],
            'order_item_id' => $data['order_item_id'],
            'items_verified' => $data['items_verified'],
        ], JSON_THROW_ON_ERROR));
    }

    private function assertSameSubmission(
        OrderFulfilmentEvent $event,
        Order $order,
        User $actor,
        FulfilmentAction $action,
        string $payloadHash,
    ): void {
        if ((int) $event->order_id !== (int) $order->getKey()
            || (int) $event->actor_user_id !== (int) $actor->getKey()
            || $event->event_type !== $action->value
            || ! hash_equals((string) $event->payload_hash, $payloadHash)) {
            throw ValidationException::withMessages(['submission_key' => 'This fulfilment form was already used for a different action.']);
        }
    }

    private function buyerMessage(FulfilmentAction $action, Order $order): string
    {
        return match ($action) {
            FulfilmentAction::Confirm => 'Your order was confirmed.',
            FulfilmentAction::StartPicking => 'Your order is being prepared.',
            FulfilmentAction::ReportShortage => 'We are reviewing an item availability issue.',
            FulfilmentAction::ResumePicking => 'Preparation of your order has resumed.',
            FulfilmentAction::Pack => 'Your order has been picked and packed.',
            FulfilmentAction::MarkReady => $order->fulfilment_status === FulfilmentStatus::ReadyForPickup
                ? 'Your order is ready for branch pickup.'
                : 'Your order is ready for dispatch.',
            FulfilmentAction::Dispatch => 'Your order has been handed to delivery.',
            FulfilmentAction::OutForDelivery, FulfilmentAction::RetryDelivery => 'Your order is out for delivery.',
            FulfilmentAction::RecordFailedAttempt => 'A delivery attempt was unsuccessful. The shop will review the next steps.',
            FulfilmentAction::ReturnToSender => 'The delivery was returned to the shop for review.',
            FulfilmentAction::ConfirmHandover => 'Your order was handed over at the branch.',
            FulfilmentAction::Deliver => 'Your order was delivered.',
            FulfilmentAction::Cancel => 'Your order was cancelled before dispatch.',
        };
    }
}
