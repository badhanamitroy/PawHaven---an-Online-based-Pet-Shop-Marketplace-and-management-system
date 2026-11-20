<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Services\ProductReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductReturnController extends Controller
{
    public function store(Request $request, Order $order, OrderItem $item, ProductReturnService $returns): RedirectResponse
    {
        Gate::authorize('view', $order);
        $validated = $request->validate([
            'return_submission_key' => ['required', 'uuid'],
            'return_quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $returns->request(
            $order,
            $item,
            $request->user(),
            (int) $validated['return_quantity'],
            $validated['reason'],
            $validated['return_submission_key'],
        );

        return back()->with('success', 'Return request submitted for business review. No stock or payment was changed.');
    }

    public function approve(Request $request, ProductReturn $productReturn, ProductReturnService $returns): RedirectResponse
    {
        $validated = $request->validate(['review_note' => ['required', 'string', 'max:1000']]);
        $reviewed = $returns->review($productReturn, $request->user(), true, $validated['review_note']);

        return redirect()->route('admin.orders.show', $reviewed->order_id)->with('success', 'Return approved for physical receipt. Inventory was not changed.');
    }

    public function reject(Request $request, ProductReturn $productReturn, ProductReturnService $returns): RedirectResponse
    {
        $validated = $request->validate(['review_note' => ['required', 'string', 'max:1000']]);
        $reviewed = $returns->review($productReturn, $request->user(), false, $validated['review_note']);

        return redirect()->route('admin.orders.show', $reviewed->order_id)->with('success', 'Return request rejected and recorded.');
    }

    public function receive(Request $request, ProductReturn $productReturn, ProductReturnService $returns): RedirectResponse
    {
        $validated = $request->validate([
            'receipt_submission_key' => ['required', 'uuid'],
            'received_quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'resellable_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'damaged_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'receipt_note' => ['required', 'string', 'max:1000'],
        ]);
        $received = $returns->receive(
            $productReturn,
            $request->user(),
            (int) $validated['received_quantity'],
            (int) $validated['resellable_quantity'],
            (int) $validated['damaged_quantity'],
            $validated['receipt_note'],
            $validated['receipt_submission_key'],
        );

        return redirect()->route('admin.orders.show', $received->order_id)->with('success', 'Physical receipt recorded. Only the authorized resellable quantity was added to stock.');
    }
}
