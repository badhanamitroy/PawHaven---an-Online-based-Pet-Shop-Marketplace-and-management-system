@extends('layouts.admin')

@section('title', 'Payment Transaction Details')

@section('content')
<div class="mx-auto max-w-2xl space-y-5">
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-[#69736B]">Payment transaction</p>
        <h1 class="mt-1 text-xl font-bold text-[#173F32]">Order {{ $payment->order->order_number }}</h1>
        <dl class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-[#69736B]">Method</dt><dd class="font-semibold">{{ $payment->payment_method->label() }}</dd></div>
            <div><dt class="text-xs text-[#69736B]">Amount</dt><dd class="font-semibold">{{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }}</dd></div>
            <div><dt class="text-xs text-[#69736B]">Status</dt><dd class="font-semibold">{{ $payment->status->label() }}</dd></div>
            <div><dt class="text-xs text-[#69736B]">Recorded</dt><dd class="font-semibold">{{ $payment->created_at?->format('M j, Y g:i A') }}</dd></div>
            @if($payment->transaction_reference)<div class="sm:col-span-2"><dt class="text-xs text-[#69736B]">Reference</dt><dd class="break-all font-mono">{{ $payment->transaction_reference }}</dd></div>@endif
            @if($payment->verified_at)<div><dt class="text-xs text-[#69736B]">Verified by</dt><dd class="font-semibold">{{ $payment->verifiedBy?->name ?? 'Unavailable' }}</dd></div><div><dt class="text-xs text-[#69736B]">Verified at</dt><dd class="font-semibold">{{ $payment->verified_at->format('M j, Y g:i A') }}</dd></div>@endif
        </dl>
        <p class="mt-5 rounded-xl bg-amber-50 p-3 text-xs text-amber-900">This page documents a payment submission. Only a verified transaction is counted as collected. It does not confirm a gateway response.</p>
    </div>
    <a href="{{ route('admin.sales-desk.receipt', $payment->order) }}" class="inline-flex rounded-xl bg-[#245C45] px-4 py-2.5 text-sm font-semibold text-white">Back to order receipt</a>
</div>
@endsection
