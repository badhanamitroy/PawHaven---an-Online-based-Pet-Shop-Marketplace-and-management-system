<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\FulfilmentAction;
use App\Enums\FulfilmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BusinessAccessService;
use App\Services\FulfilmentService;
use App\Services\PaymentTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $order->load(['items.product', 'productReturns.product', 'productReturns.orderItem', 'productReturns.requestedBy', 'productReturns.reviewedBy', 'productReturns.receivedBy', 'productReturns.events.actor', 'fulfilmentEvents.actor', 'user', 'paymentTransactions.recordedBy', 'paymentTransactions.verifiedBy', 'paymentTransactions.events.actor', 'paymentTransactions.refunds.initiatedBy', 'paymentTransactions.refunds.approvedBy', 'paymentTransactions.refunds.events.actor']);
        $fulfilmentActions = app(FulfilmentService::class)->availableActions($order);

        $canManagePayments = Gate::allows('update', $order) && $order->seller_id === null && $order->pet_listing_id === null;
        $membership = app(\App\Services\BusinessAccessService::class)->activeMemberships(request()->user())->first();
        $canReviewRefunds = $order->branch_id === null
            ? $membership?->role === \App\Services\BusinessAccessService::OWNER
            : ($membership?->role === \App\Services\BusinessAccessService::MANAGER
                && (int) $membership->branch_id === (int) $order->branch_id);
        $canRequestRefund = $order->branch_id !== null
            && $canManagePayments
            && $membership?->role === \App\Services\BusinessAccessService::OWNER;
        $canReviewReturns = $membership?->role === \App\Services\BusinessAccessService::OWNER
            || ($membership?->role === \App\Services\BusinessAccessService::MANAGER
                && $order->branch_id !== null
                && (int) $membership->branch_id === (int) $order->branch_id);
        $paymentTotals = app(PaymentTransactionService::class)->totalsForOrder($order);

        return view('admin.orders.show', compact('order', 'fulfilmentActions', 'canManagePayments', 'canReviewRefunds', 'canRequestRefund', 'canReviewReturns', 'paymentTotals'));
    }

    public function updateStatus(Request $request, Order $order, FulfilmentService $fulfilment): RedirectResponse
    {
        Gate::authorize('update', $order);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'submission_key' => ['required', 'uuid'],
            'delivery_method' => ['nullable', Rule::enum(\App\Enums\DeliveryMethod::class)],
            'delivery_provider' => ['nullable', 'string', 'max:120'],
            'tracking_reference' => ['nullable', 'string', 'max:120'],
            'cancelled_reason' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $nextStatus = OrderStatus::from($validated['status']);
        $action = match ($nextStatus) {
            OrderStatus::Processing => FulfilmentAction::Confirm,
            OrderStatus::Shipped => FulfilmentAction::Dispatch,
            OrderStatus::Delivered => $order->delivery_method === \App\Enums\DeliveryMethod::BranchPickup
                ? FulfilmentAction::ConfirmHandover
                : FulfilmentAction::Deliver,
            OrderStatus::Cancelled => FulfilmentAction::Cancel,
            default => throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'That order status is not an operational action.']),
        };

        $event = $fulfilment->transition($order, $request->user(), $action, [
            ...$validated,
            'note' => $validated['reason'] ?? $validated['cancelled_reason'] ?? null,
        ]);

        return redirect()->route('admin.orders.show', $event->order_id)
            ->with('success', 'Order fulfilment action recorded.');
    }
}
