<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductReturnStatus;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ProductReturnEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductReturnService
{
    public function __construct(
        private readonly BusinessAccessService $businessAccess,
        private readonly InventoryMovementService $inventory,
    ) {}

    public function request(
        Order $order,
        OrderItem $item,
        User $buyer,
        int $quantity,
        string $reason,
        string $submissionKey,
    ): ProductReturn {
        $this->assertAuthenticated($buyer);
        if ($quantity < 1 || $quantity > 2_147_483_647) {
            throw ValidationException::withMessages(['return_quantity' => 'Return quantity must be a positive whole number.']);
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Provide a return reason of up to 1,000 characters.']);
        }
        if (! Str::isUuid($submissionKey)) {
            throw ValidationException::withMessages(['return_submission_key' => 'The return form has expired. Reload the order before trying again.']);
        }

        try {
            return DB::transaction(function () use ($order, $item, $buyer, $quantity, $reason, $submissionKey): ProductReturn {
                $lockedOrder = Order::query()->withoutGlobalScopes()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                $this->authorizeBuyer($buyer, $lockedOrder);

                $existing = ProductReturn::query()->where('submission_key', $submissionKey)->first();
                if ($existing) {
                    $this->assertSameRequest($existing, $lockedOrder, $item, $buyer, $quantity, $reason);

                    return $existing;
                }

                if ($lockedOrder->status !== OrderStatus::Delivered
                    || $lockedOrder->seller_id !== null
                    || $lockedOrder->pet_listing_id !== null) {
                    throw ValidationException::withMessages(['order' => 'Returns can be requested only for delivered PawHaven product orders.']);
                }

                $lockedItem = OrderItem::query()->whereKey($item->getKey())
                    ->where('order_id', $lockedOrder->getKey())->lockForUpdate()->firstOrFail();
                $product = Product::query()->withoutGlobalScopes()->whereKey($lockedItem->product_id)->lockForUpdate()->first();
                if (! $product
                    || (int) $product->company_id !== (int) $lockedOrder->company_id
                    || ($product->branch_id !== null && (int) $product->branch_id !== (int) $lockedOrder->branch_id)) {
                    throw ValidationException::withMessages(['order_item' => 'This order item is not eligible for an internal inventory return.']);
                }

                $usedQuantity = ProductReturn::query()->where('order_item_id', $lockedItem->getKey())
                    ->whereIn('status', [
                        ProductReturnStatus::Requested->value,
                        ProductReturnStatus::Approved->value,
                        ProductReturnStatus::Received->value,
                    ])->get(['status', 'requested_quantity', 'received_quantity'])
                    ->sum(fn (ProductReturn $return): int => $return->status === ProductReturnStatus::Received
                        ? (int) $return->received_quantity
                        : (int) $return->requested_quantity);

                if ($usedQuantity + $quantity > (int) $lockedItem->quantity) {
                    throw ValidationException::withMessages(['return_quantity' => 'The requested quantity exceeds the unreturned quantity on this order item.']);
                }

                $productReturn = ProductReturn::create([
                    'order_id' => $lockedOrder->getKey(),
                    'order_item_id' => $lockedItem->getKey(),
                    'product_id' => $product->getKey(),
                    'company_id' => $lockedOrder->company_id,
                    'branch_id' => $product->branch_id,
                    'activity_branch_id' => $lockedOrder->branch_id,
                    'requested_by_user_id' => $buyer->getKey(),
                    'submission_key' => $submissionKey,
                    'status' => ProductReturnStatus::Requested,
                    'requested_quantity' => $quantity,
                    'reason' => $reason,
                    'requested_at' => now(),
                ]);
                $this->event($productReturn, $buyer, null, ProductReturnStatus::Requested, $reason);

                return $productReturn;
            }, 3);
        } catch (QueryException $exception) {
            $existing = ProductReturn::query()->where('submission_key', $submissionKey)->first();
            if ($existing) {
                $this->assertSameRequest($existing, $order, $item, $buyer, $quantity, $reason);

                return $existing;
            }

            throw $exception;
        }
    }

    public function review(ProductReturn $productReturn, User $reviewer, bool $approve, string $note): ProductReturn
    {
        $this->assertAuthenticated($reviewer);
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['review_note' => 'Provide a review note of up to 1,000 characters.']);
        }

        return DB::transaction(function () use ($productReturn, $reviewer, $approve, $note): ProductReturn {
            $orderId = ProductReturn::query()->whereKey($productReturn->getKey())->value('order_id');
            $order = Order::query()->withoutGlobalScopes()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $locked = ProductReturn::query()->whereKey($productReturn->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizeReviewer($reviewer, $order);
            if ($locked->status !== ProductReturnStatus::Requested) {
                throw ValidationException::withMessages(['return' => 'Only a requested return can be approved or rejected.']);
            }

            $next = $approve ? ProductReturnStatus::Approved : ProductReturnStatus::Rejected;
            $from = $locked->status;
            $locked->forceFill([
                'status' => $next,
                'reviewed_by_user_id' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->saveQuietly();
            $this->event($locked, $reviewer, $from, $next, $note);

            return $locked->fresh(['requestedBy', 'reviewedBy', 'receivedBy', 'events.actor']);
        }, 3);
    }

    public function receive(
        ProductReturn $productReturn,
        User $receiver,
        int $receivedQuantity,
        int $resellableQuantity,
        int $damagedQuantity,
        string $note,
        string $submissionKey,
    ): ProductReturn {
        $this->assertAuthenticated($receiver);
        $note = trim($note);
        if (! Str::isUuid($submissionKey)) {
            throw ValidationException::withMessages(['receipt_submission_key' => 'The receipt form has expired. Reload the return before trying again.']);
        }
        if ($note === '' || mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['receipt_note' => 'Describe the physical receipt and condition in up to 1,000 characters.']);
        }
        if ($receivedQuantity < 1 || $resellableQuantity < 0 || $damagedQuantity < 0
            || $resellableQuantity + $damagedQuantity !== $receivedQuantity) {
            throw ValidationException::withMessages(['received_quantity' => 'Classify every received unit as resellable or damaged/non-resellable.']);
        }

        return DB::transaction(function () use ($productReturn, $receiver, $receivedQuantity, $resellableQuantity, $damagedQuantity, $note, $submissionKey): ProductReturn {
            $orderId = ProductReturn::query()->whereKey($productReturn->getKey())->value('order_id');
            $order = Order::query()->withoutGlobalScopes()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $locked = ProductReturn::query()->whereKey($productReturn->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizeReviewer($receiver, $order);

            if ($locked->status === ProductReturnStatus::Received
                && $locked->receipt_submission_key === $submissionKey
                && (int) $locked->received_by_user_id === (int) $receiver->getKey()
                && (int) $locked->received_quantity === $receivedQuantity
                && (int) $locked->resellable_quantity === $resellableQuantity
                && (int) $locked->damaged_quantity === $damagedQuantity
                && $locked->receipt_note === $note) {
                return $locked->fresh(['requestedBy', 'reviewedBy', 'receivedBy', 'events.actor']);
            }
            if ($locked->status !== ProductReturnStatus::Approved) {
                throw ValidationException::withMessages(['return' => 'Only an approved return can be recorded as physically received.']);
            }
            if ($receivedQuantity > (int) $locked->requested_quantity) {
                throw ValidationException::withMessages(['received_quantity' => 'Received quantity cannot exceed the approved request.']);
            }

            $lockedItem = OrderItem::query()->whereKey($locked->order_item_id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->withoutGlobalScopes()->whereKey($locked->product_id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedItem->product_id !== (int) $product->getKey()
                || (int) $product->company_id !== (int) $order->company_id
                || (int) ($product->branch_id ?? 0) !== (int) ($locked->branch_id ?? 0)) {
                throw ValidationException::withMessages(['return' => 'The return item no longer matches its original inventory scope.']);
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => ProductReturnStatus::Received,
                'received_by_user_id' => $receiver->getKey(),
                'receipt_submission_key' => $submissionKey,
                'received_quantity' => $receivedQuantity,
                'resellable_quantity' => $resellableQuantity,
                'damaged_quantity' => $damagedQuantity,
                'receipt_note' => $note,
                'received_at' => now(),
            ])->saveQuietly();
            $this->inventory->recordReturnReceipt($locked, $receiver);
            $this->inventory->restockReturn($locked, $receiver, $resellableQuantity);
            $this->event(
                $locked,
                $receiver,
                $from,
                ProductReturnStatus::Received,
                "Received {$receivedQuantity}; resellable {$resellableQuantity}; damaged/non-resellable {$damagedQuantity}. {$note}",
            );

            return $locked->fresh(['requestedBy', 'reviewedBy', 'receivedBy', 'events.actor']);
        }, 3);
    }

    private function authorizeBuyer(User $buyer, Order $order): void
    {
        if (! $buyer->isCustomer()
            || (int) $order->user_id !== (int) $buyer->getKey()) {
            throw new AuthorizationException('Only the customer who placed this order may request its return.');
        }
    }

    private function authorizeReviewer(User $reviewer, Order $order): void
    {
        if (! $reviewer->isStaff()) {
            throw new AuthorizationException('An active Shop Owner or Branch Manager assignment is required.');
        }
        $membership = $this->businessAccess->activeMemberships($reviewer)->first();
        if (! $membership || ! in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)) {
            throw new AuthorizationException('Only the Shop Owner or assigned Branch Manager may review returns.');
        }
        $locked = BusinessMembership::query()->whereKey($membership->getKey())->lockForUpdate()->first();
        if (! $locked?->is_active
            || (int) $locked->user_id !== (int) $reviewer->getKey()
            || ! in_array($locked->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)
            || (int) $locked->company_id !== (int) $order->company_id) {
            throw new AuthorizationException('The return reviewer assignment changed before the action could be completed.');
        }

        $company = Company::query()->withoutGlobalScopes()->whereKey($locked->company_id)
            ->where('is_active', true)->lockForUpdate()->first();
        if (! $company || (int) $company->getKey() !== (int) $locked->company_id) {
            throw new AuthorizationException('This return is outside your active company assignment.');
        }
        $this->businessAccess->installationCompany((int) $company->getKey());

        if ($locked->role === BusinessAccessService::MANAGER) {
            if ($locked->branch_id === null || (int) $locked->branch_id !== (int) $order->branch_id) {
                throw new AuthorizationException('Branch Managers can review returns only for their assigned branch.');
            }
            $branch = Branch::query()->withoutGlobalScopes()->whereKey($locked->branch_id)
                ->where('company_id', $company->getKey())->where('is_active', true)->lockForUpdate()->first();
            if (! $branch) {
                throw new AuthorizationException('The assigned branch is no longer active for this company.');
            }
        } elseif ($locked->branch_id !== null) {
            throw new AuthorizationException('The Shop Owner assignment must have company-wide scope.');
        }
    }

    private function assertSameRequest(
        ProductReturn $existing,
        Order $order,
        OrderItem $item,
        User $buyer,
        int $quantity,
        string $reason,
    ): void {
        if ((int) $existing->order_id !== (int) $order->getKey()
            || (int) $existing->order_item_id !== (int) $item->getKey()
            || (int) $existing->requested_by_user_id !== (int) $buyer->getKey()
            || (int) $existing->requested_quantity !== $quantity
            || $existing->reason !== $reason) {
            throw ValidationException::withMessages(['return_submission_key' => 'This return form was already used for a different request.']);
        }
    }

    private function assertAuthenticated(User $actor): void
    {
        if (Auth::id() !== $actor->getKey()) {
            throw new AuthorizationException('The return action must use the authenticated account.');
        }
    }

    private function event(
        ProductReturn $productReturn,
        User $actor,
        ?ProductReturnStatus $from,
        ProductReturnStatus $to,
        string $note,
    ): void {
        ProductReturnEvent::create([
            'product_return_id' => $productReturn->getKey(),
            'actor_user_id' => $actor->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => trim($note),
        ]);
    }
}
