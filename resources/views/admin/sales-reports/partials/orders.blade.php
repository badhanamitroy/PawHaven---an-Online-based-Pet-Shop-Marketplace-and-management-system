<section class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
    <h2 class="font-bold text-[#173F32]">Orders</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full min-w-[58rem] text-left text-sm">
            <thead><tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-[#69736B]"><th class="py-2 pr-3">Reference / date</th><th class="py-2 pr-3">Customer</th>@if($showEmployee)<th class="py-2 pr-3">Employee</th>@endif @if($showBranch)<th class="py-2 pr-3">Branch</th>@endif<th class="py-2 pr-3">Channel</th><th class="py-2 pr-3">Order status</th><th class="py-2 pr-3">Payments</th><th class="py-2 pr-3">Order total</th><th class="py-2">Details</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($orders as $order)
                    <tr>
                        <td class="py-3 pr-3"><span class="font-mono font-semibold text-[#173F32]">{{ $order->order_number }}</span><span class="mt-0.5 block text-xs text-[#69736B]">{{ $order->created_at?->format('M j, Y g:i A') }}</span></td>
                        <td class="py-3 pr-3">{{ $order->user?->name ?? $order->customer_name }}<span class="block text-xs text-[#69736B]">{{ $order->customer_email }}</span></td>
                        @if($showEmployee)<td class="py-3 pr-3">{{ $order->employeeSeller?->role === 'staff' ? $order->employeeSeller->name : 'Unattributed' }}</td>@endif
                        @if($showBranch)<td class="py-3 pr-3">{{ $order->branch?->name ?? 'Unassigned' }}</td>@endif
                        <td class="py-3 pr-3">{{ $order->sales_channel->value === 'pos' ? 'POS' : 'Online' }}</td>
                        <td class="py-3 pr-3"><span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span></td>
                        <td class="py-3 pr-3">@forelse($order->paymentTransactions as $payment)<span class="block">{{ $payment->status->label() }}</span><span class="text-xs text-[#69736B]">{{ $payment->currency }} {{ number_format($payment->amount_minor / 100, 2) }} · {{ $payment->payment_method->label() }}</span>@empty<span class="text-xs text-[#69736B]">No transaction</span>@endforelse</td>
                        <td class="py-3 pr-3 font-semibold">{{ $order->currency }} {{ number_format($order->total_minor / 100, 2) }}</td>
                        <td class="py-3"><a class="font-semibold text-[#245C45] hover:underline" href="{{ route($detailRoute, $order->id) }}">View details</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-6 text-center text-[#69736B]">No orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</section>
