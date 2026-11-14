<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Services\OrderInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Order::class);

        $query = Order::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        $pendingCount = Order::where('status', OrderStatus::Pending)->count();
        $processingCount = Order::where('status', OrderStatus::Processing)->count();
        $shippedCount = Order::where('status', OrderStatus::Shipped)->count();
        $deliveredCount = Order::where('status', OrderStatus::Delivered)->count();
        $cancelledCount = Order::where('status', OrderStatus::Cancelled)->count();

        return view('admin.orders.index', compact(
            'orders',
            'pendingCount',
            'processingCount',
            'shippedCount',
            'deliveredCount',
            'cancelledCount'
        ));
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);
        $order->load(['items.product', 'user']);

        // Determine allowed next statuses
        $allowedTransitions = [];
        foreach (OrderStatus::cases() as $case) {
            if ($order->status->canTransitionTo($case)) {
                $allowedTransitions[] = $case;
            }
        }

        return view('admin.orders.show', compact('order', 'allowedTransitions'));
    }

    public function updateStatus(Request $request, Order $order, OrderInventoryService $inventory): RedirectResponse
    {
        Gate::authorize('update', $order);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'cancelled_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:500'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->requireManagerAuditReason($request, $validated['reason'] ?? $validated['cancelled_reason'] ?? null);

        $result = DB::transaction(function () use ($validated, $order, $inventory, $request): array {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($request->user())->authorize('update', $order);
            $nextStatus = OrderStatus::from($validated['status']);

            if (! $order->status->canTransitionTo($nextStatus)) {
                return ['updated' => false, 'order' => $order, 'next_status' => $nextStatus];
            }

            $oldStatus = $order->status;
            $order->status = $nextStatus;

            if ($nextStatus === OrderStatus::Cancelled) {
                $order->cancelled_reason = $validated['cancelled_reason'] ?? null;
                $inventory->restoreReservedInventory($order);
            }

            if ($nextStatus === OrderStatus::Delivered && $order->payment_method === 'cod') {
                $order->payment_status = PaymentStatus::Paid;
            }

            $order->save();

            AdminAuditLog::record(
                'order.status_update',
                "Order #{$order->order_number} status updated from {$oldStatus->value} to {$nextStatus->value}.",
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'from_status' => $oldStatus->value,
                    'to_status' => $nextStatus->value,
                    'previous_value' => $oldStatus->value,
                    'new_value' => $nextStatus->value,
                    'cancelled_reason' => $order->cancelled_reason,
                    'reason' => $validated['reason'] ?? null,
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                ]
            );

            return ['updated' => true, 'order' => $order, 'next_status' => $nextStatus];
        });

        $order = $result['order'];
        $nextStatus = $result['next_status'];

        if (! $result['updated']) {
            return redirect()->route('admin.orders.show', $order)
                ->with('error', "Cannot transition order from {$order->status->label()} to {$nextStatus->label()}.");
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order #{$order->order_number} status updated to {$nextStatus->label()}.");
    }

    public function updatePayment(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('update', $order);
        $validated = $request->validate([
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->requireManagerAuditReason($request, $validated['reason'] ?? null);

        [$nextPaymentStatus, $oldPaymentStatus] = DB::transaction(function () use ($order, $validated, $request): array {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($request->user())->authorize('update', $order);
            $old = $order->payment_status;
            $next = PaymentStatus::from($validated['payment_status']);
            $order->payment_status = $next;
            $order->save();

            AdminAuditLog::record(
                'order.payment_update',
                "Order #{$order->order_number} payment status updated from {$old->value} to {$next->value}.",
                [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                    'old_payment_status' => $old->value,
                    'new_payment_status' => $next->value,
                    'previous_value' => $old->value,
                    'new_value' => $next->value,
                    'reason' => $validated['reason'] ?? null,
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                ]
            );

            return [$next, $old];
        });

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order #{$order->order_number} payment status updated to {$nextPaymentStatus->label()}.");
    }

    private function requireManagerAuditReason(Request $request, ?string $reason): void
    {
        $isManager = app(\App\Services\BusinessAccessService::class)->activeMemberships($request->user())
            ->contains(fn ($membership): bool => $membership->role === \App\Services\BusinessAccessService::MANAGER);

        if ($isManager && trim((string) $reason) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reason' => 'A reason is required when a Branch Manager updates an order record.',
            ]);
        }
    }
}
