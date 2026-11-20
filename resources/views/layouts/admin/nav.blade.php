<nav class="flex flex-1 flex-col gap-y-7 text-xs font-semibold">
    @php
        $navUser = auth()->user();
        $navMemberships = $navUser?->isStaff()
            ? app(\App\Services\BusinessAccessService::class)->activeMemberships($navUser)
            : collect();
        $navIsShopOwner = $navMemberships->contains(fn ($membership) => $membership->role === 'owner');
        $navIsEmployeeSeller = $navMemberships->contains(fn ($membership) => $membership->role === 'seller');
        $navIsBranchManager = $navMemberships->contains(fn ($membership) => $membership->role === 'manager');
        $navCanBranchOperations = $navMemberships->contains(fn ($membership) => in_array($membership->role, ['owner', 'manager'], true));
    @endphp
    <ul role="list" class="flex flex-1 flex-col gap-y-6">
        {{-- 1. OVERVIEW --}}
        <li>
            <div class="text-[11px] font-bold uppercase tracking-wider text-white/50 mb-2">1. Overview</div>
            <ul role="list" class="space-y-1">
                <li>
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </li>

        {{-- 2. MY WEBSITE (WEBSITE MANAGER & BRANDING) --}}
        @if(auth()->user()?->isAdmin())
            <li>
                <div class="text-[11px] font-bold uppercase tracking-wider text-white/50 mb-2">2. My Website</div>
                <ul role="list" class="space-y-1">
                    <li>
                        <a
                            href="{{ route('admin.homepage.index') }}"
                            class="group flex items-center justify-between rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.homepage.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <div class="flex items-center gap-x-3">
                                <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                </svg>
                                <span>Website Manager</span>
                            </div>
                            @if(\App\Facades\Settings::get('homepage.draft_exists'))
                                <span class="rounded-full bg-amber-400 px-1.5 py-0.5 text-[9px] font-extrabold text-black uppercase tracking-wider">Draft</span>
                            @endif
                        </a>
                    </li>
                    <li>
                        <a
                            href="{{ route('admin.brand.index') }}"
                            class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.brand.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            <span>Brand &amp; Logos</span>
                        </a>
                    </li>
                </ul>
            </li>
        @endif

        {{-- 3. MY BUSINESS (COMMERCE, LISTINGS, COMMUNITY) --}}
        <li>
            <div class="text-[11px] font-bold uppercase tracking-wider text-white/50 mb-2">3. My Business</div>
            <ul role="list" class="space-y-1">
                {{-- Platform marketplace administration --}}
                @if(auth()->user()?->isAdmin())
                <li>
                    <a
                        href="{{ route('admin.marketplace.listings.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.marketplace.listings.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span>Marketplace Listings</span>
                    </a>
                </li>
                @if(auth()->user()?->isAdmin())
                    <li>
                        <a
                            href="{{ route('admin.marketplace.moderation.index') }}"
                            class="group flex items-center justify-between rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.marketplace.moderation.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <div class="flex items-center gap-x-3">
                                <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <span>Moderation Queue</span>
                            </div>
                            @php
                                $pendingCount = \App\Models\PetListing::where('status', \App\Enums\PetListingStatus::PendingReview)->count();
                            @endphp
                            @if($pendingCount > 0)
                                <span class="rounded-full bg-[#F28C75] px-2 py-0.5 text-[10px] font-bold text-white">{{ $pendingCount }}</span>
                            @endif
                        </a>
                    </li>
                @endif
                @endif

                {{-- Commerce & Customer Orders --}}
                @if($navIsEmployeeSeller)
                <li>
                    <a
                        href="{{ route('admin.sales-desk.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.sales-desk.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l2.4 12.2a2 2 0 002 1.6h8.8a2 2 0 002-1.6L22 8H6m4 13a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" />
                        </svg>
                        <span>Employee Sales Desk</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.sales-reports.my') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.sales-reports.my*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18M7 14l4-4 4 4 6-7" /></svg>
                        <span>My Sales</span>
                    </a>
                </li>
                @endif
                @if($navIsBranchManager)
                <li>
                    <a href="{{ route('admin.sales-reports.branch') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.sales-reports.branch') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" /></svg>
                        <span>Branch Sales</span>
                    </a>
                </li>
                @endif
                @if($navIsShopOwner)
                <li>
                    <a href="{{ route('admin.sales-reports.business') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.sales-reports.business') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M4 18V5l8-2 8 2v13M8 9h.01M16 9h.01M8 13h.01M16 13h.01M10 21v-4h4v4" /></svg>
                        <span>Business Sales</span>
                    </a>
                </li>
                @endif
                @if($navCanBranchOperations)
                <li>
                    <a
                        href="{{ route('admin.orders.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.orders.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>Customer Orders</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.fulfilment.index') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.fulfilment.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M5 7l1.5 13h11L19 7M9 7V5a3 3 0 016 0v2m-5 5v4m4-4v4" /></svg>
                        <span>Order Fulfilment</span>
                    </a>
                </li>
                @endif
                @can('viewAny', \App\Models\BusinessMembership::class)
                    <li>
                        <a
                            href="{{ route('admin.business.index') }}"
                            class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.business.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" />
                            </svg>
                            <span>Companies &amp; Branch Access</span>
                        </a>
                    </li>
                @endcan
                @if($navCanBranchOperations)
                <li>
                    <a
                        href="{{ route('admin.customers.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.customers.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Customer Directory</span>
                    </a>
                </li>
                @endif

                {{-- Products & Shop Catalog --}}
                @can('viewAny', \App\Models\Product::class)
                <li>
                    <a
                        href="{{ route('admin.products.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.products.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>Products Catalog</span>
                    </a>
                </li>
                @endcan
                @if($navIsShopOwner)
                <li>
                    <a href="{{ route('admin.pricing.index') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.pricing.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V5m0 3v8m0 0v3m9-7a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Pricing &amp; Promotions</span>
                    </a>
                </li>
                @endif
                @can('viewAny', \App\Models\ProductCategory::class)
                <li>
                    <a
                        href="{{ route('admin.categories.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.categories.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        <span>Product Categories</span>
                    </a>
                </li>
                @endcan
                @can('viewAny', \App\Models\Product::class)
                <li>
                    <a
                        href="{{ route('admin.inventory.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.inventory.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Inventory &amp; Stock</span>
                    </a>
                </li>
                @endcan

                {{-- Species, Breeds & Community Hub --}}
                @if(auth()->user()?->isAdmin())
                    <li>
                        <a
                            href="{{ route('admin.marketplace.categories.index') }}"
                            class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.marketplace.categories.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            <span>Species Categories</span>
                        </a>
                    </li>
                @endif
                <li>
                    <a
                        href="{{ route('admin.breeds.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.breeds.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>Breed Profiles</span>
                    </a>
                </li>
                <li>
                    <a
                        href="{{ route('admin.community.index') }}"
                        class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.community.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                    >
                        <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span>Adoption &amp; Community Hub</span>
                    </a>
                </li>
            </ul>
        </li>

        {{-- 4. COMPANY SETTINGS --}}
        @if($navIsShopOwner)
            <li>
                <div class="text-[11px] font-bold uppercase tracking-wider text-white/50 mb-2">4. Company Settings</div>
                <ul role="list" class="space-y-1">
                    <li>
                        <a
                            href="{{ route('admin.settings.shop') }}"
                            class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.settings.shop') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Shop &amp; Delivery Fees</span>
                        </a>
                    </li>
                </ul>
            </li>
        @endif

        {{-- Platform Settings and Administration --}}
        @if(auth()->user()?->isAdmin())
            <li>
                <div class="text-[11px] font-bold uppercase tracking-wider text-white/50 mb-2">5. Platform Settings</div>
                <ul role="list" class="space-y-1">
                    <li>
                        <a
                            href="{{ route('admin.settings.features') }}"
                            class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.settings.features') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>Branch &amp; Staff Feature Flags</span>
                        </a>
                    </li>
                </ul>
            </li>

            <li>
                <div class="text-[11px] font-bold uppercase tracking-wider text-white/50 mb-2">6. Platform Security</div>
                <ul role="list" class="space-y-1">
                    <li>
                        <a
                            href="{{ route('admin.system.index') }}"
                            class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.system.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                            <span>System &amp; Security Health</span>
                        </a>
                    </li>
                    <li>
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"
                        >
                            <svg class="h-4 w-4 shrink-0 text-[#F28C75]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span>Users &amp; Staff Roles</span>
                        </a>
                    </li>
                </ul>
            </li>
        @endif

        @if(auth()->user()?->isAdmin() || $navIsShopOwner)
            <li>
                <div class="text-[11px] font-bold uppercase tracking-wider text-white/50 mb-2">Careers &amp; Access</div>
                <ul role="list" class="space-y-1">
                    @if(auth()->user()?->isAdmin())
                        <li><a href="{{ route('admin.careers.vacancies.index') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.careers.vacancies.*', 'admin.careers.applications.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"><span>Careers recruitment</span></a></li>
                    @endif
                    @if($navIsShopOwner)
                        <li><a href="{{ route('admin.careers.approvals.index') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.careers.approvals.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}"><span>Employee approvals</span></a></li>
                    @endif
                    <li>
                        <a href="{{ route('admin.invitations.index') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.invitations.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                            <span>Exceptional invitations</span>
                        </a>
                    </li>
                </ul>
            </li>
        @endif

        @if(auth()->user()?->isAdmin() || $navCanBranchOperations)
            <li>
                <ul role="list" class="space-y-1">
                    <li>
                        <a href="{{ route('admin.audit-logs.index') }}" class="group flex gap-x-3 rounded-xl p-2.5 text-xs font-medium transition-colors {{ request()->routeIs('admin.audit-logs.*') ? 'bg-white/15 text-white font-semibold' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                            <span>Activity &amp; Audit Trail</span>
                        </a>
                    </li>
                </ul>
            </li>
        @endif
    </ul>
</nav>
