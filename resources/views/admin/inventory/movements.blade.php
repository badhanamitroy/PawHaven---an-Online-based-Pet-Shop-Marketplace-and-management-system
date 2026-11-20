@extends('layouts.admin')

@section('title', 'Inventory Movement History')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Inventory Movement History</h1>
            <p class="mt-1 max-w-3xl text-xs text-[#69736B]">Stock is held on each product record. Company-shared stock remains one shared bucket; order activity is attributed to its originating branch. Delivered quantities are order quantities, not proof of collected payment.</p>
        </div>
        <a href="{{ route('admin.inventory.index') }}" class="inline-flex w-fit items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-[#245C45]">Current stock</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm"><p class="text-[11px] font-semibold text-[#69736B]">Current stock in visible buckets</p><p class="mt-2 text-2xl font-bold text-[#173F32]">{{ number_format($summary['current_stock']) }}</p><p class="mt-1 text-[10px] text-[#69736B]">{{ $summary['product_count'] }} products</p></div>
        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 shadow-sm"><p class="text-[11px] font-semibold text-blue-900">Delivered quantity · POS / online</p><p class="mt-2 text-2xl font-bold text-blue-900">{{ number_format($summary['pos_delivered_quantity']) }} / {{ number_format($summary['online_delivered_quantity']) }}</p><p class="mt-1 text-[10px] text-blue-800">Delivered orders placed within the selected dates.</p></div>
        <div class="rounded-2xl border border-violet-100 bg-violet-50 p-4 shadow-sm"><p class="text-[11px] font-semibold text-violet-900">Received returns · resellable / damaged</p><p class="mt-2 text-2xl font-bold text-violet-900">{{ number_format($summary['return_received_quantity']) }} · {{ number_format($summary['return_resellable_quantity']) }} / {{ number_format($summary['return_damaged_quantity']) }}</p><p class="mt-1 text-[10px] text-violet-800">Resellable quantity is restored only after receipt is authorized.</p></div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-sm"><p class="text-[11px] font-semibold text-amber-900">Ledger discrepancies</p><p class="mt-2 text-2xl font-bold text-amber-900">{{ number_format($summary['inventory_discrepancy_count']) }}</p><p class="mt-1 text-[10px] text-amber-800">Visible product stock balances that differ from the full ledger.</p></div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-gray-100 bg-white p-3 text-xs"><span class="text-[#69736B]">Reserved POS / online</span><p class="mt-1 font-bold text-[#173F32]">{{ number_format($summary['pos_reserved_quantity']) }} / {{ number_format($summary['online_reserved_quantity']) }}</p></div>
        <div class="rounded-xl border border-gray-100 bg-white p-3 text-xs"><span class="text-[#69736B]">Cancellation releases</span><p class="mt-1 font-bold text-[#173F32]">{{ number_format($summary['cancellation_released_quantity']) }}</p></div>
        <div class="rounded-xl border border-gray-100 bg-white p-3 text-xs"><span class="text-[#69736B]">Manual adjustment · net quantity</span><p class="mt-1 font-bold text-[#173F32]">{{ number_format($summary['manual_adjustment_net']) }}</p></div>
    </div>

    <form method="GET" action="{{ route('admin.inventory.movements') }}" class="grid gap-3 rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-6">
        <label class="text-[11px] font-semibold text-[#69736B]">From<input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"></label>
        <label class="text-[11px] font-semibold text-[#69736B]">To<input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"></label>
        <label class="text-[11px] font-semibold text-[#69736B]">Product<select name="product_id" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"><option value="">All visible products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>{{ $product->name }} · {{ $product->sku }}</option>@endforeach</select></label>
        @if($membership->role === \App\Services\BusinessAccessService::OWNER)
            <label class="text-[11px] font-semibold text-[#69736B]">Branch activity<select name="branch_id" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"><option value="">All branches and shared activity</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
        @endif
        <label class="text-[11px] font-semibold text-[#69736B]">Movement type<select name="movement_type" class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-2 text-xs"><option value="">All movement types</option>@foreach(\App\Enums\InventoryMovementType::cases() as $movementType)<option value="{{ $movementType->value }}" @selected(($filters['movement_type'] ?? '') === $movementType->value)>{{ $movementType->label() }}</option>@endforeach</select></label>
        <div class="flex items-end gap-2"><button class="w-full rounded-lg bg-[#245C45] px-3 py-2 text-xs font-semibold text-white">Apply filters</button><a href="{{ route('admin.inventory.movements') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600">Clear</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($movements->isEmpty())
            <p class="p-8 text-center text-xs text-[#69736B]">No inventory movements match the selected filters.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[950px] text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[10px] font-bold uppercase tracking-wider text-[#69736B]"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Product</th><th class="px-4 py-3">Movement</th><th class="px-4 py-3 text-right">Change</th><th class="px-4 py-3 text-right">Stock after</th><th class="px-4 py-3">Stock bucket / activity branch</th><th class="px-4 py-3">Reference / actor</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($movements as $movement)
                            <tr class="align-top hover:bg-gray-50/60">
                                <td class="whitespace-nowrap px-4 py-3 text-[#69736B]">{{ $movement->created_at?->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3"><div class="font-semibold text-[#173F32]">{{ $movement->product?->name ?? 'Unavailable product' }}</div><div class="font-mono text-[10px] text-[#69736B]">{{ $movement->product?->sku }}</div></td>
                                <td class="px-4 py-3"><div class="font-semibold text-[#202820]">{{ $movement->movement_type->label() }}</div><div class="mt-1 max-w-xs text-[10px] text-[#69736B]">{{ $movement->note }}</div></td>
                                <td class="px-4 py-3 text-right font-bold {{ $movement->quantity_delta < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-[#202820]">{{ $movement->quantity_after ?? '—' }}</td>
                                <td class="px-4 py-3 text-[#69736B]">{{ $movement->branch?->name ?? 'Shared company stock' }}<span class="mx-1">/</span>{{ $movement->activityBranch?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-[#69736B]">@if($movement->order)<a class="font-mono font-semibold text-[#245C45] hover:underline" href="{{ route('admin.orders.show', $movement->order) }}">{{ $movement->order->order_number }}</a><br>@endif{{ $movement->actor?->name ?? 'System baseline' }}<div class="mt-1 break-all font-mono text-[9px]">{{ $movement->reference_key }}</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($movements->hasPages())<div class="border-t border-gray-100 p-4">{{ $movements->links() }}</div>@endif
        @endif
    </div>
</div>
@endsection
