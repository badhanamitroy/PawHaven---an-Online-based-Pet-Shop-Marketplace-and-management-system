@extends('layouts.admin')

@section('title', 'Sales Desk Order Confirmation')

@section('content')
<style>
    @media print {
        body * { visibility: hidden !important; }
        .sales-desk-receipt, .sales-desk-receipt * { visibility: visible !important; }
        .sales-desk-receipt { position: absolute; inset: 0; width: 100%; max-width: none; border: 0 !important; box-shadow: none !important; }
        .sales-desk-no-print { display: none !important; }
    }
</style>
<div class="sales-desk-receipt mx-auto max-w-3xl space-y-5">
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
        <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Order created</p>
        <h1 class="mt-1 text-2xl font-bold text-[#173F32]">Sale reference {{ $sale->order_number }}</h1>
        <p class="mt-2 text-sm text-emerald-900">The order was recorded for this branch. Payment claims remain unverified until a Shop Owner or Branch Manager confirms collection.</p>
    </div>

    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><p class="text-xs font-semibold uppercase tracking-wide text-[#69736B]">Customer</p><p class="mt-1 font-semibold text-[#202820]">{{ $sale->customer_name }}</p><p class="text-sm text-[#69736B]">{{ $sale->customer_email }} · {{ $sale->customer_phone }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-[#69736B]">Branch &amp; status</p><p class="mt-1 font-semibold text-[#202820]">{{ $sale->branch?->name ?? 'Assigned branch' }} · {{ $sale->status->label() }}</p><p class="text-sm text-[#69736B]">POS order · In-store pickup</p></div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[28rem] text-left text-sm">
                <thead><tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-[#69736B]"><th class="py-2 pr-4">Product</th><th class="py-2 pr-4">Qty</th><th class="py-2 pr-4">Unit price</th><th class="py-2 text-right">Line total</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($sale->items as $item)
                        <tr><td class="py-3 pr-4 font-medium text-[#202820]">{{ $item->product_name }}<span class="block text-xs text-[#69736B]">{{ $item->sku }}</span>@if(($item->promotion_discount_minor ?? 0) > 0 || ($item->coupon_discount_minor ?? 0) > 0)<span class="block text-xs text-emerald-700">Discounts: −{{ $sale->currency }} {{ number_format(($item->promotion_discount_minor + $item->coupon_discount_minor) / 100, 2) }}</span>@endif</td><td class="py-3 pr-4">{{ $item->quantity }}</td><td class="py-3 pr-4">{{ $sale->currency }} {{ number_format($item->price_minor / 100, 2) }}</td><td class="py-3 text-right font-semibold">{{ $sale->currency }} {{ number_format($item->subtotal_minor / 100, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ml-auto mt-4 max-w-xs space-y-2 border-t border-gray-100 pt-4 text-sm">
            @if(($sale->promotion_discount_minor ?? 0) + ($sale->coupon_discount_minor ?? 0) > 0)<div class="flex justify-between"><span>Merchandise before discounts</span><span>{{ $sale->currency }} {{ number_format(($sale->subtotal_minor + $sale->promotion_discount_minor + $sale->coupon_discount_minor) / 100, 2) }}</span></div>@endif
            <div class="flex justify-between"><span>Subtotal after discounts</span><span>{{ $sale->currency }} {{ number_format($sale->subtotal_minor / 100, 2) }}</span></div>
            @if(($sale->promotion_discount_minor ?? 0) > 0)<div class="flex justify-between text-emerald-700"><span>Promotion discounts</span><span>−{{ $sale->currency }} {{ number_format($sale->promotion_discount_minor / 100, 2) }}</span></div>@endif
            @if(($sale->coupon_discount_minor ?? 0) > 0)<div class="flex justify-between text-emerald-700"><span>Coupon discounts</span><span>−{{ $sale->currency }} {{ number_format($sale->coupon_discount_minor / 100, 2) }}</span></div>@endif
            <div class="flex justify-between"><span>Delivery</span><span>{{ $sale->currency }} {{ number_format($sale->delivery_fee_minor / 100, 2) }}</span></div>
            <div class="flex justify-between text-base font-bold text-[#173F32]"><span>Total</span><span>{{ $sale->currency }} {{ number_format($sale->total_minor / 100, 2) }}</span></div>
        </div>
        @if($sale->paymentTransactions->isNotEmpty())
            <div class="mt-5 border-t border-gray-100 pt-4">
                <h2 class="text-sm font-bold text-[#173F32]">Payment submissions</h2>
                @foreach($sale->paymentTransactions as $payment)
                    <div class="mt-2 rounded-xl bg-gray-50 p-3 text-sm">
                        <p class="font-semibold">{{ $payment->payment_method->label() }} · {{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }} · {{ $payment->status->label() }}</p>
                        @if($payment->transaction_reference)<p class="mt-1 text-xs text-[#69736B]">Reference: {{ $payment->transaction_reference }}</p>@endif
                        @if($payment->verified_at)<p class="mt-1 text-xs text-emerald-800">Verified by {{ $payment->verifiedBy?->name ?? 'authorized staff' }} on {{ $payment->verified_at->format('M j, Y g:i A') }}</p>@else<p class="mt-1 text-xs text-amber-800">Not verified or counted as collected.</p>@endif
                        <a class="sales-desk-no-print mt-2 inline-block text-xs font-semibold text-[#245C45] underline" href="{{ route('admin.sales-desk.payment-receipt', $payment) }}">View payment receipt details</a>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mt-4 border-t border-gray-100 pt-4 text-sm text-[#69736B]">No payment transaction was submitted with this order.</p>
        @endif
        <div class="sales-desk-no-print mt-5 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-between">
            <a href="{{ route('admin.sales-desk.index') }}" class="rounded-xl bg-[#245C45] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[#173F32]">Start another sale</a>
            <button type="button" onclick="window.print()" class="rounded-xl border border-[#245C45] px-4 py-2.5 text-center text-sm font-semibold text-[#245C45] hover:bg-[#245C45]/5">Print receipt details</button>
        </div>
    </div>
</div>
@endsection
