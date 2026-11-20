<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentRefundTransaction;
use App\Services\PaymentRefundService;
use App\Services\PaymentTransactionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaymentRefundController extends Controller
{
    public function request(Request $request, Order $order, PaymentRefundService $refunds, PaymentTransactionService $payments): RedirectResponse
    {
        Gate::authorize('view', $order);
        $validated = $request->validate([
            'payment_transaction_id' => ['required', 'integer'],
            'refund_submission_key' => ['required', 'uuid'],
            'refund_amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $payment = $order->paymentTransactions()->findOrFail((int) $validated['payment_transaction_id']);

        $refunds->request(
            $payment,
            $request->user(),
            $payments->amountToMinor($validated['refund_amount']),
            $validated['reason'],
            $validated['refund_submission_key'],
        );

        return back()->with('success', 'Refund request recorded for independent review. It is not counted as refunded yet.');
    }

    public function complete(Request $request, PaymentRefundTransaction $refund, PaymentRefundService $refunds): RedirectResponse
    {
        $validated = $request->validate(['approval_note' => ['required', 'string', 'max:1000']]);
        $completed = $refunds->approve($refund, $request->user(), $validated['approval_note']);

        return redirect()->route('admin.orders.show', $completed->paymentTransaction->order_id)
            ->with('success', 'Refund completion recorded with approval evidence.');
    }

    public function reject(Request $request, PaymentRefundTransaction $refund, PaymentRefundService $refunds): RedirectResponse
    {
        $validated = $request->validate(['approval_note' => ['required', 'string', 'max:1000']]);
        $rejected = $refunds->reject($refund, $request->user(), $validated['approval_note']);

        return redirect()->route('admin.orders.show', $rejected->paymentTransaction->order_id)
            ->with('success', 'Refund request rejected and recorded.');
    }
}
