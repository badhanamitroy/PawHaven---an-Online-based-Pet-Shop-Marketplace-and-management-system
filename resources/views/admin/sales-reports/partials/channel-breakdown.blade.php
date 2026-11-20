<section class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
    <h2 class="font-bold text-[#173F32]">Order channel comparison</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full min-w-[32rem] text-left text-sm">
            <thead><tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-[#69736B]"><th class="py-2">Channel</th><th class="py-2">Orders</th><th class="py-2">Non-cancelled order value</th><th class="py-2">Cancelled value</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($channels as $row)
                    <tr><td class="py-2 font-semibold">{{ $row->channel_value === 'pos' ? 'POS' : ucfirst($row->channel_value) }}</td><td class="py-2">{{ number_format((int) $row->order_count) }}</td><td class="py-2">{{ $summary['currency'] }} {{ number_format((int) $row->order_value_minor / 100, 2) }}</td><td class="py-2">{{ $summary['currency'] }} {{ number_format((int) $row->cancelled_order_value_minor / 100, 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-[#69736B]">No orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
