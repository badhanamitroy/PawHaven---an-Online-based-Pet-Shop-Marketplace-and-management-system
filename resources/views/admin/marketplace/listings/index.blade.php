@extends('layouts.admin')

@section('title', 'Marketplace Pet Listings')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Pet Marketplace Listings</h1>
            <p class="text-xs text-[#69736B] mt-1">Review community listings, check status, verify seller submissions, and inspect details.</p>
        </div>
        <div>
            <a
                href="{{ route('admin.marketplace.moderation.index') }}"
                class="inline-flex items-center gap-2 rounded-xl bg-[#F28C75] px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#e0755d] transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span>Moderation Queue</span>
            </a>
        </div>
    </div>

    {{-- Filter toolbar --}}
    <div class="rounded-2xl border border-[#245C45]/15 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('admin.marketplace.listings.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search title, location, seller..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                />
            </div>
            <div>
                <select
                    name="category"
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                >
                    <option value="">All Pet Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <select
                    name="status"
                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:outline-none focus:ring-1 focus:ring-[#245C45]"
                >
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\PetListingStatus::cases() as $st)
                        <option value="{{ $st->value }}" {{ request('status') === $st->value ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $st->value)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="rounded-xl bg-[#FAF8F2] border border-[#245C45]/20 px-4 py-2 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                >
                    Filter
                </button>
                @if(request()->hasAny(['search', 'category', 'status']))
                    <a href="{{ route('admin.marketplace.listings.index') }}" class="text-xs text-[#69736B] hover:underline">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Listings Table --}}
    <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white shadow-sm">
        @if($listings->isEmpty())
            <div class="p-12 text-center text-xs text-[#69736B]">
                No pet listings match the selected filters.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-gray-100 bg-[#FAF8F2] text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <tr>
                            <th class="py-3.5 pl-6 pr-3">Pet Listing</th>
                            <th class="px-3 py-3.5">Category &amp; Breed</th>
                            <th class="px-3 py-3.5">Type &amp; Price</th>
                            <th class="px-3 py-3.5">Seller</th>
                            <th class="px-3 py-3.5">Status</th>
                            <th class="py-3.5 pl-3 pr-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($listings as $item)
                            <tr class="hover:bg-gray-50/70 transition-colors">
                                <td class="py-3.5 pl-6 pr-3">
                                    <div class="flex items-center gap-3">
                                        @if($item->images->isNotEmpty())
                                            <img src="{{ $item->images->first()->url() }}" alt="{{ $item->title }}" class="h-10 w-10 rounded-lg object-cover border border-gray-200" />
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#245C45]/10 text-[#245C45]">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-bold text-[#173F32]">{{ $item->title }}</div>
                                            <div class="text-[11px] text-[#69736B]">{{ $item->location }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="font-medium text-[#202820]">{{ $item->petCategory?->name ?? 'General' }}</div>
                                    <div class="text-[11px] text-[#69736B]">{{ $item->breed?->name ?? 'Mixed/Other' }}</div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-gray-700">
                                        {{ $item->listing_type->value }}
                                    </span>
                                    <div class="font-bold text-[#173F32] mt-0.5">
                                        @if($item->price_minor)
                                            {{ $item->currency }} {{ number_format($item->price_minor / 100, 2) }}
                                        @else
                                            <span class="text-emerald-700 text-[11px]">Free / Rehome</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="font-medium text-[#202820]">{{ $item->seller?->name ?? 'Unknown' }}</div>
                                    <div class="text-[11px] text-[#69736B]">{{ $item->seller?->email }}</div>
                                </td>
                                <td class="px-3 py-3.5">
                                    @php
                                        $statusClass = match($item->status) {
                                            \App\Enums\PetListingStatus::Published => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
                                            \App\Enums\PetListingStatus::PendingReview => 'bg-amber-50 text-amber-800 ring-amber-600/20',
                                            \App\Enums\PetListingStatus::Rejected => 'bg-red-50 text-red-800 ring-red-600/20',
                                            \App\Enums\PetListingStatus::Completed => 'bg-blue-50 text-blue-800 ring-blue-600/20',
                                            default => 'bg-gray-100 text-gray-700 ring-gray-500/20',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ring-inset {{ $statusClass }}">
                                        {{ str_replace('_', ' ', $item->status->value) }}
                                    </span>
                                </td>
                                <td class="py-3.5 pl-3 pr-6 text-right">
                                    <a
                                        href="{{ route('admin.marketplace.listings.show', $item) }}"
                                        class="rounded-lg bg-[#FAF8F2] border border-[#245C45]/20 px-3 py-1.5 text-xs font-semibold text-[#173F32] hover:bg-[#245C45] hover:text-white transition-colors"
                                    >
                                        Inspect
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($listings->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $listings->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
