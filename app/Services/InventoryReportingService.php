<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryReportingService
{
    /** @param array<string, mixed> $filters */
    public function movementQuery(BusinessMembership $membership, array $filters): Builder
    {
        $query = InventoryMovement::query()
            ->with(['product:id,name,sku,stock_quantity,branch_id', 'branch:id,name', 'activityBranch:id,name', 'order:id,order_number,sales_channel,branch_id', 'orderItem:id,product_name', 'actor:id,name'])
            ->where('company_id', $membership->company_id);

        if ($membership->role === BusinessAccessService::MANAGER) {
            $query->where(function (Builder $scope) use ($membership): void {
                $scope->where('branch_id', $membership->branch_id)
                    ->orWhere('activity_branch_id', $membership->branch_id);
            });
        } elseif (isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $branchId = (int) $filters['branch_id'];
            $query->where(function (Builder $scope) use ($branchId): void {
                $scope->where('branch_id', $branchId)->orWhere('activity_branch_id', $branchId);
            });
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }
        if (! empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', date('Y-m-d', strtotime($filters['to'].' +1 day')).' 00:00:00');
        }

        return $query->latest('created_at')->latest('id');
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, int>
     */
    public function summary(BusinessMembership $membership, array $filters): array
    {
        $products = Product::query()->withoutGlobalScopes()->where('company_id', $membership->company_id);
        if ($membership->role === BusinessAccessService::MANAGER) {
            $products->where('branch_id', $membership->branch_id);
        } elseif (! empty($filters['branch_id'])) {
            $products->where('branch_id', (int) $filters['branch_id']);
        }
        if (! empty($filters['product_id'])) {
            $products->whereKey((int) $filters['product_id']);
        }

        $productRows = $products->get(['id', 'stock_quantity', 'branch_id']);
        $productIds = $productRows->pluck('id');
        $movements = $this->movementQuery($membership, $filters);
        $typeTotals = (clone $movements)->select('movement_type')
            ->selectRaw('SUM(quantity_delta) as net_quantity')
            ->selectRaw('SUM(CASE WHEN quantity_delta < 0 THEN -quantity_delta ELSE quantity_delta END) as moved_quantity')
            ->groupBy('movement_type')->get()->keyBy('movement_type');

        $ledgerBalances = InventoryMovement::query()->where('company_id', $membership->company_id)
            ->whereIn('product_id', $productIds)
            ->select('product_id')->selectRaw('SUM(quantity_delta) as balance')
            ->groupBy('product_id')->pluck('balance', 'product_id');
        $discrepancyCount = $productRows->filter(fn (Product $product): bool =>
            (int) $product->stock_quantity !== (int) ($ledgerBalances[$product->getKey()] ?? 0)
        )->count();

        $sales = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.company_id', $membership->company_id)
            ->where('orders.status', OrderStatus::Delivered->value)
            ->whereNull('orders.seller_id')->whereNull('orders.pet_listing_id')
            ->whereNotNull('order_items.product_id');
        if ($membership->role === BusinessAccessService::MANAGER) {
            $sales->where('orders.branch_id', $membership->branch_id);
        } elseif (! empty($filters['branch_id'])) {
            $sales->where('orders.branch_id', (int) $filters['branch_id']);
        }
        if (! empty($filters['product_id'])) {
            $sales->where('order_items.product_id', (int) $filters['product_id']);
        }
        if (! empty($filters['from'])) {
            $sales->where('orders.created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $sales->where('orders.created_at', '<', date('Y-m-d', strtotime($filters['to'].' +1 day')).' 00:00:00');
        }
        $salesByChannel = $sales->select('orders.sales_channel')->selectRaw('SUM(order_items.quantity) as quantity')
            ->groupBy('orders.sales_channel')->pluck('quantity', 'orders.sales_channel');

        $returns = ProductReturn::query()->where('company_id', $membership->company_id)->where('status', 'received');
        if ($membership->role === BusinessAccessService::MANAGER) {
            $returns->where('activity_branch_id', $membership->branch_id);
        } elseif (! empty($filters['branch_id'])) {
            $returns->where('activity_branch_id', (int) $filters['branch_id']);
        }
        if (! empty($filters['product_id'])) {
            $returns->where('product_id', (int) $filters['product_id']);
        }
        if (! empty($filters['from'])) {
            $returns->where('received_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $returns->where('received_at', '<', date('Y-m-d', strtotime($filters['to'].' +1 day')).' 00:00:00');
        }

        $type = fn (InventoryMovementType $movementType): int => (int) ($typeTotals[$movementType->value]->moved_quantity ?? 0);

        return [
            'current_stock' => (int) $productRows->sum('stock_quantity'),
            'product_count' => $productRows->count(),
            'pos_reserved_quantity' => $type(InventoryMovementType::PosReservation),
            'online_reserved_quantity' => $type(InventoryMovementType::OnlineReservation),
            'cancellation_released_quantity' => $type(InventoryMovementType::CancellationRelease),
            'manual_adjustment_net' => (int) ($typeTotals[InventoryMovementType::ManualAdjustment->value]->net_quantity ?? 0),
            'pos_delivered_quantity' => (int) ($salesByChannel['pos'] ?? 0),
            'online_delivered_quantity' => (int) ($salesByChannel['online'] ?? 0),
            'return_received_quantity' => (int) (clone $returns)->sum('received_quantity'),
            'return_resellable_quantity' => (int) (clone $returns)->sum('resellable_quantity'),
            'return_damaged_quantity' => (int) (clone $returns)->sum('damaged_quantity'),
            'inventory_discrepancy_count' => $discrepancyCount,
        ];
    }

    /** @return iterable<Branch> */
    public function branchOptions(BusinessMembership $membership): iterable
    {
        if ($membership->role === BusinessAccessService::MANAGER) {
            return Branch::query()->whereKey($membership->branch_id)->get();
        }

        return Branch::query()->where('company_id', $membership->company_id)->orderBy('name')->get();
    }
}
