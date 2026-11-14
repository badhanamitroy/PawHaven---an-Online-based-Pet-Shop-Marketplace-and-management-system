<x-layouts::app :title="__('Customer Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-[#245C45]/20 bg-[#245C45]/10 p-4 text-sm font-medium text-[#173F32]">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        {{-- Welcome Header (Prompt 5 Greeting) --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-[#245C45]/10 text-[#245C45]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#245C45]"></span>
                        PawHaven Customer
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#173F32] mt-2">
                        Hello, {{ auth()->user()->name }}!
                    </h1>
                    <p class="text-sm text-[#69736B] mt-1 font-medium">
                        Welcome to PawHaven. Let's find your next best friend.
                    </p>
                </div>

                <div class="flex items-center gap-3 shrink-0 flex-wrap">
                    <a
                        href="{{ route('listings.index') }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] transition-colors shadow-xs"
                    >
                        <span>Explore Pet Marketplace &rarr;</span>
                    </a>
                    <a
                        href="{{ route('profile.edit') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors"
                        title="Profile Settings"
                    >
                        <svg class="w-4 h-4 text-[#69736B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Profile</span>
                    </a>
                    <a
                        href="{{ route('security.edit') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors"
                        title="Security & 2FA"
                    >
                        <svg class="w-4 h-4 text-[#69736B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Security</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Metrics Overview --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- My Purchase Requests / Shop Orders --}}
            <div class="rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>Shop Orders</span>
                    <div class="w-8 h-8 rounded-lg bg-[#245C45]/10 text-[#245C45] flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $orders->total() }}</div>
                    <span class="text-xs text-[#69736B] mt-1 block">Active applications &amp; purchases</span>
                </div>
            </div>

            {{-- Saved / Favourite Pets --}}
            <div class="rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>Saved Pets</span>
                    <div class="w-8 h-8 rounded-lg bg-[#F28C75]/15 text-[#F28C75] flex items-center justify-center">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $savedPets->count() }}</div>
                    <span class="text-xs text-[#F28C75] font-medium mt-1 block">Bookmarked companions</span>
                </div>
            </div>

            {{-- My Pet Listings (Rehoming Posts) --}}
            <div class="rounded-2xl border border-[#202820]/10 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-[#69736B]">
                    <span>My Pet Listings</span>
                    <div class="w-8 h-8 rounded-lg bg-[#245C45]/10 text-[#245C45] flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-3xl font-bold tracking-tight text-[#173F32]">{{ $myListings->count() }}</div>
                    <span class="text-xs text-[#69736B] font-medium mt-1 block">Adoption &amp; rehoming submissions</span>
                </div>
            </div>
        </div>

        {{-- My Purchase Requests & Orders with Timeline & Payment Instructions --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-[#173F32]">My Purchase Requests &amp; Orders</h2>
                    <p class="text-xs text-[#69736B]">Track request progression, timeline, and direct in-person payment settlement.</p>
                </div>
                <a href="{{ route('listings.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                    Browse More Pets &rarr;
                </a>
            </div>

            {{-- Ethical Payment Instructions Card --}}
            <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 flex items-start gap-3 text-xs">
                <div class="w-8 h-8 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <span class="font-bold text-[#173F32] block">Safe Payment Instructions (Cash on Handover / COD):</span>
                    <p class="text-[#69736B] mt-0.5 leading-relaxed">
                        To protect animal welfare and prevent fraud, commercial pet settlements occur <strong>strictly in person upon safe handover and physical health inspection</strong>. Never transfer advance funds online. For shelter adoptions, zero fees apply.
                    </p>
                </div>
            </div>

            @if($orders->isEmpty())
                <div class="text-center py-10 text-sm text-[#69736B] bg-[#FAF8F2] rounded-2xl border border-[#202820]/5">
                    You haven't submitted any pet purchase or adoption requests yet.
                    <div class="mt-3">
                        <a href="{{ route('listings.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32]">
                            Explore Available Pets
                        </a>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($orders as $order)
                        <div class="p-5 rounded-2xl border border-[#202820]/10 bg-white hover:border-[#245C45]/40 transition-colors">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-[#202820]/5">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-bold text-[#173F32]">#{{ $order->order_number }}</span>
                                        <span class="text-xs text-[#69736B]">&bull; {{ $order->created_at->format('M d, Y') }}</span>
                                    </div>
                                    <div class="font-semibold text-sm text-[#202820] mt-0.5">
                                        {{ $order->items->first()?->product_name ?? ($order->petListing?->title ?? 'Pet Request') }}
                                    </div>
                                    @if($order->seller)
                                        <div class="text-xs text-[#69736B]">Seller: {{ $order->seller->name }}</div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-[#202820]">
                                            @if($order->total_minor > 0)
                                                ${{ number_format($order->total_minor / 100, 2) }}
                                            @else
                                                <span class="text-emerald-700">Free Adoption</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-[#69736B]">
                                            {{ $order->payment_method === 'cod' ? 'Cash on Handover' : ucfirst($order->payment_method) }}
                                        </span>
                                    </div>

                                    {{-- Customer Cancellation if Pending --}}
                                    @if($order->status === \App\Enums\OrderStatus::Pending)
                                        <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 transition-colors">
                                                Cancel Request
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            {{-- Status Timeline & Progress Bar --}}
                            <div class="pt-4">
                                @php
                                    $steps = [
                                        \App\Enums\OrderStatus::Pending->value => 'Pending Review',
                                        \App\Enums\OrderStatus::Processing->value => 'Accepted & Processing',
                                        \App\Enums\OrderStatus::Shipped->value => 'Out for Handover',
                                        \App\Enums\OrderStatus::Delivered->value => 'Completed',
                                    ];

                                    $stepOrder = ['pending', 'processing', 'shipped', 'delivered'];
                                    $currentIndex = array_search($order->status->value, $stepOrder, true);
                                    $isCancelled = $order->status === \App\Enums\OrderStatus::Cancelled;
                                @endphp

                                @if($isCancelled)
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                        <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                                        <span>Request Cancelled</span>
                                    </div>
                                @else
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                                        @foreach($stepOrder as $idx => $stepKey)
                                            @php
                                                $isPassed = $currentIndex !== false && $idx <= $currentIndex;
                                                $isCurrent = $currentIndex !== false && $idx === $currentIndex;
                                            @endphp
                                            <div class="p-2.5 rounded-xl border {{ $isCurrent ? 'bg-[#245C45] text-white border-[#245C45] font-bold shadow-xs' : ($isPassed ? 'bg-[#245C45]/10 text-[#173F32] border-[#245C45]/20 font-semibold' : 'bg-[#FAF8F2] text-[#69736B] border-[#202820]/10') }}">
                                                <div class="text-[11px] uppercase tracking-wider">Step {{ $idx + 1 }}</div>
                                                <div class="mt-0.5 truncate">{{ $steps[$stepKey] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-4">
                        {{ $orders->links() }}
                    </div>
                </div>
            @endif
        </div>

        {{-- Saved / Favourite Pets Section --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#173F32]">Saved Pets (Favorites)</h2>
                    <p class="text-xs text-[#69736B]">Your bookmarked companions for quick access.</p>
                </div>
                <a href="{{ route('listings.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                    Browse More Pets &rarr;
                </a>
            </div>

            @if($savedPets->isEmpty())
                <div class="text-center py-8 text-sm text-[#69736B] bg-[#FAF8F2] rounded-2xl border border-[#202820]/5">
                    You haven't saved any pets to your favorites yet.
                    <div class="mt-3">
                        <a href="{{ route('listings.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32]">
                            Explore Pets &amp; Save Favorites
                        </a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($savedPets as $pet)
                        <div class="rounded-2xl border border-[#202820]/10 bg-[#FAF8F2] overflow-hidden flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div>
                                <div class="aspect-video w-full bg-black/10 overflow-hidden relative">
                                    @if($pet->images->isNotEmpty())
                                        <img src="{{ $pet->images->first()->url() }}" alt="{{ $pet->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-3xl">🐾</div>
                                    @endif
                                    <div class="absolute top-2 right-2">
                                        <form method="POST" action="{{ route('listings.favorite', $pet) }}">
                                            @csrf
                                            <button type="submit" class="p-1.5 rounded-full bg-white/90 text-[#F28C75] hover:bg-white shadow-xs" title="Remove from favorites">
                                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <span class="text-[11px] font-semibold text-[#69736B] uppercase tracking-wider block">
                                        {{ $pet->petCategory?->name ?? 'Pet' }} &bull; {{ $pet->breed?->name ?? 'Mixed' }}
                                    </span>
                                    <a href="{{ route('listings.show', $pet) }}" class="font-bold text-[#173F32] hover:underline block text-sm mt-1">
                                        {{ $pet->title }}
                                    </a>
                                    <div class="mt-2 text-xs font-semibold text-[#202820]">
                                        @if($pet->listing_type === \App\Enums\PetListingType::Sale && $pet->price_minor)
                                            ${{ number_format($pet->price_minor / 100, 2) }}
                                        @else
                                            <span class="text-[#245C45] font-bold">Free Adoption</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 pt-0">
                                <a href="{{ route('listings.show', $pet) }}" class="w-full text-center py-2 px-3 rounded-xl bg-[#245C45] text-white hover:bg-[#173F32] text-xs font-semibold block transition-colors shadow-xs">
                                    View Pet &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Customer's Own Adoption / Rehoming Posts --}}
        <div class="rounded-3xl border border-[#202820]/10 bg-white p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#173F32]">My Rehoming &amp; Adoption Posts</h2>
                    <p class="text-xs text-[#69736B]">Community posts submitted under your account.</p>
                </div>
            </div>

            @if($myListings->isEmpty())
                <div class="text-center py-8 text-sm text-[#69736B] bg-[#FAF8F2] rounded-2xl border border-[#202820]/5">
                    You have not submitted any pet adoption or rehoming posts.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[#202820]/10 text-[#69736B] uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="py-3 px-4">Title</th>
                                <th class="py-3 px-4">Category</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#202820]/5">
                            @foreach($myListings as $listing)
                                <tr class="hover:bg-[#FAF8F2]/60 transition-colors">
                                    <td class="py-3 px-4 font-bold text-[#173F32]">
                                        {{ $listing->title }}
                                    </td>
                                    <td class="py-3 px-4 text-[#202820]">
                                        {{ $listing->petCategory?->name ?? 'Uncategorized' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $listing->status === \App\Enums\PetListingStatus::Published ? 'bg-[#245C45]/10 text-[#173F32]' : 'bg-[#FAF8F2] border border-[#202820]/10 text-[#69736B]' }}">
                                            {{ ucfirst(str_replace('_', ' ', $listing->status->value)) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-[#69736B]">
                                        {{ $listing->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        @if($listing->status === \App\Enums\PetListingStatus::Published)
                                            <a href="{{ route('listings.show', $listing) }}" target="_blank" class="text-xs font-semibold text-[#245C45] hover:underline">
                                                View &rarr;
                                            </a>
                                        @else
                                            <span class="text-[11px] text-[#69736B]">In review</span>
                                        @endif
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
