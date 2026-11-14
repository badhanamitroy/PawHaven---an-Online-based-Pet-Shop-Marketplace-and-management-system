<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerRequestController extends Controller
{
    /**
     * Display a listing of purchase and adoption requests assigned to the seller.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $statusFilter = $request->query('status', 'all');
        $paymentFilter = $request->query('payment');

        $query = Order::query()
            ->where('seller_id', $user->id)
            ->with(['items.product', 'petListing.images', 'user'])
            ->latest();

        if ($paymentFilter === 'pending') {
            $query->where('payment_status', PaymentStatus::Pending)
                ->where('status', '!=', OrderStatus::Cancelled);
        } elseif ($paymentFilter === 'paid') {
            $query->where('payment_status', PaymentStatus::Paid);
        }

        match ($statusFilter) {
            'pending' => $query->where('status', OrderStatus::Pending),
            'awaiting' => $query->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing]),
            'processing' => $query->where('status', OrderStatus::Processing),
            'shipped' => $query->where('status', OrderStatus::Shipped),
            'delivered' => $query->where('status', OrderStatus::Delivered),
            'cancelled' => $query->where('status', OrderStatus::Cancelled),
            default => null,
        };

        $orders = $query->paginate(10)->withQueryString();

        $counts = [
            'all' => Order::where('seller_id', $user->id)->count(),
            'pending' => Order::where('seller_id', $user->id)->where('status', OrderStatus::Pending)->count(),
            'awaiting' => Order::where('seller_id', $user->id)->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing])->count(),
            'processing' => Order::where('seller_id', $user->id)->where('status', OrderStatus::Processing)->count(),
            'shipped' => Order::where('seller_id', $user->id)->where('status', OrderStatus::Shipped)->count(),
            'delivered' => Order::where('seller_id', $user->id)->where('status', OrderStatus::Delivered)->count(),
            'cancelled' => Order::where('seller_id', $user->id)->where('status', OrderStatus::Cancelled)->count(),
            'pending_payment' => Order::where('seller_id', $user->id)->where('payment_status', PaymentStatus::Pending)->where('status', '!=', OrderStatus::Cancelled)->count(),
        ];

        return view('seller.requests.index', [
            'orders' => $orders,
            'currentFilter' => $statusFilter,
            'paymentFilter' => $paymentFilter,
            'counts' => $counts,
        ]);
    }

    /**
     * Display the specified purchase or adoption request.
     */
    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['items.product', 'petListing.images', 'user']);

        return view('seller.requests.show', [
            'order' => $order,
        ]);
    }

    /**
     * Progress or decline the request according to business rules.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:processing,shipped,delivered,cancelled'],
        ]);

        $nextStatus = OrderStatus::from($validated['status']);

        if (! $order->status->canTransitionTo($nextStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition request from '{$order->status->label()}' to '{$nextStatus->label()}'.",
            ]);
        }

        $order->status = $nextStatus;

        // When delivery is confirmed for Cash on Delivery / handover, verify payment automatically
        if ($nextStatus === OrderStatus::Delivered && $order->payment_method === 'cod') {
            $order->payment_status = PaymentStatus::Paid;
        }

        $order->save();

        return back()->with('success', "Request #{$order->order_number} status updated to '{$nextStatus->label()}'.");
    }

    /**
     * Record a verified payment for the request.
     */
    public function verifyPayment(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'payment_status' => ['required', 'string', 'in:paid,refunded'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $order->payment_status = PaymentStatus::from($validated['payment_status']);

        if (! empty($validated['payment_reference'])) {
            $order->payment_reference = $validated['payment_reference'];
        }

        $order->save();

        return back()->with('success', "Payment status for request #{$order->order_number} updated to '{$order->payment_status->label()}'.");
    }
}
