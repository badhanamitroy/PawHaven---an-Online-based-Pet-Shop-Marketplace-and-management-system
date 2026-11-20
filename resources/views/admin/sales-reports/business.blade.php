@extends('layouts.admin')

@section('title', 'Business Sales')

@section('content')
<div class="space-y-5">
    <div><h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Business Sales</h1><p class="mt-1 text-sm text-[#69736B]">Company-wide order and attribution reporting for the Shop Owner.</p></div>
    @include('admin.sales-reports.partials.filters', ['routeName' => 'admin.sales-reports.business'])
    @include('admin.sales-reports.partials.summary')

    <section class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <h2 class="font-bold text-[#173F32]">Branch comparison</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[38rem] text-left text-sm">
                <thead><tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-[#69736B]"><th class="py-2">Branch</th><th class="py-2">Orders</th><th class="py-2">Non-cancelled order value</th><th class="py-2">Cancelled value</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($branches as $branch)
                        <tr><td class="py-2 font-medium">{{ $branch['branch_name'] }}</td><td class="py-2">{{ number_format($branch['order_count']) }}</td><td class="py-2">{{ $summary['currency'] }} {{ number_format($branch['order_value_minor'] / 100, 2) }}</td><td class="py-2">{{ $summary['currency'] }} {{ number_format($branch['cancelled_order_value_minor'] / 100, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-[#69736B]">No orders match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @include('admin.sales-reports.partials.channel-breakdown')
        @include('admin.sales-reports.partials.employee-breakdown')
        @include('admin.sales-reports.partials.attribution-breakdown')
    </div>
    @include('admin.sales-reports.partials.orders', ['showEmployee' => true, 'showBranch' => true, 'detailRoute' => 'admin.orders.show'])
</div>
@endsection
