<x-layouts::app :title="__('My Pet Listings')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/50 dark:text-rose-300">
                {{ session('error') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 text-xs font-semibold text-[#69736B] uppercase tracking-wider">
                        <a href="{{ route('seller.dashboard') }}" class="hover:underline">Seller Dashboard</a>
                        <span>&bull;</span>
                        <span>Manage Listings</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32] mt-1">
                        My Pet Listings
                    </h1>
                    <p class="text-xs sm:text-sm text-[#69736B] mt-1">
                        Create, update, pause, or resubmit your commercial and adoption listings.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        href="{{ route('seller.listings.create') }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] transition-colors shadow-xs"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Create New Listing</span>
                    </a>
                </div>
            </div>

            {{-- Filter Pills --}}
            <div class="flex items-center gap-2 mt-6 overflow-x-auto pb-1 text-xs">
                @php
                    $filters = [
                        'all' => 'All (' . $counts['all'] . ')',
                        'published' => 'Published (' . $counts['published'] . ')',
                        'pending' => 'Pending Review (' . $counts['pending'] . ')',
                        'draft' => 'Drafts (' . $counts['draft'] . ')',
                        'rejected' => 'Rejected (' . $counts['rejected'] . ')',
                        'paused' => 'Paused (' . $counts['paused'] . ')',
                        'attention' => 'Needs Attention (' . $counts['attention'] . ')',
                    ];
                @endphp
                @foreach($filters as $key => $label)
                    <a
                        href="{{ route('seller.listings.index', ['status' => $key]) }}"
                        class="px-3 py-1.5 rounded-full font-medium transition-colors whitespace-nowrap {{ $currentFilter === $key ? 'bg-[#245C45] text-white shadow-xs' : 'bg-[#FAF8F2] text-[#69736B] hover:bg-[#202820]/10 hover:text-[#202820]' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Listings Table --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-xs">
            @if($listings->isEmpty())
                <div class="text-center py-12 text-[#69736B]">
                    <p class="text-base font-semibold text-[#202820]">No listings found for this filter.</p>
                    <p class="text-xs mt-1">Try switching to a different filter or create a new pet listing.</p>
                    <div class="mt-4">
                        <a href="{{ route('seller.listings.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32]">
                            + Create New Listing
                        </a>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[#202820]/10 text-[#69736B] uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="py-3 px-4">Listing</th>
                                <th class="py-3 px-4">Category &amp; Breed</th>
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4">Price</th>
                                <th class="py-3 px-4">Status &amp; Notes</th>
                                <th class="py-3 px-4">Last Updated</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#202820]/5">
                            @foreach($listings as $listing)
                                <tr class="hover:bg-[#FAF8F2]/60 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            @if($listing->images->isNotEmpty())
                                                <img src="{{ $listing->images->first()->url() }}" alt="{{ $listing->title }}" class="w-12 h-12 rounded-xl object-cover border border-[#202820]/10 shrink-0">
                                            @else
                                                <div class="w-12 h-12 rounded-xl bg-[#FAF8F2] border border-[#202820]/10 flex items-center justify-center text-sm text-[#69736B] shrink-0">
                                                    🐾
                                                </div>
                                            @endif
                                            <div>
                                                <a href="{{ route('seller.listings.edit', $listing) }}" class="font-bold text-[#173F32] hover:underline text-sm block">
                                                    {{ $listing->title }}
                                                </a>
                                                <span class="text-[11px] text-[#69736B]">{{ $listing->location }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-[#202820] font-medium">{{ $listing->petCategory?->name ?? 'Uncategorized' }}</div>
                                        <div class="text-[11px] text-[#69736B]">{{ $listing->breed?->name ?? 'Mixed / Unknown' }}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $listing->listing_type === \App\Enums\PetListingType::Sale ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ ucfirst($listing->listing_type->value) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-[#202820]">
                                        @if($listing->listing_type === \App\Enums\PetListingType::Sale && $listing->price_minor)
                                            ${{ number_format($listing->price_minor / 100, 2) }}
                                        @else
                                            <span class="text-emerald-700 font-semibold">Free Adoption</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @php
                                            $listingBadges = [
                                                'published' => 'bg-emerald-100 text-emerald-800',
                                                'pending_review' => 'bg-amber-100 text-amber-800',
                                                'draft' => 'bg-zinc-100 text-zinc-700',
                                                'rejected' => 'bg-rose-100 text-rose-800',
                                                'paused' => 'bg-orange-100 text-orange-800',
                                                'completed' => 'bg-purple-100 text-purple-800',
                                                'removed' => 'bg-zinc-200 text-zinc-700',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $listingBadges[$listing->status->value] ?? 'bg-zinc-100 text-zinc-700' }}">
                                            {{ ucfirst(str_replace('_', ' ', $listing->status->value)) }}
                                        </span>

                                        {{-- If rejected, show latest moderation note --}}
                                        @if($listing->status === \App\Enums\PetListingStatus::Rejected && $listing->moderationActions->isNotEmpty())
                                            <div class="text-[11px] text-rose-700 font-medium mt-1 max-w-xs bg-rose-50 p-1.5 rounded border border-rose-200">
                                                <strong>Moderator Note:</strong> {{ $listing->moderationActions->first()->note ?? 'Please review guidelines and update listing.' }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-[#69736B]">
                                        {{ $listing->updated_at->format('M d, Y') }}
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="inline-flex items-center gap-1.5 flex-wrap justify-end">
                                            {{-- Edit --}}
                                            <a href="{{ route('seller.listings.edit', $listing) }}" class="px-2.5 py-1 rounded bg-[#FAF8F2] border border-[#202820]/15 text-[#202820] font-semibold hover:bg-white text-xs">
                                                Edit
                                            </a>

                                            {{-- Pause if Published --}}
                                            @if($listing->status === \App\Enums\PetListingStatus::Published)
                                                <form method="POST" action="{{ route('seller.listings.status', $listing) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="action" value="pause">
                                                    <button type="submit" class="px-2.5 py-1 rounded bg-amber-50 border border-amber-200 text-amber-800 font-semibold hover:bg-amber-100 text-xs" title="Pause listing from public view">
                                                        Pause
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Resubmit for Review if Draft or Rejected --}}
                                            @if(in_array($listing->status, [\App\Enums\PetListingStatus::Draft, \App\Enums\PetListingStatus::Rejected], true))
                                                <form method="POST" action="{{ route('seller.listings.status', $listing) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="action" value="submit_review">
                                                    <button type="submit" class="px-2.5 py-1 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold hover:bg-emerald-100 text-xs" title="Submit listing for admin moderation">
                                                        Submit
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Mark Completed if Published or Paused --}}
                                            @if(in_array($listing->status, [\App\Enums\PetListingStatus::Published, \App\Enums\PetListingStatus::Paused], true))
                                                <form method="POST" action="{{ route('seller.listings.status', $listing) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="action" value="mark_completed">
                                                    <button type="submit" class="px-2.5 py-1 rounded bg-purple-50 border border-purple-200 text-purple-800 font-semibold hover:bg-purple-100 text-xs" title="Mark as successfully adopted/sold">
                                                        Complete
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Delete --}}
                                            <form method="POST" action="{{ route('seller.listings.destroy', $listing) }}" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete this listing?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1 rounded text-rose-600 hover:bg-rose-50 hover:text-rose-800" title="Delete listing">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $listings->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
