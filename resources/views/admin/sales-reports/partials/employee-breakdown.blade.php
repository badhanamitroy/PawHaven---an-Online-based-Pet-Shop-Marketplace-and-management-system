<section class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
    <h2 class="font-bold text-[#173F32]">Employee attribution</h2>
    <p class="mt-1 text-xs text-[#69736B]">Order value excludes cancelled orders and is not a collected-payment figure.</p>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full min-w-[28rem] text-left text-sm">
            <thead><tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-[#69736B]"><th class="py-2">Employee / attribution</th><th class="py-2">Orders</th><th class="py-2">Non-cancelled order value</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($employees as $employee)
                    <tr><td class="py-2 font-medium">{{ $employee['employee_name'] }}</td><td class="py-2">{{ number_format($employee['order_count']) }}</td><td class="py-2">{{ $summary['currency'] }} {{ number_format($employee['order_value_minor'] / 100, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-[#69736B]">No attributed orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
