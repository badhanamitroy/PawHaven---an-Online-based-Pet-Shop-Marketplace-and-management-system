@extends('layouts.admin')

@section('title', 'Marketplace Moderation Queue')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#173F32]">Pet Marketplace Moderation Queue</h1>
            <p class="text-xs text-[#69736B] mt-1">Review lawful domestic pet listings submitted by community members prior to public publication.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">
                {{ $pendingListings->total() }} Pending Review
            </span>
        </div>
    </div>

    @if($pendingListings->isEmpty())
        <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white p-12 text-center shadow-sm">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="mt-3 text-sm font-bold text-[#173F32]">Queue is clear</h3>
            <p class="mt-1 text-xs text-[#69736B]">All submitted pet listings have been reviewed and moderated.</p>
            <div class="mt-4">
                <a href="{{ route('admin.marketplace.listings.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                    View all listings &rarr;
                </a>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($pendingListings as $listing)
                <div class="overflow-hidden rounded-2xl border border-[#245C45]/15 bg-white p-5 shadow-sm">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                        {{-- Photo & Title Details --}}
                        <div class="flex items-start gap-4 flex-1">
                            @if($listing->images->isNotEmpty())
                                <img src="{{ $listing->images->first()->url() }}" alt="{{ $listing->title }}" class="h-20 w-20 rounded-xl object-cover border border-gray-200 shrink-0" />
                            @else
                                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-[#245C45]/10 text-[#245C45]">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            @endif

                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold text-[#173F32]">
                                        <a href="{{ route('admin.marketplace.listings.show', $listing) }}" class="hover:underline">
                                            {{ $listing->title }}
                                        </a>
                                    </h3>
                                    <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-800 ring-1 ring-amber-600/20">
                                        Pending
                                    </span>
                                </div>
                                <div class="text-xs text-[#69736B]">
                                    Category: <strong class="text-[#202820]">{{ $listing->petCategory?->name ?? 'General' }}</strong> &bull;
                                    Breed: <strong class="text-[#202820]">{{ $listing->breed?->name ?? 'Mixed' }}</strong> &bull;
                                    Location: <strong class="text-[#202820]">{{ $listing->location }}</strong>
                                </div>
                                <div class="text-xs text-[#69736B]">
                                    Seller: <strong class="text-[#173F32]">{{ $listing->seller?->name }}</strong> ({{ $listing->seller?->email }}) &bull;
                                    Price: <strong class="text-[#245C45]">
                                        @if($listing->price_minor)
                                            ${{ number_format($listing->price_minor / 100, 2) }}
                                        @else
                                            Free / Adoption
                                        @endif
                                    </strong>
                                </div>
                                <p class="text-xs text-[#202820] line-clamp-2 pt-1">{{ $listing->description }}</p>
                            </div>
                        </div>

                        {{-- Moderation Action Forms --}}
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0 w-full md:w-auto">
                            <a
                                href="{{ route('admin.marketplace.listings.show', $listing) }}"
                                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                Details
                            </a>

                            <form method="POST" action="{{ route('admin.marketplace.moderation.approve', $listing) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors"
                                >
                                    &check; Approve
                                </button>
                            </form>

                            <details class="relative">
                                <summary class="rounded-xl bg-red-50 border border-red-200 px-3 py-2 text-center text-xs font-semibold text-red-700 cursor-pointer hover:bg-red-100 transition-colors list-none">
                                    Reject...
                                </summary>
                                <div class="absolute right-0 top-full mt-2 z-10 w-80 rounded-2xl border border-red-200 bg-white p-4 shadow-xl">
                                    <form method="POST" action="{{ route('admin.marketplace.moderation.reject', $listing) }}" class="space-y-3">
                                        @csrf
                                        <label for="reject_note_{{ $listing->id }}" class="block text-xs font-bold text-red-800">
                                            Rejection Reason:
                                        </label>
                                        <textarea
                                            name="note"
                                            id="reject_note_{{ $listing->id }}"
                                            rows="3"
                                            required
                                            placeholder="Specify rejection reason..."
                                            class="w-full rounded-xl border border-red-200 p-2 text-xs text-[#202820] focus:border-red-500 focus:outline-none"
                                        ></textarea>
                                        <div class="flex justify-end gap-2">
                                            <button
                                                type="submit"
                                                class="rounded-xl bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 transition-colors"
                                            >
                                                Confirm Rejection
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            @endforeach

            @if($pendingListings->hasPages())
                <div class="p-4">
                    {{ $pendingListings->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
