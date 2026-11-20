<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Explore Pets &amp; Adoption — {{ \App\Facades\Settings::get('brand.name', 'PawHaven') }}</title>
    <meta name="description" content="Browse verified companion pets for sale, shelter adoption posts, and gentle rehoming listings on PawHaven.">

    @include('partials.favicon')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="bg-[#FAF8F2] text-[#202820] font-sans antialiased selection:bg-[#245C45] selection:text-white" x-data="{ mobileOpen: false }">

    {{-- Top Announcement Bar --}}
    @if(\App\Facades\Settings::get('homepage.announcement_enabled', true))
        <div class="bg-[#173F32] text-[#FAF8F2] py-2 px-4 text-xs font-medium text-center relative z-50">
            <span>{{ \App\Facades\Settings::get('homepage.announcement_text', 'Connecting ethical breeders, local shelters, and loving families across the community.') }}</span>
        </div>
    @endif

    {{-- Navigation Bar --}}
    <header class="sticky top-0 z-40 bg-[#FAF8F2]/95 backdrop-blur-md border-b border-[#202820]/10 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                {{-- Brand Logo --}}
                <a href="{{ route('public.home') }}" class="flex items-center gap-3 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded-lg">
                    <img
                        src="{{ str_starts_with((string) \App\Facades\Settings::get('brand.logo_light_path', ''), 'http') ? \App\Facades\Settings::get('brand.logo_light_path') : asset(ltrim((string) \App\Facades\Settings::get('brand.logo_light_path', 'images/pawhaven-logo-green.png'), '/')) }}"
                        alt="{{ \App\Facades\Settings::get('brand.name', 'PawHaven') }} Logo"
                        height="48"
                        class="h-12 w-auto max-h-12 max-w-[200px] shrink-0 object-contain transition-transform group-hover:scale-105"
                        style="height: 48px; max-height: 48px; width: auto; max-width: 200px; object-fit: contain;"
                    >
                    <div>
                        <span class="text-2xl font-bold tracking-tight text-[#173F32] block leading-none">{{ \App\Facades\Settings::get('brand.name', 'PawHaven') }}</span>
                        <span class="text-[11px] tracking-wider uppercase font-semibold text-[#69736B] block mt-1">Pet Marketplace &amp; Adoption</span>
                    </div>
                </a>

                {{-- Desktop Navigation Links --}}
                <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-[#202820]" aria-label="Main navigation">
                    <a href="{{ route('public.home') }}" class="text-[#69736B] hover:text-[#245C45] transition-colors">Home</a>
                    <a href="{{ route('listings.index') }}" class="text-[#245C45] font-semibold transition-colors">Explore Pets</a>
                    <a href="{{ route('public.home') }}#pet-shop" class="text-[#69736B] hover:text-[#245C45] transition-colors">Pet Shop</a>
                    <a href="{{ route('listings.index', ['type' => 'adoption']) }}" class="text-[#69736B] hover:text-[#245C45] transition-colors">Adoption &amp; Rescue</a>
                    <a href="{{ route('public.home') }}#breeds" class="text-[#69736B] hover:text-[#245C45] transition-colors">Breeds</a>
                    <a href="{{ route('public.home') }}#community" class="text-[#69736B] hover:text-[#245C45] transition-colors">Community</a>
                </nav>

                {{-- Desktop Right Actions --}}
                <div class="hidden md:flex items-center gap-4">
                    @auth
                        @if(auth()->user()->isSeller())
                            <a href="{{ route('seller.dashboard') }}" class="text-sm font-semibold text-[#245C45] hover:text-[#173F32] px-4 py-2 rounded-lg border border-[#245C45]/20 hover:border-[#245C45] transition-colors">
                                Seller Dashboard
                            </a>
                        @elseif(! auth()->user()->isAdmin())
                            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-[#245C45] hover:text-[#173F32] px-4 py-2 rounded-lg border border-[#245C45]/20 hover:border-[#245C45] transition-colors">
                                Customer Dashboard
                            </a>
                        @else
                            <span class="text-xs font-semibold text-[#69736B] px-3 py-1.5 rounded-full bg-[#202820]/5">
                                {{ auth()->user()->name }}
                            </span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-[#69736B] hover:text-[#202820] px-3 py-2 transition-colors">
                                Log Out
                            </button>
                        </form>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="text-sm font-semibold text-[#202820] hover:text-[#245C45] px-4 py-2 transition-colors">
                                Log In
                            </a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-full text-sm font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-colors">
                                Sign Up
                            </a>
                        @endif
                    @endauth
                </div>

                {{-- Mobile Hamburger --}}
                <div class="flex items-center md:hidden">
                    <button
                        type="button"
                        @click="mobileOpen = !mobileOpen"
                        class="p-2 text-[#202820] hover:bg-[#202820]/5 rounded-xl transition-colors"
                        aria-label="Toggle menu"
                    >
                        <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile Drawer Menu --}}
        <div
            x-show="mobileOpen"
            x-cloak
            @click.away="mobileOpen = false"
            class="md:hidden border-b border-[#202820]/10 bg-[#FAF8F2] px-4 pt-2 pb-6 space-y-3"
        >
            <nav class="flex flex-col space-y-2 text-base font-medium">
                <a href="{{ route('public.home') }}" class="px-3 py-2 rounded-lg text-[#202820]">Home</a>
                <a href="{{ route('listings.index') }}" class="px-3 py-2 rounded-lg text-[#245C45] font-semibold bg-[#245C45]/10">Explore Pets</a>
                <a href="{{ route('public.home') }}#pet-shop" class="px-3 py-2 rounded-lg text-[#202820]">Pet Shop</a>
                <a href="{{ route('listings.index', ['type' => 'adoption']) }}" class="px-3 py-2 rounded-lg text-[#202820]">Adoption &amp; Rescue</a>
                <a href="{{ route('public.home') }}#breeds" class="px-3 py-2 rounded-lg text-[#202820]">Breeds</a>
            </nav>

            <div class="pt-4 border-t border-[#202820]/10 flex flex-col gap-2">
                @auth
                    @if(auth()->user()->isSeller())
                        <a href="{{ route('seller.dashboard') }}" class="w-full text-center py-2.5 px-4 rounded-xl font-semibold text-white bg-[#245C45]">Seller Dashboard</a>
                    @elseif(! auth()->user()->isAdmin())
                        <a href="{{ route('dashboard') }}" class="w-full text-center py-2.5 px-4 rounded-xl font-semibold text-white bg-[#245C45]">Customer Dashboard</a>
                    @else
                        <div class="w-full text-center py-2 px-4 rounded-xl text-xs font-semibold text-[#69736B] bg-[#202820]/5">
                            {{ auth()->user()->name }}
                        </div>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <button type="submit" class="w-full text-center py-2.5 px-4 rounded-xl font-medium text-[#69736B] border border-[#202820]/10">Log Out</button>
                    </form>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="w-full text-center py-2.5 px-4 rounded-xl font-semibold text-[#202820] border border-[#202820]/15">Log In</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="w-full text-center py-2.5 px-4 rounded-xl font-semibold text-white bg-[#245C45]">Sign Up</a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-2 text-xs text-[#69736B] mb-6" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-[#245C45]">Home</a>
                <span>&rsaquo;</span>
                <span class="text-[#202820] font-semibold">Explore Pets</span>
            </nav>

            {{-- Header & Search Form --}}
            <div class="bg-white rounded-3xl border border-[#202820]/10 p-6 sm:p-10 mb-8 shadow-sm">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#245C45]/10 text-[#245C45] text-xs font-bold uppercase tracking-wider mb-3">
                        Verified Community Listings
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#173F32]">
                        Find Your Ideal Companion
                    </h1>
                    <p class="text-[#69736B] text-sm sm:text-base mt-2 leading-relaxed">
                        Every pet is checked by our team before going public. Commercial listings and community adoption posts are strictly distinguished to support ethical pet rehoming.
                    </p>
                </div>

                {{-- Filter Bar --}}
                <form method="GET" action="{{ route('listings.index') }}" class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    {{-- Keyword Search --}}
                    <div class="lg:col-span-2">
                        <label for="q" class="sr-only">Search by keywords or location</label>
                        <div class="relative">
                            <input
                                type="text"
                                id="q"
                                name="q"
                                value="{{ $searchQuery }}"
                                placeholder="Search by name, breed, or location..."
                                class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-4 py-2.5 text-xs text-[#202820] placeholder-[#69736B] focus:border-[#245C45] focus:bg-white focus:outline-none"
                            >
                        </div>
                    </div>

                    {{-- Category Select --}}
                    <div>
                        <label for="category" class="sr-only">Pet Category</label>
                        <select
                            id="category"
                            name="category"
                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                        >
                            <option value="">All Pet Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Listing Type Select --}}
                    <div>
                        <label for="type" class="sr-only">Listing Type</label>
                        <select
                            id="type"
                            name="type"
                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                        >
                            <option value="">All Listing Types</option>
                            <option value="sale" @selected(request('type') === 'sale')>For Sale (Commercial)</option>
                            <option value="adoption" @selected(request('type') === 'adoption')>Adoption &amp; Rescue (No Fee)</option>
                        </select>
                    </div>

                    {{-- Submit Button --}}
                    <div>
                        <button
                            type="submit"
                            class="w-full inline-flex items-center justify-center rounded-xl bg-[#245C45] px-4 py-2.5 text-xs font-semibold text-white hover:bg-[#173F32] shadow-sm transition-colors"
                        >
                            <span>Filter Results</span>
                        </button>
                    </div>
                </form>

                {{-- Active Filter Pills --}}
                @if($searchQuery || request('category') || request('type') || request('breed'))
                    <div class="mt-4 pt-4 border-t border-[#202820]/10 flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-[#69736B] font-medium">Active filters:</span>
                        @if($searchQuery)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#245C45]/10 text-[#245C45] font-semibold">
                                Search: "{{ $searchQuery }}"
                            </span>
                        @endif
                        @if($currentCategory)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#245C45]/10 text-[#245C45] font-semibold">
                                Category: {{ $currentCategory->name }}
                            </span>
                        @endif
                        @if($currentType)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#245C45]/10 text-[#245C45] font-semibold">
                                Type: {{ $currentType === 'adoption' ? 'Adoption & Rescue' : 'Commercial Sale' }}
                            </span>
                        @endif
                        <a href="{{ route('listings.index') }}" class="text-[#69736B] hover:text-[#173F32] underline ml-2">
                            Reset filters
                        </a>
                    </div>
                @endif
            </div>

            {{-- Results Grid --}}
            @if($listings->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($listings as $listing)
                        <article class="bg-white rounded-2xl overflow-hidden border border-[#202820]/10 shadow-sm hover:shadow-md transition-all flex flex-col">
                            {{-- Image Preview & Badges --}}
                            <div class="relative aspect-video w-full bg-[#FAF8F2] overflow-hidden">
                                @if($listing->images->isNotEmpty())
                                    <img src="{{ $listing->images->first()->url() }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex flex-col items-center justify-center text-[#69736B]">
                                        <svg class="w-10 h-10 mb-1 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-[11px] font-medium">Photo in verification</span>
                                    </div>
                                @endif

                                {{-- Category Badge --}}
                                <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/90 backdrop-blur-xs text-[#173F32] shadow-xs">
                                        {{ $listing->petCategory?->name ?? 'Pet' }}
                                    </span>
                                    @if($listing->listing_type === \App\Enums\PetListingType::Sale)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#245C45] text-white shadow-xs">
                                            For Sale
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#F28C75] text-[#173F32] shadow-xs">
                                            Adoption
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Content Body --}}
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between text-xs text-[#69736B] mb-1">
                                        <span>{{ $listing->breed?->name ?? 'Mixed Breed' }}</span>
                                        @if($listing->seller)
                                            <span class="font-medium text-[#202820]">By {{ $listing->seller->name }}</span>
                                        @endif
                                    </div>
                                    <h2 class="text-base font-bold text-[#173F32] group-hover:text-[#245C45] line-clamp-1">
                                        <a href="{{ route('listings.show', $listing) }}">{{ $listing->title }}</a>
                                    </h2>
                                    <p class="text-xs text-[#69736B] line-clamp-2 mt-2 leading-relaxed">{{ $listing->description }}</p>
                                </div>

                                <div class="mt-4 pt-4 border-t border-[#202820]/10 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-[10px] uppercase tracking-wider text-[#69736B] font-semibold block">
                                                {{ $listing->listing_type === \App\Enums\PetListingType::Sale ? 'Commercial Price' : 'Adoption Contribution' }}
                                            </span>
                                            <span class="text-base font-bold text-[#173F32]">
                                                @if($listing->listing_type === \App\Enums\PetListingType::Sale && $listing->price_minor)
                                                    {{ $listing->currency }} {{ number_format($listing->price_minor / 100, 2) }}
                                                @else
                                                    <span class="text-[#245C45] font-semibold text-xs">No Sale Fee</span>
                                                @endif
                                            </span>
                                        </div>

                                        @if($listing->location)
                                            <div class="text-right">
                                                <span class="text-[10px] uppercase tracking-wider text-[#69736B] font-semibold block">Location</span>
                                                <span class="text-xs text-[#202820] font-medium flex items-center justify-end gap-1">
                                                    <svg class="w-3.5 h-3.5 text-[#69736B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    </svg>
                                                    {{ $listing->location }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>

                                    <a
                                        href="{{ route('listings.show', $listing) }}"
                                        class="w-full inline-flex items-center justify-center py-2.5 px-4 rounded-xl text-xs font-semibold text-[#245C45] bg-[#245C45]/10 hover:bg-[#245C45] hover:text-white transition-colors"
                                    >
                                        <span>View Details &amp; Contact Seller</span>
                                        <svg class="ml-1.5 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-10">
                    {{ $listings->links() }}
                </div>
            @else
                {{-- Polished Empty State --}}
                <div class="bg-white rounded-3xl border border-[#202820]/10 p-8 sm:p-14 text-center max-w-2xl mx-auto shadow-sm">
                    <div class="w-16 h-16 rounded-2xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mx-auto mb-5">
                        <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 11.5c-2.2 0-4 1.8-4 4 0 1.9 1.5 3.5 3.5 3.5.7 0 1.3-.2 1.8-.5.5.3 1.1.5 1.8.5 2 0 3.5-1.6 3.5-3.5 0-2.2-1.8-4-4-4zm-5.5-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm11 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-[#173F32]">No matching pet listings found</h3>
                    <p class="text-[#69736B] text-sm mt-3 leading-relaxed">
                        There are currently no active public listings matching your exact search filters. Every listing is reviewed by our administrators before publication to maintain animal welfare integrity.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <a href="{{ route('listings.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors">
                            Clear Search Filters
                        </a>
                        @auth
                            @if(auth()->user()->isSeller())
                                <a href="{{ route('seller.dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-colors">
                                    Submit New Pet Listing &rarr;
                                </a>
                            @endif
                        @else
                            <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-colors">
                                Become a Verified Seller / Shelter
                            </a>
                        @endauth
                    </div>
                </div>
            @endif
        </div>
    </main>

    {{-- Footer --}}
    <footer class="bg-[#173F32] text-white pt-16 pb-12 border-t border-white/10 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 pb-12 border-b border-white/10">
                <div class="lg:col-span-2 space-y-4">
                    <a href="{{ route('public.home') }}" class="flex items-center gap-3 group focus-visible:outline-none">
                        <img
                            src="{{ str_starts_with((string) \App\Facades\Settings::get('brand.logo_dark_path', ''), 'http') ? \App\Facades\Settings::get('brand.logo_dark_path') : asset(ltrim((string) \App\Facades\Settings::get('brand.logo_dark_path', 'images/pawhaven-logo-white.png'), '/')) }}"
                            alt="{{ \App\Facades\Settings::get('brand.name', 'PawHaven') }} Logo"
                            height="48"
                            class="h-12 w-auto max-h-12 max-w-[200px] shrink-0 object-contain"
                            style="height: 48px; max-height: 48px; width: auto; max-width: 200px; object-fit: contain;"
                        >
                        <div>
                            <span class="text-2xl font-bold tracking-tight text-white block leading-none">{{ \App\Facades\Settings::get('brand.name', 'PawHaven') }}</span>
                            <span class="text-[11px] tracking-wider uppercase font-semibold text-white/60 block mt-1">Pet Marketplace &amp; Adoption</span>
                        </div>
                    </a>
                    <p class="text-sm text-white/70 max-w-sm leading-relaxed">
                        {{ \App\Facades\Settings::get('brand.tagline', 'Find Pets. Give Hope. Shop with Love. PawHaven is an ethical platform connecting pet owners, adoption partners, and prospective families.') }}
                    </p>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-white/50 mb-4">Platform</h3>
                    <ul class="space-y-2.5 text-sm text-white/80">
                        <li><a href="{{ route('public.home') }}" class="hover:text-white transition-colors">Home</a></li>
                        <li><a href="{{ route('listings.index') }}" class="hover:text-white transition-colors">Find Pets</a></li>
                        <li><a href="{{ route('public.home') }}#pet-shop" class="hover:text-white transition-colors">Pet Shop</a></li>
                        <li><a href="{{ route('public.home') }}#breeds" class="hover:text-white transition-colors">Breed Directory</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-white/50 mb-4">Community</h3>
                    <ul class="space-y-2.5 text-sm text-white/80">
                        <li><a href="{{ route('listings.index', ['type' => 'adoption']) }}" class="hover:text-white transition-colors">Adoption &amp; Rescue</a></li>
                        <li><a href="{{ route('public.home') }}#community" class="hover:text-white transition-colors">Community Network</a></li>
                        <li><a href="{{ route('public.home') }}#how-it-works" class="hover:text-white transition-colors">How It Works</a></li>
                        <li><a href="{{ route('public.home') }}#trust-safety" class="hover:text-white transition-colors">Trust &amp; Safety</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-white/50 mb-4">Account</h3>
                    <ul class="space-y-2.5 text-sm text-white/80">
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="hover:text-white transition-colors">Dashboard</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Log In</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Create Account</a></li>
                        @endauth
                    </ul>
                </div>
            </div>

            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-white/50">
                <p>&copy; {{ date('Y') }} {{ \App\Facades\Settings::get('brand.name', 'PawHaven') }}. All rights reserved.</p>
                <p>Dedicated to ethical pet parenthood and lifelong companion care.</p>
            </div>
        </div>
    </footer>

</body>
</html>
