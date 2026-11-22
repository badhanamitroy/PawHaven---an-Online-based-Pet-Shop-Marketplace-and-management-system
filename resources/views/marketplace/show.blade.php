<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $listing->title }} — {{ \App\Facades\Settings::get('brand.name', 'PawHaven') }}</title>
    <meta name="description" content="{{ Str::limit($listing->description, 160) }}">

    @include('partials.favicon')
    @include('partials.custom-cursor')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="bg-[#FAF8F2] text-[#202820] font-sans antialiased selection:bg-[#245C45] selection:text-white" x-data="{ mobileOpen: false, activeImage: '{{ $listing->images->isNotEmpty() ? $listing->images->first()->url() : '' }}' }">

    {{-- Non-published Preview Warning Banner --}}
    @if($listing->status !== \App\Enums\PetListingStatus::Published)
        <div class="bg-amber-500 text-black py-2.5 px-4 text-xs font-bold sticky top-0 z-[60] shadow-md border-b border-amber-600 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-black shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>AUTHOR PREVIEW: This listing status is <strong>{{ strtoupper($listing->status->value) }}</strong>. It is not currently visible to public visitors.</span>
            </div>
            @if(auth()->check() && auth()->user()->isSeller())
                <a href="{{ route('seller.dashboard') }}" class="underline text-black font-semibold hover:text-black/80">Return to Seller Dashboard &rarr;</a>
            @endif
        </div>
    @endif

    {{-- Navigation Bar --}}
    <header class="sticky top-0 z-40 bg-[#FAF8F2]/95 backdrop-blur-md border-b border-[#202820]/10 transition-colors">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                {{-- Brand Logo --}}
                <a href="{{ route('public.home') }}" class="flex items-center gap-3 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded-lg">
                    <img
                        src="{{ \App\Services\MediaManager::logoUrl('green') }}"
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
            </nav>

            <div class="pt-4 border-t border-[#202820]/10 flex flex-col gap-2">
                @auth
                    @if(auth()->user()->isSeller())
                        <a href="{{ route('seller.dashboard') }}" class="w-full text-center py-2.5 px-4 rounded-xl font-semibold text-white bg-[#245C45]">Seller Dashboard</a>
                    @elseif(! auth()->user()->isAdmin())
                        <a href="{{ route('dashboard') }}" class="w-full text-center py-2.5 px-4 rounded-xl font-semibold text-white bg-[#245C45]">Customer Dashboard</a>
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
    <main class="py-10 sm:py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-2 text-xs text-[#69736B] mb-6" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-[#245C45]">Home</a>
                <span>&rsaquo;</span>
                <a href="{{ route('listings.index') }}" class="hover:text-[#245C45]">Explore Pets</a>
                <span>&rsaquo;</span>
                <span class="text-[#202820] font-semibold truncate max-w-xs sm:max-w-sm">{{ $listing->title }}</span>
            </nav>

            {{-- Flash Messages --}}
            @if(session('error'))
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                {{-- Left: Photo Gallery & Overview (7 cols) --}}
                <div class="lg:col-span-7 space-y-6">
                    {{-- Media Card --}}
                    <div class="bg-white rounded-3xl border border-[#202820]/10 overflow-hidden shadow-sm">
                        <div class="relative aspect-4/3 w-full bg-[#FAF8F2] overflow-hidden">
                            @if($listing->images->isNotEmpty())
                                <template x-if="activeImage">
                                    <img :src="activeImage" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!activeImage">
                                    <img src="{{ $listing->images->first()->url() }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                                </template>
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-[#69736B]">
                                    <svg class="w-16 h-16 mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs font-medium">No verified photos uploaded yet</span>
                                </div>
                            @endif

                            {{-- Badges on Hero Image --}}
                            <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-white/95 backdrop-blur-xs text-[#173F32] shadow-sm">
                                    {{ $listing->petCategory?->name ?? 'Pet' }}
                                </span>
                                @if($listing->listing_type === \App\Enums\PetListingType::Sale)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-[#245C45] text-white shadow-sm">
                                        Commercial Sale
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-[#F28C75] text-[#173F32] shadow-sm">
                                        Adoption &amp; Rescue
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Thumbnails --}}
                        @if($listing->images->count() > 1)
                            <div class="p-4 bg-[#FAF8F2] border-t border-[#202820]/10 flex items-center gap-3 overflow-x-auto">
                                @foreach($listing->images as $img)
                                    <button
                                        type="button"
                                        @click="activeImage = '{{ $img->url() }}'"
                                        class="w-16 h-16 rounded-xl overflow-hidden border-2 shrink-0 transition-transform hover:scale-105 focus:outline-none"
                                        :class="activeImage === '{{ $img->url() }}' ? 'border-[#245C45] ring-2 ring-[#245C45]/20' : 'border-transparent opacity-75 hover:opacity-100'"
                                    >
                                        <img src="{{ $img->url() }}" alt="Thumbnail" class="w-full h-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Description & Attributes Card --}}
                    <div class="bg-white rounded-3xl border border-[#202820]/10 p-6 sm:p-8 shadow-sm space-y-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-xs font-bold uppercase tracking-widest text-[#245C45]">About this pet</h2>
                                <h1 class="text-2xl sm:text-3xl font-bold text-[#173F32] mt-1">{{ $listing->title }}</h1>
                            </div>
                            @auth
                                <form method="POST" action="{{ route('listings.favorite', $listing) }}">
                                    @csrf
                                    @php
                                        $isFav = auth()->user()->savedPets()->where('pet_listing_id', $listing->id)->exists();
                                    @endphp
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-colors {{ $isFav ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-[#FAF8F2] border-[#202820]/15 text-[#202820] hover:bg-white' }}"
                                        title="{{ $isFav ? 'Remove from saved' : 'Save to favorites' }}"
                                    >
                                        <svg class="w-4 h-4 {{ $isFav ? 'fill-rose-600 text-rose-600' : 'text-[#69736B]' }}" viewBox="0 0 24 24" fill="{{ $isFav ? 'currentColor' : 'none' }}" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                        <span>{{ $isFav ? 'Saved' : 'Save Pet' }}</span>
                                    </button>
                                </form>
                            @endauth
                        </div>

                        {{-- Attributes Matrix --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/5 text-xs">
                            <div>
                                <span class="text-[#69736B] block">Species</span>
                                <strong class="text-[#173F32] text-sm font-semibold">{{ $listing->petCategory?->name ?? 'General' }}</strong>
                            </div>
                            <div>
                                <span class="text-[#69736B] block">Breed</span>
                                <strong class="text-[#173F32] text-sm font-semibold">{{ $listing->breed?->name ?? 'Mixed / Other' }}</strong>
                            </div>
                            <div>
                                <span class="text-[#69736B] block">Listing Type</span>
                                <strong class="text-[#173F32] text-sm font-semibold uppercase">{{ $listing->listing_type->value }}</strong>
                            </div>
                            <div>
                                <span class="text-[#69736B] block">Location</span>
                                <strong class="text-[#173F32] text-sm font-semibold">{{ $listing->location ?? 'Mainland' }}</strong>
                            </div>
                        </div>

                        {{-- Full Description --}}
                        <div class="prose max-w-none text-sm text-[#202820]/90 leading-relaxed space-y-3">
                            <h3 class="text-sm font-bold text-[#173F32] uppercase tracking-wider">Detailed Description</h3>
                            <div class="whitespace-pre-line text-sm text-[#202820]/80">
                                {{ $listing->description }}
                            </div>
                        </div>

                        {{-- Ethical Policy Notice --}}
                        <div class="p-4 rounded-2xl bg-[#245C45]/5 border border-[#245C45]/15 flex items-start gap-3">
                            <svg class="w-5 h-5 text-[#245C45] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <div class="text-xs text-[#173F32] leading-relaxed">
                                <strong>PawHaven Welfare Assurance:</strong> Every commercial pet breeder and adoption shelter on PawHaven must adhere to humane welfare standards. In-person meeting and health review are strongly encouraged prior to handover.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Action Box & Seller Card (5 cols) --}}
                <div class="lg:col-span-5 space-y-6">
                    {{-- Transaction / Request Card --}}
                    <div class="bg-white rounded-3xl border border-[#202820]/10 p-6 sm:p-8 shadow-sm space-y-6">
                        {{-- Price Header --}}
                        <div class="border-b border-[#202820]/10 pb-5">
                            <span class="text-xs uppercase tracking-wider font-semibold text-[#69736B]">
                                {{ $listing->listing_type === \App\Enums\PetListingType::Sale ? 'Commercial Sale Price' : 'Community Adoption Fee' }}
                            </span>
                            <div class="flex items-baseline gap-2 mt-1">
                                @if($listing->listing_type === \App\Enums\PetListingType::Sale)
                                    <span class="text-3xl sm:text-4xl font-extrabold text-[#173F32]">
                                        {{ $listing->currency }} {{ number_format(($listing->price_minor ?? 0) / 100, 2) }}
                                    </span>
                                    <span class="text-xs text-[#69736B]">Cash on Delivery / Direct Handover</span>
                                @else
                                    <span class="text-2xl sm:text-3xl font-extrabold text-[#245C45]">
                                        Free / Adoption Contribution
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Seller / Lister Profile --}}
                        <div class="flex items-center gap-3 p-3.5 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10">
                            <div class="w-10 h-10 rounded-full bg-[#245C45] text-white flex items-center justify-center font-bold text-sm shrink-0">
                                {{ strtoupper(substr($listing->seller?->name ?? 'P', 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="text-xs text-[#69736B] block">Listed by verified member</span>
                                <h4 class="text-sm font-bold text-[#173F32] truncate">{{ $listing->seller?->name ?? 'Verified Member' }}</h4>
                            </div>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                Verified
                            </span>
                        </div>

                        {{-- Action Branching --}}
                        @if($isOwner)
                            {{-- Seller Views Own Listing --}}
                            <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 space-y-2">
                                <div class="font-bold flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>This is your listing</span>
                                </div>
                                <p class="text-amber-800 leading-relaxed">You are viewing this listing as its owner. You can pause, update or manage it from your seller portal.</p>
                                <a
                                    href="{{ route('seller.dashboard') }}"
                                    class="inline-flex items-center justify-center w-full py-2.5 px-4 rounded-xl text-xs font-semibold text-white bg-[#173F32] hover:bg-[#245C45] transition-colors"
                                >
                                    Open Seller Dashboard &rarr;
                                </a>
                            </div>
                        @elseif(! auth()->check())
                            {{-- Guest View: Requires Login/Register --}}
                            <div class="space-y-4">
                                <p class="text-xs text-[#69736B] leading-relaxed">
                                    To protect animal safety and maintain verified communication records, you must sign in to submit a purchase request or adoption inquiry.
                                </p>
                                <div class="flex flex-col gap-2.5">
                                    <a
                                        href="{{ route('login') }}"
                                        class="w-full inline-flex items-center justify-center py-3.5 px-5 rounded-2xl text-xs font-bold uppercase tracking-wider text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-colors text-center"
                                    >
                                        {{ $listing->listing_type === \App\Enums\PetListingType::Sale ? 'Log In to Submit Purchase Request' : 'Log In to Apply for Adoption' }}
                                    </a>
                                    <a
                                        href="{{ route('register') }}"
                                        class="w-full inline-flex items-center justify-center py-3 px-5 rounded-2xl text-xs font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-colors text-center"
                                    >
                                        Create a Free Account
                                    </a>
                                </div>
                            </div>
                        @else
                            {{-- Authenticated Customer View: Direct Action Form --}}
                            @if($listing->listing_type === \App\Enums\PetListingType::Sale)
                                {{-- Commercial Purchase Request Form --}}
                                <form method="POST" action="{{ route('listings.purchase', $listing) }}" class="space-y-3.5">
                                    @csrf
                                    <div>
                                        <h3 class="text-sm font-bold text-[#173F32]">Submit Purchase Request</h3>
                                        <p class="text-xs text-[#69736B] mt-0.5">The seller will review your request and contact you to coordinate safe handover.</p>
                                    </div>

                                    <div>
                                        <label for="customer_phone" class="block text-xs font-semibold text-[#202820] mb-1">Your Contact Phone *</label>
                                        <input
                                            type="tel"
                                            id="customer_phone"
                                            name="customer_phone"
                                            required
                                            placeholder="Include country calling code"
                                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3.5 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                                        >
                                        @error('customer_phone') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="shipping_city" class="block text-xs font-semibold text-[#202820] mb-1">City / Region *</label>
                                        <input
                                            type="text"
                                            id="shipping_city"
                                            name="shipping_city"
                                            required
                                            placeholder="City, region"
                                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3.5 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                                        >
                                        @error('shipping_city') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="shipping_address" class="block text-xs font-semibold text-[#202820] mb-1">Preferred Meeting / Delivery Address *</label>
                                        <textarea
                                            id="shipping_address"
                                            name="shipping_address"
                                            rows="2"
                                            required
                                            placeholder="Provide a delivery address or safe public meetup location..."
                                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3.5 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                                        ></textarea>
                                        @error('shipping_address') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="notes" class="block text-xs font-semibold text-[#202820] mb-1">Message for Seller (Optional)</label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows="2"
                                            placeholder="Ask questions about veterinary records, feeding, or timetable..."
                                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3.5 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                                        ></textarea>
                                    </div>

                                    <button
                                        type="submit"
                                        class="w-full inline-flex items-center justify-center py-3.5 px-5 rounded-2xl text-xs font-bold uppercase tracking-wider text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-colors text-center"
                                    >
                                        Confirm &amp; Submit Buy Request
                                    </button>
                                </form>
                            @else
                                {{-- Adoption Application Form --}}
                                <form method="POST" action="{{ route('listings.inquire', $listing) }}" class="space-y-3.5">
                                    @csrf
                                    <div>
                                        <h3 class="text-sm font-bold text-[#173F32]">Submit Adoption Application</h3>
                                        <p class="text-xs text-[#69736B] mt-0.5">Share your household details to help the rescue team ensure this pet is a good match.</p>
                                    </div>

                                    <div>
                                        <label for="customer_phone" class="block text-xs font-semibold text-[#202820] mb-1">Contact Phone *</label>
                                        <input
                                            type="tel"
                                            id="customer_phone"
                                            name="customer_phone"
                                            required
                                            placeholder="Include country calling code"
                                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3.5 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                                        >
                                        @error('customer_phone') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="shipping_city" class="block text-xs font-semibold text-[#202820] mb-1">City / Neighborhood *</label>
                                        <input
                                            type="text"
                                            id="shipping_city"
                                            name="shipping_city"
                                            required
                                            placeholder="e.g. Portland, OR"
                                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3.5 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                                        >
                                        @error('shipping_city') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="shipping_address" class="block text-xs font-semibold text-[#202820] mb-1">Home Address *</label>
                                        <input
                                            type="text"
                                            id="shipping_address"
                                            name="shipping_address"
                                            required
                                            placeholder="Residential street address"
                                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3.5 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                                        >
                                        @error('shipping_address') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="notes" class="block text-xs font-semibold text-[#202820] mb-1">Household &amp; Experience Details *</label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows="3"
                                            required
                                            placeholder="Describe your household (fenced yard, other pets, children, time spent at home)..."
                                            class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-3.5 py-2 text-xs text-[#202820] focus:border-[#245C45] focus:bg-white focus:outline-none"
                                        ></textarea>
                                        @error('notes') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                                    </div>

                                    <button
                                        type="submit"
                                        class="w-full inline-flex items-center justify-center py-3.5 px-5 rounded-2xl text-xs font-bold uppercase tracking-wider text-white bg-[#173F32] hover:bg-[#245C45] shadow-sm transition-colors text-center"
                                    >
                                        Submit Adoption Application
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Related Listings Section --}}
            @if($relatedListings->isNotEmpty())
                <div class="mt-16 pt-12 border-t border-[#202820]/10">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">More Companions</span>
                            <h2 class="text-2xl font-bold tracking-tight text-[#173F32] mt-0.5">Similar Pets Available</h2>
                        </div>
                        <a href="{{ route('listings.index') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                            View All &rarr;
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach($relatedListings as $rel)
                            <article class="bg-white rounded-2xl overflow-hidden border border-[#202820]/10 shadow-sm hover:shadow-md transition-all flex flex-col">
                                <div class="relative aspect-video w-full bg-[#FAF8F2] overflow-hidden">
                                    @if($rel->images->isNotEmpty())
                                        <img src="{{ $rel->images->first()->url() }}" alt="{{ $rel->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-xs text-[#69736B]">
                                            Photo pending
                                        </div>
                                    @endif
                                    <div class="absolute top-2 left-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-white/90 text-[#173F32]">
                                            {{ $rel->petCategory?->name ?? 'Pet' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="p-4 flex-1 flex flex-col justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold text-[#173F32] line-clamp-1">
                                            <a href="{{ route('listings.show', $rel) }}">{{ $rel->title }}</a>
                                        </h3>
                                        <p class="text-xs text-[#69736B] line-clamp-2 mt-1">{{ $rel->description }}</p>
                                    </div>
                                    <div class="mt-3 pt-3 border-t border-[#202820]/10 flex items-center justify-between">
                                        <span class="text-xs font-bold text-[#173F32]">
                                            @if($rel->listing_type === \App\Enums\PetListingType::Sale && $rel->price_minor)
                                                {{ $rel->currency }} {{ number_format($rel->price_minor / 100, 2) }}
                                            @else
                                                <span class="text-[#245C45]">Adoption</span>
                                            @endif
                                        </span>
                                        <a href="{{ route('listings.show', $rel) }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                                            View Details &rarr;
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
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
                            src="{{ \App\Services\MediaManager::logoUrl('white') }}"
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
