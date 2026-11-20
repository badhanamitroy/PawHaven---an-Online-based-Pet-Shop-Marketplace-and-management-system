<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\SalesChannel;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    private const MAX_STOCK = 2_147_483_647;

    public function __construct(private readonly BusinessAccessService $businessAccess) {}

    public function recordOpening(Product $product, ?User $actor, string $note = 'Opening stock recorded when product was created.'): InventoryMovement
    {
        return DB::transaction(function () use ($product, $actor, $note): InventoryMovement {
            $locked = Product::query()->withoutGlobalScopes()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $reference = 'opening:product:'.$locked->getKey();
            $existing = InventoryMovement::query()->where('reference_key', $reference)->first();
            if ($existing) {
                return $existing;
            }

            return $this->movement($locked, InventoryMovementType::Opening, (int) $locked->stock_quantity, (int) $locked->stock_quantity, $reference, $note, $actor);
        }, 3);
    }

    public function reserveForOrder(Order $order, OrderItem $item, Product $product, User $actor): InventoryMovement
    {
        $this->assertAuthenticatedActor($actor);

        return DB::transaction(function () use ($order, $item, $product, $actor): InventoryMovement {
            $reference = 'reservation:order-item:'.$item->getKey();
            $lockedOrder = Order::query()->withoutGlobalScopes()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $lockedItem = OrderItem::query()->whereKey($item->getKey())->where('order_id', $lockedOrder->getKey())->lockForUpdate()->firstOrFail();
            $lockedProduct = Product::query()->withoutGlobalScopes()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $this->assertReservationContext($lockedOrder, $lockedItem, $lockedProduct, $actor);

            $existing = InventoryMovement::query()->where('reference_key', $reference)->first();
            if ($existing) {
                if ((int) $existing->product_id !== (int) $lockedProduct->getKey()
                    || (int) $existing->order_id !== (int) $lockedOrder->getKey()
                    || (int) $existing->order_item_id !== (int) $lockedItem->getKey()
                    || (int) $existing->actor_user_id !== (int) $actor->getKey()
                    || (int) $existing->quantity_delta !== -abs((int) $lockedItem->quantity)) {
                    throw new AuthorizationException('This order item already has a different inventory reservation.');
                }

                return $existing;
            }

            $this->ensureOpeningBalance($lockedProduct, $actor);

            $quantity = (int) $lockedItem->quantity;
            if ($quantity < 1 || $lockedProduct->stock_quantity < $quantity) {
                throw ValidationException::withMessages(['cart' => 'The available stock changed before the order could reserve it.']);
            }

            $updated = Product::query()->withoutGlobalScopes()
                ->whereKey($lockedProduct->getKey())->where('stock_quantity', '>=', $quantity)
                ->decrement('stock_quantity', $quantity);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['cart' => 'The available stock changed before the order could reserve it.']);
            }

            return $this->movement(
                $lockedProduct,
                $lockedOrder->sales_channel === SalesChannel::PointOfSale
                    ? InventoryMovementType::PosReservation
                    : InventoryMovementType::OnlineReservation,
                -$quantity,
                (int) $lockedProduct->stock_quantity - $quantity,
                $reference,
                $lockedOrder->sales_channel === SalesChannel::PointOfSale
                    ? 'Stock reserved for a POS order.'
                    : 'Stock reserved for an online order.',
                $actor,
                $lockedOrder,
                $lockedItem,
                activityBranchId: $lockedOrder->branch_id,
            );
        }, 3);
    }

    public function releaseForCancellation(Order $order, User $actor): void
    {
        $this->assertAuthenticatedActor($actor);
        if ($order->status !== OrderStatus::Cancelled) {
            throw new AuthorizationException('Reserved stock can be released only as part of an allowed order cancellation.');
        }
        if ($order->inventory_reserved_at === null) {
            return;
        }

        foreach ($order->items()->whereNotNull('product_id')->orderBy('id')->get() as $item) {
            $this->releaseOrderItem($order, $item, $actor);
        }

        $order->inventory_reserved_at = null;
        $order->save();
    }

    public function adjustToQuantity(
        Product $product,
        User $actor,
        int $targetQuantity,
        string $reason,
        string $submissionKey,
    ): ?InventoryMovement {
        $this->assertAuthenticatedActor($actor);
        if ($targetQuantity < 0 || $targetQuantity > self::MAX_STOCK) {
            throw ValidationException::withMessages(['stock_quantity' => 'Stock quantity is outside the supported range.']);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['stock_adjustment_reason' => 'Provide a reason for the stock adjustment.']);
        }
        if (! Str::isUuid($submissionKey)) {
            throw ValidationException::withMessages(['adjustment_key' => 'The adjustment form has expired. Reload the page before trying again.']);
        }

        try {
            return DB::transaction(function () use ($product, $actor, $targetQuantity, $reason, $submissionKey): ?InventoryMovement {
                $existing = InventoryMovement::query()->where('reference_key', 'manual:'.$submissionKey)->first();
                if ($existing) {
                    if ((int) $existing->product_id !== (int) $product->getKey()
                        || (int) $existing->quantity_after !== $targetQuantity
                        || $existing->note !== $reason) {
                        throw ValidationException::withMessages(['adjustment_key' => 'This adjustment form was already used for a different change.']);
                    }

                    return $existing;
                }

                $membership = $this->ownerMembership($actor);
                $lockedProduct = Product::query()->withoutGlobalScopes()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $lockedProduct->company_id !== (int) $membership->company_id
                    || ! $this->businessAccess->canAccessRecord($actor, $lockedProduct->company_id, $lockedProduct->branch_id)) {
                    throw new AuthorizationException('This product is outside your company inventory.');
                }
                $this->assertActiveStockBranch($lockedProduct);
                $this->ensureOpeningBalance($lockedProduct, $actor);

                if ((int) $lockedProduct->stock_quantity === $targetQuantity) {
                    return null;
                }
                $delta = $targetQuantity - (int) $lockedProduct->stock_quantity;
                $lockedProduct->forceFill(['stock_quantity' => $targetQuantity])->save();

                return $this->movement(
                    $lockedProduct,
                    InventoryMovementType::ManualAdjustment,
                    $delta,
                    $targetQuantity,
                    'manual:'.$submissionKey,
                    $reason,
                    $actor,
                    activityBranchId: $lockedProduct->branch_id,
                );
            }, 3);
        } catch (QueryException $exception) {
            $existing = InventoryMovement::query()->where('reference_key', 'manual:'.$submissionKey)->first();
            if ($existing
                && (int) $existing->product_id === (int) $product->getKey()
                && (int) $existing->quantity_after === $targetQuantity
                && $existing->note === $reason) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function restockReturn(ProductReturn $return, User $actor, int $quantity): ?InventoryMovement
    {
        $this->assertAuthenticatedActor($actor);
        if ($quantity < 1) {
            return null;
        }

        $reference = 'return:'.$return->getKey().':restock';
        $existing = InventoryMovement::query()->where('reference_key', $reference)->first();
        if ($existing) {
            if ((int) $existing->quantity_delta !== $quantity) {
                throw ValidationException::withMessages(['received_quantity' => 'This return already has a different stock-restock movement.']);
            }

            return $existing;
        }

        $product = Product::query()->withoutGlobalScopes()->whereKey($return->product_id)->lockForUpdate()->firstOrFail();
        if ((int) $product->company_id !== (int) $return->company_id
            || (int) ($product->branch_id ?? 0) !== (int) ($return->branch_id ?? 0)
            || (int) $product->stock_quantity + $quantity > self::MAX_STOCK) {
            throw ValidationException::withMessages(['resellable_quantity' => 'The returned quantity cannot be safely added to this product stock.']);
        }
        $newStock = (int) $product->stock_quantity + $quantity;
        $this->ensureOpeningBalance($product, $actor);
        $product->forceFill(['stock_quantity' => $newStock])->save();

        return $this->movement(
            $product,
            InventoryMovementType::ReturnRestock,
            $quantity,
            $newStock,
            $reference,
            'Physically received, authorized resellable quantity: '.$quantity.'. '.$return->receipt_note,
            $actor,
            $return->order,
            $return->orderItem,
            $return,
            $return->activity_branch_id,
        );
    }

    /** Record physical receipt and inspection without changing available stock. */
    public function recordReturnReceipt(ProductReturn $return, User $actor): void
    {
        $this->assertAuthenticatedActor($actor);
        if ($return->status !== \App\Enums\ProductReturnStatus::Received
            || (int) $return->received_by_user_id !== (int) $actor->getKey()) {
            throw new AuthorizationException('Physical return receipt must be recorded by its authorized receiver.');
        }

        $product = Product::query()->withoutGlobalScopes()->whereKey($return->product_id)->lockForUpdate()->firstOrFail();
        if ((int) $product->company_id !== (int) $return->company_id
            || (int) ($product->branch_id ?? 0) !== (int) ($return->branch_id ?? 0)) {
            throw ValidationException::withMessages(['return' => 'The received return no longer matches its original inventory scope.']);
        }
        $this->ensureOpeningBalance($product, $actor);

        $receivedReference = 'return:'.$return->getKey().':received';
        if (! InventoryMovement::query()->where('reference_key', $receivedReference)->exists()) {
            $this->movement(
                $product,
                InventoryMovementType::ReturnReceived,
                0,
                (int) $product->stock_quantity,
                $receivedReference,
                'Physically received quantity: '.(int) $return->received_quantity.'. '.$return->receipt_note,
                $actor,
                $return->order,
                $return->orderItem,
                $return,
                $return->activity_branch_id,
            );
        }

        if ((int) $return->damaged_quantity > 0) {
            $damagedReference = 'return:'.$return->getKey().':damaged';
            if (! InventoryMovement::query()->where('reference_key', $damagedReference)->exists()) {
                $this->movement(
                    $product,
                    InventoryMovementType::ReturnDamaged,
                    0,
                    (int) $product->stock_quantity,
                    $damagedReference,
                    'Inspected damaged/non-resellable quantity: '.(int) $return->damaged_quantity.'. '.$return->receipt_note,
                    $actor,
                    $return->order,
                    $return->orderItem,
                    $return,
                    $return->activity_branch_id,
                );
            }
        }
    }

    private function releaseOrderItem(Order $order, OrderItem $item, User $actor): void
    {
        $reference = 'cancellation:order-item:'.$item->getKey();
        if (InventoryMovement::query()->where('reference_key', $reference)->exists()) {
            return;
        }

        $product = Product::query()->withoutGlobalScopes()->whereKey($item->product_id)->lockForUpdate()->first();
        if (! $product || (int) $product->company_id !== (int) $order->company_id) {
            throw ValidationException::withMessages(['order' => 'Reserved inventory could not be reconciled for this order item.']);
        }
        $this->ensureOpeningBalance($product, $actor);
        if ((int) $product->stock_quantity + (int) $item->quantity > self::MAX_STOCK) {
            throw ValidationException::withMessages(['order' => 'The stock release exceeds the supported product quantity.']);
        }
        $newStock = (int) $product->stock_quantity + (int) $item->quantity;
        $product->forceFill(['stock_quantity' => $newStock])->save();
        $this->movement(
            $product,
            InventoryMovementType::CancellationRelease,
            (int) $item->quantity,
            $newStock,
            $reference,
            'Reserved stock released after an allowed order cancellation.',
            $actor,
            $order,
            $item,
            activityBranchId: $order->branch_id,
        );
    }

    private function assertReservationContext(Order $order, OrderItem $item, Product $product, User $actor): void
    {
        if ($order->inventory_reserved_at === null
            || (int) $item->product_id !== (int) $product->getKey()
            || (int) $product->company_id !== (int) $order->company_id
            || (int) $product->stock_quantity < 0
            || ($product->branch_id !== null && (int) $product->branch_id !== (int) $order->branch_id)) {
            throw new AuthorizationException('The order item does not match an available inventory scope.');
        }

        $expectedActor = $order->employee_seller_id ?? $order->user_id;
        if ($expectedActor === null || (int) $expectedActor !== (int) $actor->getKey()) {
            throw new AuthorizationException('The inventory reservation actor does not match the order.');
        }
    }

    private function ownerMembership(User $actor): BusinessMembership
    {
        $membership = $this->businessAccess->activeMemberships($actor)->first();
        if (Auth::id() !== $actor->getKey() || ! $actor->isStaff()
            || ! $membership
            || $membership->role !== BusinessAccessService::OWNER
            || $membership->branch_id !== null) {
            throw new AuthorizationException('Only the authenticated Shop Owner can adjust stock.');
        }

        $locked = BusinessMembership::query()->whereKey($membership->getKey())->lockForUpdate()->first();
        if (! $locked?->is_active
            || (int) $locked->user_id !== (int) $actor->getKey()
            || $locked->role !== BusinessAccessService::OWNER
            || $locked->branch_id !== null) {
            throw new AuthorizationException('The Shop Owner assignment changed before the stock adjustment could be applied.');
        }

        $company = Company::query()->withoutGlobalScopes()->whereKey($locked->company_id)
            ->where('is_active', true)->lockForUpdate()->first();
        if (! $company || (int) $company->getKey() !== (int) $locked->company_id) {
            throw new AuthorizationException('The Shop Owner company assignment is no longer active.');
        }
        $this->businessAccess->installationCompany((int) $company->getKey());

        return $locked;
    }

    private function ensureOpeningBalance(Product $product, ?User $actor): void
    {
        $reference = 'opening:product:'.$product->getKey();
        if (! InventoryMovement::query()->where('reference_key', $reference)->exists()) {
            $this->movement(
                $product,
                InventoryMovementType::Opening,
                (int) $product->stock_quantity,
                (int) $product->stock_quantity,
                $reference,
                'Opening stock baseline captured before the first tracked inventory operation.',
                $actor,
            );
        }
    }

    private function assertActiveStockBranch(Product $product): void
    {
        if ($product->branch_id === null) {
            return;
        }
        $branch = Branch::query()->withoutGlobalScopes()->whereKey($product->branch_id)
            ->where('company_id', $product->company_id)->where('is_active', true)->lockForUpdate()->first();
        if (! $branch) {
            throw new AuthorizationException('The product inventory branch is not active for this company.');
        }
    }

    private function movement(
        Product $product,
        InventoryMovementType $type,
        int $delta,
        int $quantityAfter,
        string $reference,
        string $note,
        ?User $actor,
        ?Order $order = null,
        ?OrderItem $item = null,
        ?ProductReturn $return = null,
        ?int $activityBranchId = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'product_id' => $product->getKey(),
            'company_id' => $product->company_id,
            'branch_id' => $product->branch_id,
            'activity_branch_id' => $activityBranchId,
            'order_id' => $order?->getKey(),
            'order_item_id' => $item?->getKey(),
            'product_return_id' => $return?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'movement_type' => $type,
            'quantity_delta' => $delta,
            'quantity_after' => max(0, $quantityAfter),
            'reference_key' => $reference,
            'note' => trim($note) !== '' ? trim($note) : $type->label(),
        ]);
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        if (Auth::id() !== $actor->getKey()) {
            throw new AuthorizationException('Inventory movements must be attributed to the authenticated user.');
        }
    }
}
