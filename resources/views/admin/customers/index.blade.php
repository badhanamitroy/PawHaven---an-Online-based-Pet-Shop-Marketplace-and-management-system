@extends('layouts.admin')

@section('title', 'Customers Directory')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Customer Directory</h1>
            <p class="text-xs text-[#69736B] mt-1">Customer accounts, order histories, and marketplace activity.</p>
        </div>
        <div class="text-xs text-[#69736B]">
            Total Customers: <strong class="text-[#173F32]">{{ $totalCustomersCount }}</strong>
        </div>
    </div>

    {{-- Filter toolbar --}}
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search customers by name or email..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                />
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-4 py-2 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                >
                    Filter
                </button>
                @if(request()->has('search'))
                    <a href="{{ route('admin.customers.index') }}" class="text-xs text-[#69736B] hover:underline">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Customers Table --}}
    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($customers->isEmpty())
            <div class="p-8 text-center text-xs text-[#69736B]">
                No customers match the search criteria.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">User</th>
                            <th class="px-3 py-3.5">Role</th>
                            <th class="px-3 py-3.5 text-center">Shop Orders</th>
                            <th class="px-3 py-3.5">Total Spent</th>
                            <th class="px-3 py-3.5 text-center">Pet Listings</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Registered</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($customers as $customer)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3.5 pl-6 pr-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#245C45]/10 font-bold text-xs text-[#245C45]">
                                            {{ strtoupper(substr($customer->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-[#173F32]">{{ $customer->name }}</div>
                                            <div class="text-[11px] text-[#69736B]">{{ $customer->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase text-gray-700 ring-1 ring-inset ring-gray-500/20">
                                        Customer
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-center font-bold text-[#173F32]">
                                    {{ $customer->orders_count }}
                                </td>
                                <td class="px-3 py-3.5 font-bold text-[#245C45]">
                                    @php
                                        $spentMinor = $spendTotals[$customer->id] ?? 0;
                                    @endphp
                                    ${{ number_format($spentMinor / 100, 2) }}
                                </td>
                                <td class="px-3 py-3.5 text-center font-bold text-[#69736B]">
                                    {{ $customer->pet_listings_count }}
                                </td>
                                <td class="py-3.5 pl-3 pr-6 text-right text-[#69736B]">
                                    {{ $customer->created_at?->format('M d, Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($customers->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $customers->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
