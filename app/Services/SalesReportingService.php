<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Facades\Settings;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PaymentRefundTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportingService
{
    public function __construct(private readonly BusinessAccessService $businessAccess) {}

    public function membershipFor(User $user, string $requiredRole): BusinessMembership
    {
        if (! $user->isStaff()) {
            throw new AuthorizationException('An active business employee assignment is required.');
        }

        $membership = $this->businessAccess->activeMemberships($user)->first();
        if (! $membership || $membership->role !== $requiredRole) {
            throw new AuthorizationException('This report is not available to your business position.');
        }

        return $membership;
    }

    /** @return Builder<Order> */
    public function employeeOrders(User $user, BusinessMembership $membership): Builder
    {
        return Order::query()->withoutGlobalScopes()
            ->where('company_id', $membership->company_id)
            ->where('branch_id', $membership->branch_id)
            ->whereNull('seller_id')
            ->whereNull('pet_listing_id')
            ->where('employee_seller_id', $user->getKey());
    }

    /** @return Builder<Order> */
    public function branchOrders(BusinessMembership $membership): Builder
    {
        return Order::query()->withoutGlobalScopes()
            ->where('company_id', $membership->company_id)
            ->where('branch_id', $membership->branch_id)
            ->whereNull('seller_id')
            ->whereNull('pet_listing_id');
    }

    /** @return Builder<Order> */
    public function companyOrders(BusinessMembership $membership): Builder
    {
        return Order::query()->withoutGlobalScopes()
            ->where('company_id', $membership->company_id)
            ->whereNull('seller_id')
            ->whereNull('pet_listing_id');
    }

    /** @param array<string, string|null> $filters
     *  @return Builder<Order>
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['from'] ?? null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'])->startOfDay());
        }
        if ($filters['to'] ?? null) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['to'])->addDay()->startOfDay());
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['payment_status'] ?? null) {
            $query->where('payment_status', $filters['payment_status']);
        }
        if ($filters['transaction_status'] ?? null) {
            $query->whereHas('paymentTransactions', fn (Builder $transactions): Builder => $transactions->where('status', $filters['transaction_status']));
        }
        if ($filters['channel'] ?? null) {
            $query->where('sales_channel', $filters['channel']);
        }

        return $query;
    }

    /** @return array<string, int|Collection<int, object>> */
    public function summary(Builder $orders): array
    {
        $notCancelled = fn (): Builder => (clone $orders)->where('status', '!=', OrderStatus::Cancelled->value);
        $orderIds = (clone $orders)->select('orders.id')->reorder();
        $activeOrderIds = (clone $notCancelled())->select('orders.id')->reorder();
        $cancelledOrderIds = (clone $orders)->where('status', OrderStatus::Cancelled->value)->select('orders.id')->reorder();
        $currency = (clone $orders)->value('currency') ?? (string) Settings::get('shop.currency', 'USD');
        $transactions = PaymentTransaction::query()->whereIn('order_id', $orderIds);
        $refunds = PaymentRefundTransaction::query()->whereIn(
            'payment_transaction_id',
            PaymentTransaction::query()->select('id')->whereIn('order_id', $orderIds),
        );
        $verifiedByOrder = DB::table('payment_transactions')
            ->select('order_id')->selectRaw('SUM(amount_minor) as verified_minor')
            ->where('status', 'verified')->groupBy('order_id');
        $refundedByOrder = DB::table('payment_refund_transactions')
            ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_refund_transactions.payment_transaction_id')
            ->select('payment_transactions.order_id')->selectRaw('SUM(payment_refund_transactions.amount_minor) as refunded_minor')
            ->where('payment_refund_transactions.status', 'refunded')->groupBy('payment_transactions.order_id');
        $balances = (clone $orders)->leftJoinSub($verifiedByOrder, 'verified_payments', function ($join): void {
            $join->on('verified_payments.order_id', '=', 'orders.id');
        })->leftJoinSub($refundedByOrder, 'refunded_payments', function ($join): void {
            $join->on('refunded_payments.order_id', '=', 'orders.id');
        })->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('SUM(CASE WHEN orders.total_minor > COALESCE(verified_payments.verified_minor, 0) - COALESCE(refunded_payments.refunded_minor, 0) THEN orders.total_minor - COALESCE(verified_payments.verified_minor, 0) + COALESCE(refunded_payments.refunded_minor, 0) ELSE 0 END) as outstanding_minor')
            ->value('outstanding_minor');

        return [
            'currency' => $currency,
            'order_count' => (clone $orders)->count(),
            'order_value_minor' => $notCancelled()->sum('total_minor'),
            'cancelled_order_value_minor' => (clone $orders)->where('status', OrderStatus::Cancelled->value)->sum('total_minor'),
            'recorded_payment_minor' => (clone $transactions)->whereIn('status', ['pending', 'submitted', 'verified'])
                ->whereIn('order_id', $activeOrderIds)
                ->sum('amount_minor'),
            'verified_collected_minor' => (clone $transactions)->where('status', 'verified')
                ->whereIn('order_id', $activeOrderIds)
                ->sum('amount_minor'),
            'gross_verified_collections_minor' => (clone $transactions)->where('status', 'verified')->sum('amount_minor'),
            'successful_refunds_minor' => (clone $refunds)->where('status', 'refunded')->sum('amount_minor'),
            'net_collected_minor' => max(
                0,
                (int) (clone $transactions)->where('status', 'verified')->sum('amount_minor')
                    - (int) (clone $refunds)->where('status', 'refunded')->sum('amount_minor'),
            ),
            'pending_payment_minor' => (clone $transactions)->whereIn('status', ['pending', 'submitted'])->sum('amount_minor'),
            'failed_reversed_minor' => (clone $transactions)->whereIn('status', ['failed', 'reversed'])->sum('amount_minor'),
            'cancelled_verified_minor' => (clone $transactions)->where('status', 'verified')
                ->whereIn('order_id', $cancelledOrderIds)
                ->sum('amount_minor'),
            'outstanding_minor' => (int) ($balances ?? 0),
            'reversed_refunded_minor' => (clone $transactions)->whereIn('status', ['reversed', 'refunded'])->sum('amount_minor')
                + (clone $refunds)->where('status', 'refunded')->sum('amount_minor'),
            'transaction_status_counts' => (clone $transactions)->selectRaw('status as transaction_status_value')
                ->selectRaw('COUNT(*) as transaction_count')->selectRaw('SUM(amount_minor) as amount_minor')
                ->groupBy('status')->get(),
            'payment_status_counts' => (clone $orders)->selectRaw('payment_status as payment_status_value')
                ->selectRaw('COUNT(*) as order_count')->groupBy('payment_status')->get(),
            'order_status_counts' => (clone $orders)->selectRaw('status as order_status_value')
                ->selectRaw('COUNT(*) as order_count')->groupBy('status')->get(),
        ];
    }

    /** @return Collection<int, object> */
    public function channelBreakdown(Builder $orders): Collection
    {
        return (clone $orders)->selectRaw('sales_channel as channel_value')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status != ? THEN total_minor ELSE 0 END) as order_value_minor', [OrderStatus::Cancelled->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN total_minor ELSE 0 END) as cancelled_order_value_minor', [OrderStatus::Cancelled->value])
            ->groupBy('sales_channel')->orderBy('sales_channel')->get();
    }

    /** @return Collection<int, array{employee_id: int|null, employee_name: string, order_count: int, order_value_minor: int}> */
    public function employeeBreakdown(Builder $orders): Collection
    {
        $rows = (clone $orders)->select('employee_seller_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status != ? THEN total_minor ELSE 0 END) as order_value_minor', [OrderStatus::Cancelled->value])
            ->groupBy('employee_seller_id')->orderBy('employee_seller_id')->get();

        $staff = User::query()->where('role', 'staff')
            ->whereIn('id', $rows->pluck('employee_seller_id')->filter()->unique()->values())
            ->get(['id', 'name'])->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'employee_id' => $row->employee_seller_id === null ? null : (int) $row->employee_seller_id,
            'employee_name' => $row->employee_seller_id === null
                ? 'Unattributed'
                : ($staff->get((int) $row->employee_seller_id)?->name ?? 'Unrecognized employee attribution'),
            'order_count' => (int) $row->order_count,
            'order_value_minor' => (int) $row->order_value_minor,
        ]);
    }

    /** @return Collection<int, array{branch_id: int|null, branch_name: string, order_count: int, order_value_minor: int, cancelled_order_value_minor: int}> */
    public function branchBreakdown(Builder $orders): Collection
    {
        $rows = (clone $orders)->select('branch_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status != ? THEN total_minor ELSE 0 END) as order_value_minor', [OrderStatus::Cancelled->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN total_minor ELSE 0 END) as cancelled_order_value_minor', [OrderStatus::Cancelled->value])
            ->groupBy('branch_id')->orderBy('branch_id')->get();

        $branches = Branch::query()->withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('branch_id')->filter()->unique()->values())
            ->get(['id', 'name'])->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'branch_id' => $row->branch_id === null ? null : (int) $row->branch_id,
            'branch_name' => $row->branch_id === null
                ? 'Unassigned branch'
                : ($branches->get((int) $row->branch_id)?->name ?? 'Branch unavailable'),
            'order_count' => (int) $row->order_count,
            'order_value_minor' => (int) $row->order_value_minor,
            'cancelled_order_value_minor' => (int) $row->cancelled_order_value_minor,
        ]);
    }

    /** @return Collection<int, object> */
    public function attributionByChannel(Builder $orders): Collection
    {
        return (clone $orders)
            ->selectRaw('sales_channel as channel_value')
            ->selectRaw("CASE WHEN employee_seller_id IS NULL THEN 'unattributed' ELSE 'employee' END as attribution")
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(CASE WHEN status != ? THEN total_minor ELSE 0 END) as order_value_minor', [OrderStatus::Cancelled->value])
            ->groupBy('sales_channel')
            ->groupByRaw("CASE WHEN employee_seller_id IS NULL THEN 'unattributed' ELSE 'employee' END")
            ->orderBy('sales_channel')->orderBy('attribution')->get();
    }
}
