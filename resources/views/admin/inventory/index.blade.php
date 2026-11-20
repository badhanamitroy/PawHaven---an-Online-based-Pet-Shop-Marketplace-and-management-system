@extends('layouts.admin')

@section('title', 'Inventory & Stock Alerts')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Inventory &amp; Stock Alerts</h1>
            <p class="text-xs text-[#69736B] mt-1">Current product stock by its company or branch inventory bucket.</p>
        </div>
        <a href="{{ route('admin.inventory.movements') }}" class="inline-flex w-fit items-center rounded-xl bg-[#245C45] px-4 py-2 text-xs font-semibold text-white">Stock movement history</a>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#69736B]">Total Units in Stock</span>
                <span class="rounded-lg bg-emerald-50 p-2 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </span>
            </div>
            <div class="mt-3 text-2xl font-bold text-[#173F32]">{{ number_format($totalItemsInStock) }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Across all active products</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#69736B]">Low Stock Alerts</span>
                <span class="rounded-lg bg-amber-50 p-2 text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </span>
            </div>
            <div class="mt-3 text-2xl font-bold text-amber-700">{{ $lowStockTotal }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Products at or below threshold</div>
        </div>

        <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-[#69736B]">Out of Stock</span>
                <span class="rounded-lg bg-red-50 p-2 text-red-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </span>
            </div>
            <div class="mt-3 text-2xl font-bold text-red-700">{{ $outOfStockTotal }}</div>
            <div class="mt-1 text-[11px] text-[#69736B]">Requires restocking</div>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <a
                    href="{{ route('admin.inventory.index') }}"
                    class="rounded-xl px-3 py-1.5 text-xs font-semibold {{ !request('filter') ? 'bg-[#245C45] text-white' : 'bg-gray-100 text-[#69736B] hover:text-[#202820]' }}"
                >
                    All Items
                </a>
                <a
                    href="{{ route('admin.inventory.index', ['filter' => 'low_stock']) }}"
                    class="rounded-xl px-3 py-1.5 text-xs font-semibold {{ request('filter') === 'low_stock' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-[#69736B] hover:text-[#202820]' }}"
                >
                    Low Stock ({{ $lowStockTotal }})
                </a>
                <a
                    href="{{ route('admin.inventory.index', ['filter' => 'out_of_stock']) }}"
                    class="rounded-xl px-3 py-1.5 text-xs font-semibold {{ request('filter') === 'out_of_stock' ? 'bg-red-600 text-white' : 'bg-gray-100 text-[#69736B] hover:text-[#202820]' }}"
                >
                    Out of Stock ({{ $outOfStockTotal }})
                </a>
            </div>

            <div class="w-full sm:w-72">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search name or SKU..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                />
            </div>
        </form>
    </div>

    {{-- Inventory Table --}}
    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($products->isEmpty())
            <div class="p-8 text-center text-xs text-[#69736B]">
                No inventory items match the current criteria.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Product</th>
                            <th class="px-3 py-3.5">Stock Branch</th>
                            <th class="px-3 py-3.5">Category</th>
                            <th class="px-3 py-3.5">SKU</th>
                            <th class="px-3 py-3.5">Stock Level</th>
                            <th class="px-3 py-3.5">Threshold</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Quick Stock Adjustment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($products as $prod)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3.5 pl-6 pr-3">
                                    <div class="font-bold text-[#173F32]">{{ $prod->name }}</div>
                                    <div class="text-[11px] text-[#69736B]">{{ $prod->currency }} {{ number_format($prod->price_minor / 100, 2) }}</div>
                                </td>
                                <td class="px-3 py-3.5 text-[#69736B]">{{ $prod->branch?->name ?? 'Shared company stock' }}</td>
                                <td class="px-3 py-3.5 text-[#69736B]">{{ $prod->category->name }}</td>
                                <td class="px-3 py-3.5 font-mono text-[11px] text-[#173F32] font-semibold">{{ $prod->sku }}</td>
                                <td class="px-3 py-3.5">
                                    @if($prod->stock_quantity <= 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-red-800">
                                            Out of Stock (0)
                                        </span>
                                    @elseif($prod->isLowStock())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">
                                            Low: {{ $prod->stock_quantity }} units
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                            {{ $prod->stock_quantity }} units
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 text-[#69736B]">
                                    Alert at &le; {{ $prod->low_stock_threshold }}
                                </td>
                                <td class="py-3.5 pl-3 pr-6 text-right">
                                    <form
                                        method="POST"
                                        action="{{ route('admin.inventory.update', $prod) }}"
                                        class="inline-flex items-center gap-2"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="adjustment_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <div class="flex items-center gap-1">
                                            <span class="text-[10px] text-gray-400 font-semibold">Qty:</span>
                                            <input
                                                type="number"
                                                min="0"
                                                name="stock_quantity"
                                                value="{{ $prod->stock_quantity }}"
                                                class="w-16 rounded-lg border border-gray-200 px-2 py-1 text-xs text-center text-[#202820] focus:border-[#245C45] focus:outline-none"
                                            />
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="text-[10px] text-gray-400 font-semibold">Alert:</span>
                                            <input
                                                type="number"
                                                min="0"
                                                name="low_stock_threshold"
                                                value="{{ $prod->low_stock_threshold }}"
                                                class="w-14 rounded-lg border border-gray-200 px-2 py-1 text-xs text-center text-[#202820] focus:border-[#245C45] focus:outline-none"
                                            />
                                        </div>
                                        <input
                                            type="text"
                                            name="stock_adjustment_reason"
                                            required
                                            maxlength="1000"
                                            placeholder="Adjustment reason"
                                            class="w-36 rounded-lg border border-gray-200 px-2 py-1 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none"
                                        />
                                        <button
                                            type="submit"
                                            class="rounded-lg bg-[#FAF8F2] border border-[#245C45]/20 px-2.5 py-1 text-[11px] font-bold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                                        >
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $products->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

