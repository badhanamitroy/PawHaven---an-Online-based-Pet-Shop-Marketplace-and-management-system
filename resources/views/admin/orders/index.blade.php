@extends('layouts.admin')

@section('title', 'Customer Orders')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Customer Orders</h1>
            <p class="text-xs text-[#69736B] mt-1">Manage single-shop retail orders, Cash on Delivery verification, and delivery lifecycle.</p>
        </div>
    </div>

    {{-- Status Tabs --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 pb-3">
        <a
            href="{{ route('admin.orders.index') }}"
            class="rounded-xl px-3 py-1.5 text-xs font-semibold transition-colors {{ !request('status') ? 'bg-[#245C45] text-white' : 'text-[#69736B] hover:bg-gray-100 hover:text-[#202820]' }}"
        >
            All Orders
        </a>
        <a
            href="{{ route('admin.orders.index', ['status' => 'pending']) }}"
            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-colors {{ request('status') === 'pending' ? 'bg-amber-600 text-white' : 'text-[#69736B] hover:bg-gray-100 hover:text-[#202820]' }}"
        >
            <span>Pending</span>
            @if($pendingCount > 0)
                <span class="rounded-full bg-white/20 px-1.5 py-0.2 text-[10px] font-bold">{{ $pendingCount }}</span>
            @endif
        </a>
        <a
            href="{{ route('admin.orders.index', ['status' => 'processing']) }}"
            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-colors {{ request('status') === 'processing' ? 'bg-blue-600 text-white' : 'text-[#69736B] hover:bg-gray-100 hover:text-[#202820]' }}"
        >
            <span>Processing</span>
            @if($processingCount > 0)
                <span class="rounded-full bg-white/20 px-1.5 py-0.2 text-[10px] font-bold">{{ $processingCount }}</span>
            @endif
        </a>
        <a
            href="{{ route('admin.orders.index', ['status' => 'shipped']) }}"
            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-colors {{ request('status') === 'shipped' ? 'bg-purple-600 text-white' : 'text-[#69736B] hover:bg-gray-100 hover:text-[#202820]' }}"
        >
            <span>Out for Delivery</span>
            @if($shippedCount > 0)
                <span class="rounded-full bg-white/20 px-1.5 py-0.2 text-[10px] font-bold">{{ $shippedCount }}</span>
            @endif
        </a>
        <a
            href="{{ route('admin.orders.index', ['status' => 'delivered']) }}"
            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-colors {{ request('status') === 'delivered' ? 'bg-emerald-600 text-white' : 'text-[#69736B] hover:bg-gray-100 hover:text-[#202820]' }}"
        >
            <span>Delivered</span>
            @if($deliveredCount > 0)
                <span class="rounded-full bg-white/20 px-1.5 py-0.2 text-[10px] font-bold">{{ $deliveredCount }}</span>
            @endif
        </a>
        <a
            href="{{ route('admin.orders.index', ['status' => 'cancelled']) }}"
            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-colors {{ request('status') === 'cancelled' ? 'bg-red-600 text-white' : 'text-[#69736B] hover:bg-gray-100 hover:text-[#202820]' }}"
        >
            <span>Cancelled</span>
            @if($cancelledCount > 0)
                <span class="rounded-full bg-white/20 px-1.5 py-0.2 text-[10px] font-bold">{{ $cancelledCount }}</span>
            @endif
        </a>
    </div>

    {{-- Search Form --}}
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="flex items-center gap-3">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}" />
            @endif
            <div class="flex-1">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search by order number (e.g. PH-2026-...), customer name, or email..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                />
            </div>
            <button
                type="submit"
                class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-4 py-2 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
            >
                Search
            </button>
            @if(request('search'))
                <a
                    href="{{ route('admin.orders.index', request('status') ? ['status' => request('status')] : []) }}"
                    class="text-xs text-[#69736B] hover:underline"
                >
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Orders Table --}}
    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($orders->isEmpty())
            <div class="p-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#FAF8F2] text-[#245C45]">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-bold text-[#173F32]">No orders found</h3>
                <p class="mt-1 text-xs text-[#69736B]">No customer orders match the current status filter or search parameters.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Order Number</th>
                            <th class="px-3 py-3.5">Customer</th>
                            <th class="px-3 py-3.5">Date</th>
                            <th class="px-3 py-3.5">Total</th>
                            <th class="px-3 py-3.5">Payment</th>
                            <th class="px-3 py-3.5">Status</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($orders as $order)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3.5 pl-6 pr-3 font-mono font-bold text-[#173F32]">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="hover:underline">
                                        #{{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="font-bold text-[#202820]">{{ $order->customer_name }}</div>
                                    <div class="text-[11px] text-[#69736B]">{{ $order->customer_email }}</div>
                                </td>
                                <td class="px-3 py-3.5 text-[#69736B]">
                                    {{ $order->created_at?->format('M d, Y H:i') }}
                                </td>
                                <td class="px-3 py-3.5 font-bold text-[#173F32]">
                                    ${{ number_format($order->total_minor / 100, 2) }}
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-700 uppercase">
                                            {{ $order->payment_method }}
                                        </span>
                                        @if($order->payment_status === \App\Enums\PaymentStatus::Paid)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">Paid</span>
                                        @elseif($order->payment_status === \App\Enums\PaymentStatus::Pending)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">Pending</span>
                                        @else
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-800">{{ $order->payment_status->value }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $order->status->badgeClasses() }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td class="py-3.5 pl-3 pr-6 text-right">
                                    <a
                                        href="{{ route('admin.orders.show', $order) }}"
                                        class="rounded-lg bg-[#FAF8F2] border border-[#245C45]/20 px-3 py-1.5 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                                    >
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
