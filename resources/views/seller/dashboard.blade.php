<x-layouts::app :title="__('Seller Portal')">
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

        {{-- Welcome Header (Prompt 5 Greeting) --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-[#245C45]/10 text-[#245C45]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#245C45]"></span>
                        <span>Seller Portal &bull; Verified Marketplace Seller</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32] mt-2">
                        Hello, {{ auth()->user()->name }}!
                    </h1>
                    <p class="text-sm text-[#69736B] mt-1 font-medium">
                        Here's what's happening with your shop today.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0 flex-wrap">
                    <a
                        href="{{ route('seller.listings.create') }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] transition-colors shadow-xs"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Create New Listing</span>
                    </a>
                    <a
                        href="{{ route('listings.index') }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors"
                    >
                        <span>Browse Marketplace &rarr;</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Metrics Overview (All persisted, linked to filtered views) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            {{-- 1. New Purchase Requests --}}
            <a
                href="{{ route('seller.requests.index', ['status' => 'pending']) }}"
                class="group rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs hover:border-[#245C45] hover:shadow-md transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>New Requests</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 group-hover:scale-110 transition-transform flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $metrics['new_requests'] }}</div>
                    <span class="text-xs text-amber-600 font-medium mt-1 block">Awaiting response &rarr;</span>
                </div>
            </a>

            {{-- 2. Requests Awaiting Seller Action --}}
            <a
                href="{{ route('seller.requests.index', ['status' => 'awaiting']) }}"
                class="group rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs hover:border-[#245C45] hover:shadow-md transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>Awaiting Action</span>
                    <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 group-hover:scale-110 transition-transform flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $metrics['awaiting_action'] }}</div>
                    <span class="text-xs text-sky-600 font-medium mt-1 block">Pending &amp; processing &rarr;</span>
                </div>
            </a>

            {{-- 3. Payment Submissions Requiring Verification --}}
            <a
                href="{{ route('seller.requests.index', ['payment' => 'pending']) }}"
                class="group rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs hover:border-[#245C45] hover:shadow-md transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>Payment Verification</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 group-hover:scale-110 transition-transform flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $metrics['pending_payment'] }}</div>
                    <span class="text-xs text-emerald-600 font-medium mt-1 block">Requires verification &rarr;</span>
                </div>
            </a>

            {{-- 4. Active Listings (Live & Published) --}}
            <a
                href="{{ route('seller.listings.index', ['status' => 'published']) }}"
                class="group rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs hover:border-[#245C45] hover:shadow-md transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>Live &amp; Published</span>
                    <div class="w-8 h-8 rounded-lg bg-[#245C45]/10 text-[#245C45] group-hover:scale-110 transition-transform flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $metrics['active_listings'] }}</div>
                    <span class="text-xs text-[#245C45] font-medium mt-1 block">Total Listings: {{ $metrics['active_listings'] + $metrics['attention_listings'] }} &rarr;</span>
                </div>
            </a>

            {{-- 5. Listings That Need Attention (Pending Review, Drafts, Rejected) --}}
            <a
                href="{{ route('seller.listings.index', ['status' => 'attention']) }}"
                class="group rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs hover:border-[#245C45] hover:shadow-md transition-all flex flex-col justify-between"
            >
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>Pending Review</span>
                    <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 group-hover:scale-110 transition-transform flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $metrics['attention_listings'] }}</div>
                    <span class="text-xs text-rose-600 font-medium mt-1 block">Needs Attention &rarr;</span>
                </div>
            </a>
        </div>

        {{-- Recent Incoming Purchase / Adoption Requests --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-[#173F32]">Incoming Purchase &amp; Adoption Requests</h2>
                    <p class="text-xs text-[#69736B]">Requests submitted by verified customers for your pet listings.</p>
                </div>
                <a href="{{ route('seller.requests.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                    View All Requests ({{ $metrics['awaiting_action'] + $metrics['new_requests'] }}) &rarr;
                </a>
            </div>

            @if($recentRequests->isEmpty())
                <div class="text-center py-8 text-sm text-[#69736B] bg-[#FAF8F2] rounded-2xl border border-[#202820]/5">
                    No requests have been submitted yet. When customers request your pets, they will appear here.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[#202820]/10 text-[#69736B] uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="py-3 px-4">Request #</th>
                                <th class="py-3 px-4">Pet / Subject</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Payment</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#202820]/5">
                            @foreach($recentRequests as $order)
                                <tr class="hover:bg-[#FAF8F2]/60 transition-colors">
                                    <td class="py-3 px-4 font-mono font-medium text-[#173F32]">
                                        <a href="{{ route('seller.requests.show', $order) }}" class="hover:underline">
                                            #{{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-[#202820]">
                                            {{ $order->items->first()?->product_name ?? ($order->petListing?->title ?? 'Pet Request') }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-[#202820] font-medium">{{ $order->customer_name }}</div>
                                        <div class="text-[11px] text-[#69736B]">{{ $order->customer_phone }}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-amber-100 text-amber-800',
                                                'processing' => 'bg-blue-100 text-blue-800',
                                                'shipped' => 'bg-purple-100 text-purple-800',
                                                'delivered' => 'bg-emerald-100 text-emerald-800',
                                                'cancelled' => 'bg-rose-100 text-rose-800',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusColors[$order->status->value] ?? 'bg-zinc-100 text-zinc-700' }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $order->payment_status === \App\Enums\PaymentStatus::Paid ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-100 text-zinc-700' }}">
                                            {{ $order->payment_status->label() }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-[#69736B]">
                                        {{ $order->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <a href="{{ route('seller.requests.show', $order) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#245C45] hover:underline">
                                            <span>Manage</span>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Recent Listings Table --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-[#173F32]">My Pet Listings</h2>
                    <p class="text-xs text-[#69736B]">Manage your active listings, drafts, and moderation responses.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('seller.listings.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                        View All Listings ({{ $metrics['active_listings'] + $metrics['attention_listings'] }}) &rarr;
                    </a>
                </div>
            </div>

            @if($recentListings->isEmpty())
                <div class="text-center py-8 text-sm text-[#69736B] bg-[#FAF8F2] rounded-2xl border border-[#202820]/5">
                    You haven't created any pet listings yet.
                    <div class="mt-3">
                        <a href="{{ route('seller.listings.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32]">
                            + Create Your First Listing
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
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#202820]/5">
                            @foreach($recentListings as $listing)
                                <tr class="hover:bg-[#FAF8F2]/60 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            @if($listing->images->isNotEmpty())
                                                <img src="{{ $listing->images->first()->url() }}" alt="{{ $listing->title }}" class="w-10 h-10 rounded-lg object-cover border border-[#202820]/10">
                                            @else
                                                <div class="w-10 h-10 rounded-lg bg-[#FAF8F2] border border-[#202820]/10 flex items-center justify-center text-xs text-[#69736B]">
                                                    🐾
                                                </div>
                                            @endif
                                            <div>
                                                <a href="{{ route('seller.listings.edit', $listing) }}" class="font-semibold text-[#173F32] hover:underline block">
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
                                    <td class="py-3 px-4 font-medium text-[#202820]">
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
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('seller.listings.edit', $listing) }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                                                Edit
                                            </a>
                                            @if($listing->status === \App\Enums\PetListingStatus::Published)
                                                <a href="{{ route('listings.show', $listing) }}" target="_blank" class="text-xs text-[#69736B] hover:text-[#202820]">
                                                    View &rarr;
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
