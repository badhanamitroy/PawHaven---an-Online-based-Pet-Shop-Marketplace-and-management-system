@extends('layouts.admin')

@section('title', 'Pet Listing - ' . $listing->title)

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-[#69736B]">
                <a href="{{ route('admin.marketplace.listings.index') }}" class="hover:text-[#245C45]">Pet Marketplace</a>
                <span>&rsaquo;</span>
                <span>Listing Details</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">{{ $listing->title }}</h1>
                @php
                    $statusClass = match($listing->status) {
                        \App\Enums\PetListingStatus::Published => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
                        \App\Enums\PetListingStatus::PendingReview => 'bg-amber-50 text-amber-800 ring-amber-600/20',
                        \App\Enums\PetListingStatus::Rejected => 'bg-red-50 text-red-800 ring-red-600/20',
                        \App\Enums\PetListingStatus::Completed => 'bg-blue-50 text-blue-800 ring-blue-600/20',
                        default => 'bg-gray-100 text-gray-700 ring-gray-500/20',
                    };
                @endphp
                <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold uppercase ring-1 ring-inset {{ $statusClass }}">
                    {{ str_replace('_', ' ', $listing->status->value) }}
                </span>
            </div>
            <p class="text-xs text-[#69736B] mt-1">Submitted by {{ $listing->seller?->name }} on {{ $listing->created_at?->format('M d, Y \a\t h:i A') }}</p>
        </div>
        <div>
            <a
                href="{{ route('admin.marketplace.listings.index') }}"
                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
            >
                &larr; Back to Listings
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Details & Photos (2 cols) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Photos Gallery --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm">
                <h2 class="text-xs font-bold uppercase tracking-wider text-[#173F32] mb-3">Photos ({{ $listing->images->count() }})</h2>
                @if($listing->images->isEmpty())
                    <div class="rounded-xl bg-gray-50 p-8 text-center text-xs text-[#69736B]">
                        No photos uploaded for this listing.
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($listing->images as $img)
                            <div class="overflow-hidden rounded-xl border border-gray-200 aspect-square">
                                <img src="{{ $img->url() }}" alt="{{ $listing->title }}" class="h-full w-full object-cover hover:scale-105 transition-transform duration-200" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Description & Attributes --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-wider text-[#173F32] border-b border-gray-100 pb-2">Listing Description &amp; Details</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-[#69736B] block">Category:</span>
                        <strong class="text-[#173F32]">{{ $listing->petCategory?->name ?? 'General' }}</strong>
                    </div>
                    <div>
                        <span class="text-[#69736B] block">Breed:</span>
                        <strong class="text-[#173F32]">{{ $listing->breed?->name ?? 'Mixed/Other' }}</strong>
                    </div>
                    <div>
                        <span class="text-[#69736B] block">Listing Type:</span>
                        <strong class="text-[#173F32] uppercase">{{ $listing->listing_type->value }}</strong>
                    </div>
                    <div>
                        <span class="text-[#69736B] block">Price:</span>
                        <strong class="text-[#245C45]">
                            @if($listing->price_minor)
                                {{ $listing->currency }} {{ number_format($listing->price_minor / 100, 2) }}
                            @else
                                Free / Adoption
                            @endif
                        </strong>
                    </div>
                    <div>
                        <span class="text-[#69736B] block">Location:</span>
                        <strong class="text-[#202820]">{{ $listing->location }}</strong>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <span class="text-[#69736B] text-xs font-bold uppercase block mb-1">Description</span>
                    <p class="text-xs text-[#202820] leading-relaxed whitespace-pre-line">{{ $listing->description }}</p>
                </div>
            </div>

            {{-- Moderation History --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-6 shadow-sm">
                <h2 class="text-xs font-bold uppercase tracking-wider text-[#173F32] mb-3">Moderation Audit History</h2>
                @if($listing->moderationActions->isEmpty())
                    <p class="text-xs text-[#69736B]">No moderation actions recorded yet.</p>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($listing->moderationActions as $act)
                            <div class="py-3 text-xs space-y-1">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold uppercase text-[10px] rounded px-1.5 py-0.5 {{ $act->action === \App\Enums\ListingModerationActionType::Approved ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $act->action->value }}
                                        </span>
                                        <span class="text-[#202820]">by <strong>{{ $act->admin?->name ?? 'System' }}</strong></span>
                                    </div>
                                    <span class="text-[#69736B] text-[11px]">{{ $act->created_at?->format('M d, Y H:i') }}</span>
                                </div>
                                @if($act->note)
                                    <div class="text-[11px] text-gray-600 bg-gray-50 p-2 rounded-lg italic">
                                        &ldquo;{{ $act->note }}&rdquo;
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Seller & Action Controls (1 col) --}}
        <div class="space-y-6">
            {{-- Quick Moderation (If PendingReview) --}}
            @if($listing->status === \App\Enums\PetListingStatus::PendingReview)
                <div class="rounded-2xl border border-amber-300 bg-amber-50/50 p-5 shadow-sm space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-amber-900 border-b border-amber-200 pb-2">
                        Pending Moderation Decision
                    </h3>

                    <form method="POST" action="{{ route('admin.marketplace.moderation.approve', $listing) }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors"
                        >
                            &check; Approve &amp; Publish Listing
                        </button>
                    </form>

                    <div class="border-t border-amber-200 pt-3">
                        <form method="POST" action="{{ route('admin.marketplace.moderation.reject', $listing) }}" class="space-y-2">
                            @csrf
                            <label for="reject_note" class="block text-xs font-semibold text-red-800">
                                Reject Listing (Reason required):
                            </label>
                            <textarea
                                name="note"
                                id="reject_note"
                                rows="3"
                                required
                                placeholder="Explain why this listing is being rejected (e.g. prohibited breed, inaccurate details, photo policy violation)..."
                                class="w-full rounded-xl border border-red-200 p-2 text-xs text-[#202820] focus:border-red-500 focus:outline-none"
                            ></textarea>
                            <button
                                type="submit"
                                class="w-full rounded-xl bg-red-600 py-2 text-xs font-bold text-white hover:bg-red-700 transition-colors"
                            >
                                Reject Listing
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Seller Information --}}
            <div class="rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#173F32] border-b border-gray-100 pb-2">
                    Seller Profile
                </h3>
                <div class="text-xs space-y-2 text-[#202820]">
                    <div class="font-bold text-sm text-[#173F32]">{{ $listing->seller?->name ?? 'Unknown' }}</div>
                    <div class="text-[#69736B]">{{ $listing->seller?->email }}</div>
                    <div class="pt-2 border-t border-gray-100 flex justify-between">
                        <span class="text-[#69736B]">Role:</span>
                        <span class="font-semibold uppercase text-[10px] bg-gray-100 px-2 py-0.5 rounded">{{ $listing->seller?->role ?? 'customer' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#69736B]">Member Since:</span>
                        <span class="text-gray-700">{{ $listing->seller?->created_at?->format('M Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
