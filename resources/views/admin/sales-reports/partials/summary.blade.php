<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-[#69736B]">Orders in filter</p><p class="mt-2 text-2xl font-bold text-[#173F32]">{{ number_format($summary['order_count']) }}</p><p class="mt-1 text-[11px] text-[#69736B]">Includes cancelled orders.</p></div>
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm"><p class="text-xs font-semibold text-[#69736B]">Non-cancelled order value</p><p class="mt-2 text-2xl font-bold text-[#173F32]">{{ $summary['currency'] }} {{ number_format($summary['order_value_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-[#69736B]">Order totals, not collected revenue.</p></div>
    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm"><p class="text-xs font-semibold text-sky-900">Recorded payment amount</p><p class="mt-2 text-2xl font-bold text-sky-900">{{ $summary['currency'] }} {{ number_format($summary['recorded_payment_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-sky-800">Submitted and verified transactions for non-cancelled orders.</p></div>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm"><p class="text-xs font-semibold text-emerald-900">Gross verified collections</p><p class="mt-2 text-2xl font-bold text-emerald-900">{{ $summary['currency'] }} {{ number_format($summary['gross_verified_collections_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-emerald-800">All verified transactions, including collections on cancelled orders.</p></div>
    <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm"><p class="text-xs font-semibold text-violet-900">Successful refunds</p><p class="mt-2 text-2xl font-bold text-violet-900">{{ $summary['currency'] }} {{ number_format($summary['successful_refunds_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-violet-800">Completed refunds only; pending requests are excluded.</p></div>
    <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4 shadow-sm"><p class="text-xs font-semibold text-teal-900">Net collected</p><p class="mt-2 text-2xl font-bold text-teal-900">{{ $summary['currency'] }} {{ number_format($summary['net_collected_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-teal-800">Gross verified collections less successful refunds.</p></div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm"><p class="text-xs font-semibold text-amber-900">Outstanding order balance</p><p class="mt-2 text-2xl font-bold text-amber-900">{{ $summary['currency'] }} {{ number_format($summary['outstanding_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-amber-800">Order value less net verified collections (after completed refunds) for non-cancelled orders.</p></div>
    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4 shadow-sm"><p class="text-xs font-semibold text-orange-900">Pending payment submissions</p><p class="mt-2 text-2xl font-bold text-orange-900">{{ $summary['currency'] }} {{ number_format($summary['pending_payment_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-orange-800">Submitted but not verified.</p></div>
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm"><p class="text-xs font-semibold text-rose-900">Failed / reversed payments</p><p class="mt-2 text-2xl font-bold text-rose-900">{{ $summary['currency'] }} {{ number_format($summary['failed_reversed_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-rose-800">Excluded from verified collections.</p></div>
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm"><p class="text-xs font-semibold text-rose-900">Cancelled order value</p><p class="mt-2 text-2xl font-bold text-rose-900">{{ $summary['currency'] }} {{ number_format($summary['cancelled_order_value_minor'] / 100, 2) }}</p><p class="mt-1 text-[11px] text-rose-800">Order value shown separately from refunds.</p></div>
</div>

<div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
    Verified amounts on cancelled orders: <strong>{{ $summary['currency'] }} {{ number_format($summary['cancelled_verified_minor'] / 100, 2) }}</strong>. Included in gross collections above; shown here for cancellation context.
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <section class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <h2 class="font-bold text-[#173F32]">Order status breakdown</h2>
        <div class="mt-3 divide-y divide-gray-100">
            @php($orderStatusCounts = $summary['order_status_counts']->keyBy('order_status_value'))
            @foreach(\App\Enums\OrderStatus::cases() as $status)
                <div class="flex justify-between py-2 text-sm"><span>{{ $status->label() }}</span><span class="font-semibold">{{ number_format((int) ($orderStatusCounts->get($status->value)?->order_count ?? 0)) }}</span></div>
            @endforeach
        </div>
    </section>
    <section class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <h2 class="font-bold text-[#173F32]">Payment transaction status breakdown</h2>
        <div class="mt-3 divide-y divide-gray-100">
            @php($transactionStatusCounts = $summary['transaction_status_counts']->keyBy('transaction_status_value'))
            @foreach(\App\Enums\PaymentTransactionStatus::cases() as $status)
                <div class="flex justify-between gap-3 py-2 text-sm"><span>{{ $status->label() }}</span><span class="text-right font-semibold">{{ number_format((int) ($transactionStatusCounts->get($status->value)?->transaction_count ?? 0)) }} · {{ $summary['currency'] }} {{ number_format((int) ($transactionStatusCounts->get($status->value)?->amount_minor ?? 0) / 100, 2) }}</span></div>
            @endforeach
        </div>
        <p class="mt-3 border-t border-gray-100 pt-3 text-[11px] text-[#69736B]">Legacy order payment labels are not used to calculate collections.</p>
    </section>
</div>
