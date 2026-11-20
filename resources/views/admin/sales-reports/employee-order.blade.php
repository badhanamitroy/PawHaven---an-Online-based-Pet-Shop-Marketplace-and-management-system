@extends('layouts.admin')

@section('title', 'My Order Details')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-xs font-semibold uppercase tracking-wider text-[#69736B]">My sales order</p><h1 class="mt-1 text-2xl font-bold text-[#173F32]">{{ $sale->order_number }}</h1><p class="mt-1 text-sm text-[#69736B]">{{ $sale->created_at?->format('F j, Y g:i A') }} · {{ $sale->sales_channel->value === 'pos' ? 'POS' : 'Online' }}</p></div>
        <a href="{{ route('admin.sales-reports.my') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-[#245C45]">&larr; Back to My Sales</a>
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <section class="rounded-2xl border border-[#245C45]/15 bg-white p-4"><h2 class="text-xs font-bold uppercase tracking-wider text-[#69736B]">Customer</h2><p class="mt-2 font-semibold">{{ $sale->user?->name ?? $sale->customer_name }}</p><p class="text-sm text-[#69736B]">{{ $sale->customer_email }} @if($sale->customer_phone)· {{ $sale->customer_phone }}@endif</p></section>
        <section class="rounded-2xl border border-[#245C45]/15 bg-white p-4"><h2 class="text-xs font-bold uppercase tracking-wider text-[#69736B]">Order and payment status</h2><p class="mt-2 font-semibold">{{ $sale->status->label() }}</p><p class="text-sm text-[#69736B]">Order payment label {{ ucfirst($sale->payment_status->value) }} · {{ $sale->branch?->name ?? 'Branch unassigned' }}</p></section>
    </div>
    <section class="rounded-2xl border border-[#245C45]/15 bg-white p-5">
        <h2 class="font-bold text-[#173F32]">Order items</h2>
        <div class="mt-3 divide-y divide-gray-100">
            @foreach($sale->items as $item)
                <div class="flex justify-between gap-4 py-3"><div><p class="font-semibold">{{ $item->product_name }}</p><p class="text-xs text-[#69736B]">{{ $item->sku }} · {{ $sale->currency }} {{ number_format($item->price_minor / 100, 2) }} × {{ $item->quantity }}</p></div><p class="whitespace-nowrap font-semibold">{{ $sale->currency }} {{ number_format($item->subtotal_minor / 100, 2) }}</p></div>
            @endforeach
        </div>
        <div class="ml-auto mt-4 max-w-xs space-y-2 border-t border-gray-100 pt-4 text-sm">
            <div class="flex justify-between"><span>Subtotal</span><span>{{ $sale->currency }} {{ number_format($sale->subtotal_minor / 100, 2) }}</span></div>
            <div class="flex justify-between"><span>Delivery</span><span>{{ $sale->currency }} {{ number_format($sale->delivery_fee_minor / 100, 2) }}</span></div>
            <div class="flex justify-between text-base font-bold"><span>Order total</span><span>{{ $sale->currency }} {{ number_format($sale->total_minor / 100, 2) }}</span></div>
        </div>
        <div class="mt-5 border-t border-gray-100 pt-4">
            <h2 class="font-bold text-[#173F32]">Payment transactions</h2>
            @forelse($sale->paymentTransactions as $payment)
                <div class="mt-2 rounded-xl bg-gray-50 p-3 text-sm">
                    <p class="font-semibold">{{ $payment->payment_method->label() }} · {{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }} · {{ $payment->status->label() }}</p>
                    @if($payment->transaction_reference)<p class="mt-1 break-all text-xs text-[#69736B]">Reference: {{ $payment->transaction_reference }}</p>@endif
                </div>
            @empty
                <p class="mt-2 text-xs text-[#69736B]">No payment transaction has been recorded for this order.</p>
            @endforelse
            <p class="mt-3 text-xs text-[#69736B]">Only verified transactions are included in collected amounts. Order status, including delivery, does not establish payment.</p>
        </div>
    </section>
</div>
@endsection
