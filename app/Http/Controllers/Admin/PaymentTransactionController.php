<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\BusinessAccessService;
use App\Services\PaymentTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentTransactionController extends Controller
{
    public function store(Request $request, Order $order, PaymentTransactionService $payments): RedirectResponse
    {
        $validated = $request->validate([
            'payment_submission_key' => ['required', 'uuid'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_amount' => ['required', 'string', 'regex:/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/'],
            'payment_reference' => ['nullable', 'string', 'max:120', 'required_unless:payment_method,cash'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->requireManagerNote($request, 'note');

        $payments->recordBusinessPayment(
            $order,
            $request->user(),
            PaymentMethod::from($validated['payment_method']),
            $payments->amountToMinor($validated['payment_amount']),
            $validated['payment_reference'] ?? null,
            $validated['note'] ?? null,
            $validated['payment_submission_key'],
        );

        return redirect()->route('admin.orders.show', $order)->with('success', 'Payment submitted for verification. It is not counted as collected yet.');
    }

    public function verify(Request $request, PaymentTransaction $paymentTransaction, PaymentTransactionService $payments): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $payments->verify($paymentTransaction, $request->user(), false, $validated['note']);

        return redirect()->route('admin.orders.show', $paymentTransaction->order_id)->with('success', 'Payment marked verified and collected.');
    }

    public function fail(Request $request, PaymentTransaction $paymentTransaction, PaymentTransactionService $payments): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $payments->verify($paymentTransaction, $request->user(), true, $validated['note']);

        return redirect()->route('admin.orders.show', $paymentTransaction->order_id)->with('success', 'Payment submission marked failed.');
    }

    public function receipt(Request $request, PaymentTransaction $paymentTransaction): View
    {
        $paymentTransaction->load(['order.items', 'order.branch', 'recordedBy', 'verifiedBy']);
        abort_unless((int) $paymentTransaction->order->employee_seller_id === (int) $request->user()->getKey(), 404);

        app(\App\Services\EmployeeSalesDeskService::class)->membershipFor($request->user());

        return view('admin.sales-desk.payment-receipt', ['payment' => $paymentTransaction]);
    }

    private function requireManagerNote(Request $request, string $field): void
    {
        $membership = app(BusinessAccessService::class)->activeMemberships($request->user())->first();
        if ($membership?->role === BusinessAccessService::MANAGER && trim((string) $request->input($field)) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => 'A reason is required when a Branch Manager records payment information.',
            ]);
        }
    }
}
