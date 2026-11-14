@php
    use App\Facades\Settings;
    $p = $previewSettings ?? [];
    $brandName = $p['brand.name'] ?? Settings::get('brand.name', 'PawHaven');
    $brandTagline = $p['brand.tagline'] ?? Settings::get('brand.tagline', 'Find Pets. Give Hope. Shop with Love.');
    $announcementEnabled = isset($p['homepage.announcement_enabled']) ? (bool)$p['homepage.announcement_enabled'] : filter_var(Settings::get('homepage.announcement_enabled', true), FILTER_VALIDATE_BOOLEAN);
    $announcementText = $p['homepage.announcement_text'] ?? Settings::get('homepage.announcement_text', 'Connecting ethical breeders, local shelters, and loving families across the community.');
    $heroHeadline = $p['homepage.hero_headline'] ?? Settings::get('homepage.hero_headline', 'Your next best friend is waiting.');
    $heroSupporting = $p['homepage.hero_supporting'] ?? Settings::get('homepage.hero_supporting', Settings::get('homepage.hero_supporting_text', 'Discover pets, shop food and accessories, and help animals find loving homes—all in one place.'));
    $ctaPrimary = $p['homepage.cta_primary_label'] ?? Settings::get('homepage.cta_primary_label', 'Explore Pets');
    $ctaPrimaryUrl = $p['homepage.cta_primary_url'] ?? Settings::get('homepage.cta_primary_url', '#find-pets');
    $ctaSecondary = $p['homepage.cta_secondary_label'] ?? Settings::get('homepage.cta_secondary_label', 'Shop Pet Supplies');
    $ctaSecondaryUrl = $p['homepage.cta_secondary_url'] ?? Settings::get('homepage.cta_secondary_url', '#pet-shop');
    $ctaAdoption = $p['homepage.cta_adoption_label'] ?? Settings::get('homepage.cta_adoption_label', 'Explore Adoption');
    $ctaAdoptionUrl = $p['homepage.cta_adoption_url'] ?? Settings::get('homepage.cta_adoption_url', '#adoption-rescue');

    $sectionMarketplace = isset($p['homepage.section_marketplace_visible']) ? (bool)$p['homepage.section_marketplace_visible'] : filter_var(Settings::get('homepage.section_marketplace_visible', true), FILTER_VALIDATE_BOOLEAN);
    $sectionShop = isset($p['homepage.section_shop_visible']) ? (bool)$p['homepage.section_shop_visible'] : filter_var(Settings::get('homepage.section_shop_visible', true), FILTER_VALIDATE_BOOLEAN);
    $sectionAdoption = isset($p['homepage.section_adoption_visible']) ? (bool)$p['homepage.section_adoption_visible'] : filter_var(Settings::get('homepage.section_adoption_visible', true), FILTER_VALIDATE_BOOLEAN);
    $sectionBreeds = isset($p['homepage.section_breeds_visible']) ? (bool)$p['homepage.section_breeds_visible'] : filter_var(Settings::get('homepage.section_breeds_visible', true), FILTER_VALIDATE_BOOLEAN);
    $sectionCommunity = isset($p['homepage.section_community_visible']) ? (bool)$p['homepage.section_community_visible'] : filter_var(Settings::get('homepage.section_community_visible', true), FILTER_VALIDATE_BOOLEAN);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brandName }} — {{ $brandTagline }}</title>
    <meta name="description" content="{{ Settings::get('brand.meta_description', 'PawHaven is a premium pet marketplace, adoption and rescue community, breed encyclopedia, and pet supplies shop.') }}">

    @include('partials.favicon')

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="bg-[#FAF8F2] text-[#202820] font-sans antialiased selection:bg-[#245C45] selection:text-white" x-data="{ mobileOpen: false, buyModalOpen: false, buyProduct: { id: '', name: '', price: '' } }">

    @if(!empty($isDraftPreview))
    {{-- Administrator Draft Preview Banner --}}
    <div class="bg-amber-500 text-black py-2.5 px-4 text-xs font-bold sticky top-0 z-[60] shadow-md border-b border-amber-600 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-black shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span>DRAFT PREVIEW MODE — Changes rendered below are not yet published to public visitors.</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.homepage.index') }}" class="underline hover:text-white">Back to Customizer</a>
            <form method="POST" action="{{ route('admin.homepage.publish') }}" class="inline">
                @csrf
                <button type="submit" class="px-3 py-1 rounded bg-black text-white hover:bg-neutral-800 text-[11px] font-semibold">Publish Live</button>
            </form>
        </div>
    </div>
    @endif

    @if($announcementEnabled)
    {{-- Top Announcement Bar --}}
    <div class="bg-[#173F32] text-[#FAF8F2] text-xs sm:text-sm py-2 px-4 text-center font-medium">
        <div class="max-w-7xl mx-auto flex items-center justify-center gap-2">
            <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-[#F28C75] animate-pulse"></span>
            <span>{{ $announcementText }}</span>
        </div>
    </div>
    @endif

    {{-- Navigation Bar --}}
    <header class="sticky top-0 z-50 bg-[#FAF8F2]/95 backdrop-blur-md border-b border-[#202820]/10 transition-colors">
        <div class="w-full px-4 sm:px-6 lg:px-[5vw]">
            <div class="flex items-center justify-between h-20">
                {{-- Brand Logo --}}
                <a href="{{ route('public.home') }}" class="flex items-center gap-3 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded-lg">
                    <img
                        src="{{ str_starts_with((string) Settings::get('brand.logo_light_path', ''), 'http') ? Settings::get('brand.logo_light_path') : asset(ltrim((string) Settings::get('brand.logo_light_path', 'images/pawhaven-logo-green.png'), '/')) }}"
                        alt="{{ $brandName }} Logo"
                        height="48"
                        class="h-12 w-auto max-h-12 max-w-[200px] shrink-0 object-contain transition-transform group-hover:scale-105"
                        style="height: 48px; max-height: 48px; width: auto; max-width: 200px; object-fit: contain;"
                    >
                    <div>
                        <span class="text-2xl font-bold tracking-tight text-[#173F32] block leading-none">{{ $brandName }}</span>
                        <span class="text-[11px] tracking-wider uppercase font-semibold text-[#69736B] block mt-1">Pet Marketplace &amp; Adoption</span>
                    </div>
                </a>

                {{-- Desktop Navigation Links --}}
                <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-[#202820]" aria-label="Main navigation">
                    <a href="{{ route('public.home') }}" class="text-[#245C45] font-semibold transition-colors hover:text-[#173F32] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">Home</a>
                    <a href="#find-pets" class="text-[#69736B] hover:text-[#245C45] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">Find Pets</a>
                    <a href="#pet-shop" class="text-[#69736B] hover:text-[#245C45] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">Pet Shop</a>
                    <a href="#adoption-rescue" class="text-[#69736B] hover:text-[#245C45] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">Adoption &amp; Rescue</a>
                    <a href="#breeds" class="text-[#69736B] hover:text-[#245C45] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">Breeds</a>
                    <a href="#community" class="text-[#69736B] hover:text-[#245C45] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">Community</a>
                    <a href="{{ route('careers.index') }}" class="text-[#69736B] hover:text-[#245C45] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">Careers</a>
                </nav>

                {{-- Desktop Right Actions (Cart + Auth) --}}
                <div class="hidden md:flex items-center gap-4">
                    {{-- Cart Entry Point (Linked to Pet Shop with milestone placeholder notice) --}}
                    <a
                        href="#pet-shop"
                        class="relative p-2.5 text-[#202820] hover:text-[#245C45] hover:bg-[#202820]/5 rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45]"
                        aria-label="Shopping Cart (PawHaven Store - Cart enabled in upcoming e-commerce milestone)"
                        title="Shopping Cart (PawHaven Store)"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span class="absolute top-1.5 right-1.5 inline-flex items-center justify-center min-w-[16px] h-4 px-1 text-[10px] font-bold text-white bg-[#245C45] rounded-full">
                            0
                        </span>
                    </a>

                    @auth
                        @if(auth()->user()->isSeller())
                            <a href="{{ route('seller.dashboard') }}" class="text-sm font-semibold text-[#245C45] hover:text-[#173F32] px-4 py-2 rounded-lg border border-[#245C45]/20 hover:border-[#245C45] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45]">
                                Seller Dashboard
                            </a>
                        @elseif(! auth()->user()->isAdmin())
                            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-[#245C45] hover:text-[#173F32] px-4 py-2 rounded-lg border border-[#245C45]/20 hover:border-[#245C45] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45]">
                                Customer Dashboard
                            </a>
                        @else
                            <span class="text-xs font-semibold text-[#69736B] px-3 py-1.5 rounded-full bg-[#202820]/5">
                                {{ auth()->user()->name }}
                            </span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-[#69736B] hover:text-[#202820] px-3 py-2 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">
                                Log Out
                            </button>
                        </form>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="text-sm font-semibold text-[#202820] hover:text-[#245C45] px-4 py-2 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45] rounded">
                                Log In
                            </a>
                        @endif

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-full text-sm font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm hover:shadow transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#245C45]">
                                Sign Up
                            </a>
                        @endif
                    @endauth
                </div>

                {{-- Mobile Header Right Controls --}}
                <div class="flex md:hidden items-center gap-1.5">
                    {{-- Mobile Cart Icon --}}
                    <a
                        href="#pet-shop"
                        class="relative p-2 text-[#202820] hover:text-[#245C45] rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45]"
                        aria-label="Shopping Cart (PawHaven Store)"
                        title="Shopping Cart (PawHaven Store)"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span class="absolute top-1 right-1 inline-flex items-center justify-center min-w-[15px] h-[15px] px-1 text-[9px] font-bold text-white bg-[#245C45] rounded-full">
                            0
                        </span>
                    </a>

                    {{-- Mobile Menu Hamburger --}}
                    <button
                        type="button"
                        @click="mobileOpen = !mobileOpen"
                        :aria-expanded="mobileOpen.toString()"
                        aria-label="Toggle navigation menu"
                        class="p-2 rounded-lg text-[#202820] hover:text-[#245C45] hover:bg-[#202820]/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45]"
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
                <a @click="mobileOpen = false" href="{{ route('public.home') }}" class="px-3 py-2 rounded-lg text-[#245C45] font-semibold bg-[#245C45]/10">Home</a>
                <a @click="mobileOpen = false" href="#find-pets" class="px-3 py-2 rounded-lg text-[#202820] hover:bg-[#202820]/5">Find Pets</a>
                <a @click="mobileOpen = false" href="#pet-shop" class="px-3 py-2 rounded-lg text-[#202820] hover:bg-[#202820]/5 flex items-center justify-between">
                    <span>Pet Shop</span>
                    <span class="text-xs bg-[#245C45]/10 text-[#245C45] font-semibold px-2 py-0.5 rounded-full">Store</span>
                </a>
                <a @click="mobileOpen = false" href="#adoption-rescue" class="px-3 py-2 rounded-lg text-[#202820] hover:bg-[#202820]/5">Adoption &amp; Rescue</a>
                <a @click="mobileOpen = false" href="#breeds" class="px-3 py-2 rounded-lg text-[#202820] hover:bg-[#202820]/5">Breeds</a>
                <a @click="mobileOpen = false" href="#community" class="px-3 py-2 rounded-lg text-[#202820] hover:bg-[#202820]/5">Community</a>
                <a @click="mobileOpen = false" href="{{ route('careers.index') }}" class="px-3 py-2 rounded-lg text-[#202820] hover:bg-[#202820]/5">Careers</a>
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
                        <button type="submit" class="w-full text-center py-2.5 px-4 rounded-xl font-medium text-[#69736B] hover:text-[#202820] border border-[#202820]/10">Log Out</button>
                    </form>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="w-full text-center py-2.5 px-4 rounded-xl font-semibold text-[#202820] border border-[#202820]/15 hover:bg-[#202820]/5">Log In</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="w-full text-center py-2.5 px-4 rounded-xl font-semibold text-white bg-[#245C45] hover:bg-[#173F32]">Sign Up</a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    <main id="main-content">
        {{-- HERO SECTION --}}
        <section class="relative isolate flex min-h-[520px] items-center overflow-hidden py-10 lg:min-h-[clamp(520px,35vw,700px)] lg:py-14">
            <img
                src="{{ asset('images/pawhaven-homepage-banner.png') }}"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 h-full w-full object-cover object-[72%_center] lg:object-center"
                fetchpriority="high"
            >
            <div class="relative z-10 mx-auto w-full px-4 sm:px-6 lg:px-[5vw]">
                {{-- Hero Content --}}
                <div class="mx-auto max-w-[900px] space-y-6 rounded-[2rem] bg-[#FAF8F2] p-5 text-center shadow-sm sm:p-8 lg:mx-0 lg:w-[54%] lg:rounded-none lg:bg-transparent lg:p-0 lg:text-left lg:shadow-none">
                        {{-- EXACT HERO HEADLINE PRESERVED VERBATIM AS DEFAULT --}}
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-[#173F32] leading-[1.12]">
                            {{ $heroHeadline }}
                        </h1>

                        {{-- SUPPORTING CONTENT --}}
                        <p class="text-lg sm:text-xl text-[#69736B] max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                            {{ $heroSupporting }}
                        </p>

                        {{-- CALLS TO ACTION --}}
                        <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3.5 pt-2 flex-wrap">
                            {{-- Primary CTA 1: Explore Pets --}}
                            <a href="{{ $ctaPrimaryUrl }}" class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 rounded-full text-base font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-md hover:shadow-lg transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#245C45]">
                                <span>{{ $ctaPrimary }}</span>
                                <svg class="ml-2 w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </a>

                            {{-- Primary CTA 2: Shop Pet Supplies --}}
                            <a href="{{ $ctaSecondaryUrl }}" class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 rounded-full text-base font-semibold text-[#173F32] bg-white border-2 border-[#245C45]/20 hover:border-[#245C45] hover:bg-[#FAF8F2] shadow-sm transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45]">
                                <span>{{ $ctaSecondary }}</span>
                            </a>

                            {{-- Secondary CTA: Explore Adoption --}}
                            <a href="{{ $ctaAdoptionUrl }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-4 rounded-full text-base font-semibold text-[#245C45] hover:text-[#173F32] hover:bg-[#245C45]/5 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45]">
                                <span>{{ $ctaAdoption }}</span>
                            </a>
                        </div>

                        {{-- Trust Highlights --}}
                        <div class="grid max-w-lg grid-cols-3 gap-4 rounded-2xl border border-white/90 bg-white/80 px-4 py-4 text-left shadow-sm mx-auto lg:mx-0">
                            <div>
                                <div class="text-sm font-bold text-[#173F32]">Moderated</div>
                                <div class="text-xs text-[#69736B]">Pre-reviewed listings</div>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-[#173F32]">PawHaven Shop</div>
                                <div class="text-xs text-[#69736B]">Single-store supplies</div>
                            </div>
                            <div>
                                <div class="text-sm font-bold text-[#173F32]">Rescue Network</div>
                                <div class="text-xs text-[#69736B]">Direct shelter adoption</div>
                            </div>
                        </div>
                    </div>
            </div>
        </section>

        {{-- PET CATEGORY SECTION --}}
        <section id="categories" class="py-16 sm:py-24 bg-white border-y border-[#202820]/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-12">
                    <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Categories</span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#173F32] mt-1">Browse by Pet Category</h2>
                    <p class="text-[#69736B] mt-3">Find companions, adoption inquiries, and essential care guides tailored to every pet.</p>
                </div>

                @if(isset($petCategories) && $petCategories->isNotEmpty())
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-6">
                        @foreach($petCategories as $cat)
                            <a href="{{ route('listings.index', ['category' => $cat->id]) }}" class="group relative rounded-2xl overflow-hidden border border-[#202820]/10 bg-[#FAF8F2] shadow-sm hover:shadow-md transition-all p-5 text-center flex flex-col items-center justify-center">
                                <div class="w-14 h-14 rounded-2xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mb-3 group-hover:bg-[#245C45] group-hover:text-white transition-all">
                                    <svg class="w-7 h-7 fill-current" viewBox="0 0 24 24">
                                        <path d="M12 11.5c-2.2 0-4 1.8-4 4 0 1.9 1.5 3.5 3.5 3.5.7 0 1.3-.2 1.8-.5.5.3 1.1.5 1.8.5 2 0 3.5-1.6 3.5-3.5 0-2.2-1.8-4-4-4zm-5.5-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm11 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                                    </svg>
                                </div>
                                <h3 class="font-bold text-[#173F32] group-hover:text-[#245C45] transition-colors text-sm sm:text-base">{{ $cat->name }}</h3>
                                <p class="text-xs text-[#69736B] mt-1">{{ $cat->pet_listings_count }} available</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-6">
                        {{-- Dogs --}}
                        <a href="{{ route('listings.index') }}" class="group relative rounded-2xl overflow-hidden border border-[#202820]/10 bg-[#FAF8F2] shadow-sm hover:shadow-md transition-all">
                            <div class="aspect-square w-full overflow-hidden">
                                <img src="https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=600&q=80" alt="Dogs category" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                            </div>
                            <div class="p-3.5 text-center">
                                <h3 class="font-bold text-[#173F32] group-hover:text-[#245C45] transition-colors">Dogs</h3>
                                <p class="text-xs text-[#69736B] mt-0.5">Puppies &amp; adult breeds</p>
                            </div>
                        </a>

                        {{-- Cats --}}
                        <a href="{{ route('listings.index') }}" class="group relative rounded-2xl overflow-hidden border border-[#202820]/10 bg-[#FAF8F2] shadow-sm hover:shadow-md transition-all">
                            <div class="aspect-square w-full overflow-hidden">
                                <img src="{{ asset('images/british-shorthair.jpg') }}" alt="British Shorthair cat" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                            </div>
                            <div class="p-3.5 text-center">
                                <h3 class="font-bold text-[#173F32] group-hover:text-[#245C45] transition-colors">Cats</h3>
                                <p class="text-xs text-[#69736B] mt-0.5">Kittens &amp; feline companions</p>
                            </div>
                        </a>

                        {{-- Birds --}}
                        <a href="{{ route('listings.index') }}" class="group relative rounded-2xl overflow-hidden border border-[#202820]/10 bg-[#FAF8F2] shadow-sm hover:shadow-md transition-all">
                            <div class="aspect-square w-full overflow-hidden">
                                <img src="{{ asset('images/birds-category.jpg') }}" alt="Birds category" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                            </div>
                            <div class="p-3.5 text-center">
                                <h3 class="font-bold text-[#173F32] group-hover:text-[#245C45] transition-colors">Birds</h3>
                                <p class="text-xs text-[#69736B] mt-0.5">Parrots, canaries &amp; finches</p>
                            </div>
                        </a>

                        {{-- Rabbits --}}
                        <a href="{{ route('listings.index') }}" class="group relative rounded-2xl overflow-hidden border border-[#202820]/10 bg-[#FAF8F2] shadow-sm hover:shadow-md transition-all">
                            <div class="aspect-square w-full overflow-hidden">
                                <img src="{{ asset('images/rabbits-category.jpg') }}" alt="Rabbits category" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                            </div>
                            <div class="p-3.5 text-center">
                                <h3 class="font-bold text-[#173F32] group-hover:text-[#245C45] transition-colors">Rabbits</h3>
                                <p class="text-xs text-[#69736B] mt-0.5">Gentle house bunnies</p>
                            </div>
                        </a>

                        {{-- Fish --}}
                        <a href="{{ route('listings.index') }}" class="group relative rounded-2xl overflow-hidden border border-[#202820]/10 bg-[#FAF8F2] shadow-sm hover:shadow-md transition-all">
                            <div class="aspect-square w-full overflow-hidden">
                                <img src="https://images.unsplash.com/photo-1524704654690-b56c05c78a00?auto=format&fit=crop&w=600&q=80" alt="Fish category" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                            </div>
                            <div class="p-3.5 text-center">
                                <h3 class="font-bold text-[#173F32] group-hover:text-[#245C45] transition-colors">Fish</h3>
                                <p class="text-xs text-[#69736B] mt-0.5">Freshwater &amp; marine</p>
                            </div>
                        </a>

                        {{-- Other Pets --}}
                        <a href="{{ route('listings.index') }}" class="group relative rounded-2xl overflow-hidden border border-[#202820]/10 bg-[#FAF8F2] shadow-sm hover:shadow-md transition-all">
                            <div class="aspect-square w-full overflow-hidden">
                                <img src="https://images.unsplash.com/photo-1425082661705-1834bfd09dca?auto=format&fit=crop&w=600&q=80" alt="Other Pets category" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                            </div>
                            <div class="p-3.5 text-center">
                                <h3 class="font-bold text-[#173F32] group-hover:text-[#245C45] transition-colors">Other Pets</h3>
                                <p class="text-xs text-[#69736B] mt-0.5">Hamsters, guinea pigs &amp; more</p>
                            </div>
                        </a>
                    </div>
                @endif
            </div>
        </section>

        {{-- PET MARKETPLACE / DISCOVERY SECTION --}}
        @if($sectionMarketplace)
        <section id="find-pets" class="py-16 sm:py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
                    <div>
                        <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Pet Marketplace</span>
                        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#173F32] mt-1">Meet your future companion</h2>
                        <p class="text-[#69736B] mt-2">Explore active pet listings and rehoming opportunities verified by the PawHaven moderation team.</p>
                    </div>

                    <div class="text-sm text-[#69736B] font-medium flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#245C45]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span>All listings pre-approved by PawHaven administrators</span>
                    </div>
                </div>

                {{-- Search & Filter Interface --}}
                <div class="bg-white rounded-2xl p-4 sm:p-6 shadow-sm border border-[#202820]/10 mb-10">
                    <form method="GET" action="{{ route('listings.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {{-- Search Input --}}
                        <div>
                            <label for="filter-search" class="block text-xs font-semibold uppercase tracking-wider text-[#69736B] mb-1.5">Keyword</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    id="filter-search"
                                    name="q"
                                    placeholder="Search breed, name, or location..."
                                    class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] placeholder-[#69736B]/60 focus:outline-none focus:ring-2 focus:ring-[#245C45] focus:bg-white transition-all"
                                >
                                <svg class="w-4 h-4 text-[#69736B] absolute left-3 top-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>

                        {{-- Category Select --}}
                        <div>
                            <label for="filter-category" class="block text-xs font-semibold uppercase tracking-wider text-[#69736B] mb-1.5">Category</label>
                            <select
                                id="filter-category"
                                name="category"
                                class="w-full px-3 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:outline-none focus:ring-2 focus:ring-[#245C45] focus:bg-white transition-all"
                            >
                                <option value="">All Categories</option>
                                @if(isset($petCategories))
                                    @foreach($petCategories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        {{-- Listing Type Select --}}
                        <div>
                            <label for="filter-type" class="block text-xs font-semibold uppercase tracking-wider text-[#69736B] mb-1.5">Listing Type</label>
                            <select
                                id="filter-type"
                                name="type"
                                class="w-full px-3 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:outline-none focus:ring-2 focus:ring-[#245C45] focus:bg-white transition-all"
                            >
                                <option value="">All Types</option>
                                <option value="sale">For Sale (Commercial)</option>
                                <option value="adoption">Adoption &amp; Rescue (No Fee)</option>
                            </select>
                        </div>

                        {{-- Apply Button --}}
                        <div class="flex items-end">
                            <button
                                type="submit"
                                class="w-full py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#245C45]"
                            >
                                Search Marketplace
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Dynamic Database-Backed Listings or Useful Empty State --}}
                @if(isset($featuredListings) && $featuredListings->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($featuredListings as $listing)
                            <article class="bg-white rounded-2xl overflow-hidden border border-[#202820]/10 shadow-sm hover:shadow-md transition-all flex flex-col">
                                {{-- Listing Media & Status Badges --}}
                                <div class="relative aspect-video w-full bg-[#FAF8F2] overflow-hidden">
                                    @if($listing->images->isNotEmpty())
                                        <img src="{{ $listing->images->first()->url() }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-[#69736B]">
                                            <svg class="w-12 h-12 stroke-current" fill="none" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    @endif

                                    {{-- Availability Badge --}}
                                    <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-600 text-white shadow-sm">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                        Available
                                    </span>

                                    {{-- Listing Type Badge --}}
                                    <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-xs font-semibold bg-white/95 text-[#173F32] shadow-sm backdrop-blur-sm">
                                        {{ ucfirst($listing->listing_type->value ?? 'Listing') }}
                                    </span>
                                </div>

                                {{-- Listing Details --}}
                                <div class="p-5 flex-1 flex flex-col justify-between">
                                    <div>
                                        <div class="text-xs font-semibold uppercase text-[#245C45] tracking-wider mb-1">
                                            {{ $listing->petCategory->name ?? 'Pet' }}
                                            @if($listing->breed) • {{ $listing->breed->name }} @endif
                                        </div>
                                        <h3 class="text-lg font-bold text-[#173F32]">{{ $listing->title }}</h3>
                                        <p class="text-sm text-[#69736B] line-clamp-2 mt-2 leading-relaxed">{{ $listing->description }}</p>
                                    </div>

                                    <div class="mt-4 pt-4 border-t border-[#202820]/10 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-[11px] uppercase tracking-wider text-[#69736B] font-semibold block">Price / Rehoming</span>
                                                <span class="text-base font-bold text-[#173F32]">
                                                    @if($listing->price_minor)
                                                        ${{ number_format($listing->price_minor / 100, 2) }}
                                                    @else
                                                        <span class="text-[#245C45] font-semibold text-sm">Adoption / Free</span>
                                                    @endif
                                                </span>
                                            </div>

                                            @if($listing->location)
                                                <div class="text-right">
                                                    <span class="text-[11px] uppercase tracking-wider text-[#69736B] font-semibold block">Location</span>
                                                    <span class="text-xs text-[#202820] font-medium flex items-center justify-end gap-1">
                                                        <svg class="w-3.5 h-3.5 text-[#69736B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                        </svg>
                                                        {{ $listing->location }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Details and seller contact navigation --}}
                                        <a
                                            href="{{ route('listings.show', $listing) }}"
                                            class="w-full inline-flex items-center justify-center py-2.5 px-4 rounded-xl text-xs font-semibold text-[#245C45] bg-[#245C45]/10 hover:bg-[#245C45] hover:text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#245C45]"
                                        >
                                            <span>View Listing &amp; Contact Seller</span>
                                            <svg class="ml-1.5 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    {{-- Useful Empty State (Never fabricate listings or prices) --}}
                    <div class="mx-auto max-w-2xl text-center">
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a href="{{ route('listings.index') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full text-sm font-semibold text-[#173F32] bg-[#FAF8F2] border border-[#202820]/15 hover:bg-white transition-all">
                                Explore Marketplace
                            </a>
                            @auth
                                @if(auth()->user()->isSeller())
                                    <a href="{{ route('seller.dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full text-sm font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-all">
                                        Go to Seller Dashboard
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full text-sm font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-all">
                                        Go to Dashboard
                                    </a>
                                @endif
                            @else
                                @if(Route::has('register'))
                                    <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full text-sm font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-all">
                                        Create Customer Account
                                    </a>
                                @endif
                            @endauth
                        </div>
                    </div>
                @endif
            </div>
        </section>
        @endif

        {{-- PET SHOP (SINGLE-SHOP E-COMMERCE EXPERIENCE) --}}
        @if($sectionShop)
        <section id="pet-shop" class="py-16 sm:py-24 bg-white border-y border-[#202820]/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Official PawHaven Store</span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#173F32] mt-1">Everything your companion needs</h2>
                    <p class="text-[#69736B] mt-3">A dedicated single-shop catalog managed directly by PawHaven. Premium pet nutrition, enrichment toys, and durable accessories curated with care.</p>
                </div>

                {{-- Retained Category Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-16">
                    {{-- 1. Food --}}
                    <div class="rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 p-6 flex flex-col text-center shadow-sm hover:shadow-md transition-all">
                        <div class="w-12 h-12 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Food &amp; Treats</h3>
                        <p class="text-xs text-[#69736B] mt-2">Organic kibble, raw diets, hypoallergenic recipes, and dental chews.</p>
                    </div>

                    {{-- 2. Toys --}}
                    <div class="rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 p-6 flex flex-col text-center shadow-sm hover:shadow-md transition-all">
                        <div class="w-12 h-12 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Toys &amp; Enrichment</h3>
                        <p class="text-xs text-[#69736B] mt-2">Puzzle feeders, durable rubber toys, interactive wands, and scratchers.</p>
                    </div>

                    {{-- 3. Collars --}}
                    <div class="rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 p-6 flex flex-col text-center shadow-sm hover:shadow-md transition-all">
                        <div class="w-12 h-12 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Collars &amp; Leashes</h3>
                        <p class="text-xs text-[#69736B] mt-2">Ergonomic no-pull harnesses, reflective leashes, and custom ID tags.</p>
                    </div>

                    {{-- 4. Grooming --}}
                    <div class="rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 p-6 flex flex-col text-center shadow-sm hover:shadow-md transition-all">
                        <div class="w-12 h-12 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Grooming &amp; Care</h3>
                        <p class="text-xs text-[#69736B] mt-2">Natural oatmeal shampoos, deshedding brushes, ear cleaners, and balms.</p>
                    </div>

                    {{-- 5. Beds --}}
                    <div class="rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 p-6 flex flex-col text-center shadow-sm hover:shadow-md transition-all">
                        <div class="w-12 h-12 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Beds &amp; Carriers</h3>
                        <p class="text-xs text-[#69736B] mt-2">Orthopedic memory foam mattresses, airline-ready crates, and cozy blankets.</p>
                    </div>
                </div>

                {{-- Prominent Prepared Product Cards (Previewing real catalog items) --}}
                <div class="mb-12">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Featured Supplies</span>
                            <h3 class="text-2xl font-bold text-[#173F32] mt-0.5">PawHaven Official Products</h3>
                        </div>
                        <span class="text-xs text-[#69736B] hidden sm:block">Shipped directly by PawHaven</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        @forelse($featuredProducts as $product)
                            <article class="bg-[#FAF8F2] rounded-2xl overflow-hidden border border-[#202820]/10 shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
                                <div>
                                    <div class="relative aspect-square w-full overflow-hidden bg-white">
                                        <img
                                            src="{{ $product->image_path ?? 'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?auto=format&fit=crop&w=600&q=80' }}"
                                            alt="{{ $product->name }}"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        >
                                        @if($product->stock_quantity > 0)
                                            <span class="absolute top-3 left-3 inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                In Stock
                                            </span>
                                        @else
                                            <span class="absolute top-3 left-3 inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 border border-rose-200">
                                                Out of Stock
                                            </span>
                                        @endif
                                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/90 text-[#173F32]">
                                            {{ $product->category->name ?? 'Supplies' }}
                                        </span>
                                    </div>
                                    <div class="p-4">
                                        <h4 class="font-bold text-[#173F32] text-sm leading-snug">{{ $product->name }}</h4>
                                        <p class="text-xs text-[#69736B] mt-2 line-clamp-2 leading-relaxed">{{ $product->description }}</p>
                                    </div>
                                </div>
                                <div class="p-4 pt-0">
                                    <div class="flex items-baseline justify-between mb-3">
                                        <span class="text-lg font-bold text-[#173F32]">${{ number_format(($product->sale_price_minor ?? $product->price_minor) / 100, 2) }}</span>
                                        <span class="text-[11px] text-[#69736B]">Direct PawHaven Item</span>
                                    </div>
                                    @guest
                                        <a
                                            href="{{ route('login') }}"
                                            class="w-full inline-flex items-center justify-center py-2.5 px-3 rounded-xl text-xs font-semibold text-[#245C45] bg-[#245C45]/10 hover:bg-[#245C45] hover:text-white transition-colors"
                                            title="Log in or register as a Customer to submit a purchase request"
                                        >
                                            <span>Request to Buy (Log In Required)</span>
                                        </a>
                                    @else
                                        @if($product->stock_quantity > 0)
                                            <button
                                                type="button"
                                                @click="buyProduct = { id: {{ $product->id }}, name: '{{ addslashes($product->name) }}', price: '${{ number_format(($product->sale_price_minor ?? $product->price_minor) / 100, 2) }}' }; buyModalOpen = true;"
                                                class="w-full inline-flex items-center justify-center py-2.5 px-3 rounded-xl text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-xs transition-colors"
                                            >
                                                <span>Request to Buy &rarr;</span>
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                disabled
                                                class="w-full py-2.5 px-3 rounded-xl text-xs font-semibold bg-zinc-100 text-zinc-400 cursor-not-allowed text-center"
                                            >
                                                Out of Stock
                                            </button>
                                        @endif
                                    @endguest
                                </div>
                            </article>
                        @empty
                            {{-- Curated Catalog Fallback (When no database products seeded) --}}
                            {{-- Product 1: Food --}}
                            <article class="bg-[#FAF8F2] rounded-2xl overflow-hidden border border-[#202820]/10 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="relative aspect-square w-full overflow-hidden bg-white">
                                        <img
                                            src="https://images.unsplash.com/photo-1589924691995-400dc9ecc119?auto=format&fit=crop&w=600&q=80"
                                            alt="PawHaven Heritage All-Natural Salmon &amp; Rice Dry Food"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        >
                                        <span class="absolute top-3 left-3 inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            In Stock
                                        </span>
                                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/90 text-[#173F32]">
                                            Food
                                        </span>
                                    </div>
                                    <div class="p-4">
                                        <h4 class="font-bold text-[#173F32] text-sm leading-snug">PawHaven Heritage All-Natural Salmon &amp; Rice Dry Food (12kg)</h4>
                                        <p class="text-xs text-[#69736B] mt-2 line-clamp-2 leading-relaxed">Hypoallergenic blend formulated with omega-rich wild salmon and brown rice for coat vitality and gentle digestion.</p>
                                    </div>
                                </div>
                                <div class="p-4 pt-0">
                                    <div class="flex items-baseline justify-between mb-3">
                                        <span class="text-lg font-bold text-[#173F32]">$64.99</span>
                                        <span class="text-[11px] text-[#69736B]">Direct PawHaven Item</span>
                                    </div>
                                    <button
                                        type="button"
                                        disabled
                                        class="w-full py-2 px-3 rounded-xl text-xs font-semibold bg-white text-[#69736B] border border-[#202820]/15 cursor-not-allowed text-center"
                                        title="Shopping cart and checkout available in upcoming e-commerce milestone"
                                    >
                                        Upcoming E-Commerce Milestone
                                    </button>
                                </div>
                            </article>

                            {{-- Product 2: Harness --}}
                            <article class="bg-[#FAF8F2] rounded-2xl overflow-hidden border border-[#202820]/10 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="relative aspect-square w-full overflow-hidden bg-white">
                                        <img
                                            src="https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&w=600&q=80"
                                            alt="PawHaven All-Weather Ergonomic No-Pull Harness"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        >
                                        <span class="absolute top-3 left-3 inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            In Stock
                                        </span>
                                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/90 text-[#173F32]">
                                            Gear
                                        </span>
                                    </div>
                                    <div class="p-4">
                                        <h4 class="font-bold text-[#173F32] text-sm leading-snug">PawHaven All-Weather Ergonomic No-Pull Harness</h4>
                                        <p class="text-xs text-[#69736B] mt-2 line-clamp-2 leading-relaxed">Dual alloy leash attachment rings, breathable air-mesh chest padding, and 3M reflective safety stitching for walks.</p>
                                    </div>
                                </div>
                                <div class="p-4 pt-0">
                                    <div class="flex items-baseline justify-between mb-3">
                                        <span class="text-lg font-bold text-[#173F32]">$34.50</span>
                                        <span class="text-[11px] text-[#69736B]">Direct PawHaven Item</span>
                                    </div>
                                    <button
                                        type="button"
                                        disabled
                                        class="w-full py-2 px-3 rounded-xl text-xs font-semibold bg-white text-[#69736B] border border-[#202820]/15 cursor-not-allowed text-center"
                                        title="Shopping cart and checkout available in upcoming e-commerce milestone"
                                    >
                                        Upcoming E-Commerce Milestone
                                    </button>
                                </div>
                            </article>

                            {{-- Product 3: Bed --}}
                            <article class="bg-[#FAF8F2] rounded-2xl overflow-hidden border border-[#202820]/10 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="relative aspect-square w-full overflow-hidden bg-white">
                                        <img
                                            src="https://images.unsplash.com/photo-1541781774459-bb2af2f05b55?auto=format&fit=crop&w=600&q=80"
                                            alt="PawHaven Orthopedic Memory Foam Calming Bed"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        >
                                        <span class="absolute top-3 left-3 inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            In Stock
                                        </span>
                                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/90 text-[#173F32]">
                                            Beds
                                        </span>
                                    </div>
                                    <div class="p-4">
                                        <h4 class="font-bold text-[#173F32] text-sm leading-snug">PawHaven Orthopedic Memory Foam Calming Bed</h4>
                                        <p class="text-xs text-[#69736B] mt-2 line-clamp-2 leading-relaxed">High-density orthopedic joint relief foam with waterproof internal casing and removable plush washable cover.</p>
                                    </div>
                                </div>
                                <div class="p-4 pt-0">
                                    <div class="flex items-baseline justify-between mb-3">
                                        <span class="text-lg font-bold text-[#173F32]">$79.00</span>
                                        <span class="text-[11px] text-[#69736B]">Direct PawHaven Item</span>
                                    </div>
                                    <button
                                        type="button"
                                        disabled
                                        class="w-full py-2 px-3 rounded-xl text-xs font-semibold bg-white text-[#69736B] border border-[#202820]/15 cursor-not-allowed text-center"
                                        title="Shopping cart and checkout available in upcoming e-commerce milestone"
                                    >
                                        Upcoming E-Commerce Milestone
                                    </button>
                                </div>
                            </article>

                            {{-- Product 4: Treats --}}
                            <article class="bg-[#FAF8F2] rounded-2xl overflow-hidden border border-[#202820]/10 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="relative aspect-square w-full overflow-hidden bg-white">
                                        <img
                                            src="https://images.unsplash.com/photo-1576201836106-db1758fd1c97?auto=format&fit=crop&w=600&q=80"
                                            alt="PawHaven Natural Sea Kelp Dental Chew Sticks"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        >
                                        <span class="absolute top-3 left-3 inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            In Stock
                                        </span>
                                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/90 text-[#173F32]">
                                            Dental
                                        </span>
                                    </div>
                                    <div class="p-4">
                                        <h4 class="font-bold text-[#173F32] text-sm leading-snug">PawHaven Natural Sea Kelp Dental Chew Sticks (30-Pack)</h4>
                                        <p class="text-xs text-[#69736B] mt-2 line-clamp-2 leading-relaxed">Organic sea kelp and mint formulation combating plaque buildup and keeping breath clean naturally.</p>
                                    </div>
                                </div>
                                <div class="p-4 pt-0">
                                    <div class="flex items-baseline justify-between mb-3">
                                        <span class="text-lg font-bold text-[#173F32]">$18.99</span>
                                        <span class="text-[11px] text-[#69736B]">Direct PawHaven Item</span>
                                    </div>
                                    <button
                                        type="button"
                                        disabled
                                        class="w-full py-2 px-3 rounded-xl text-xs font-semibold bg-white text-[#69736B] border border-[#202820]/15 cursor-not-allowed text-center"
                                        title="Shopping cart and checkout available in upcoming e-commerce milestone"
                                    >
                                        Upcoming E-Commerce Milestone
                                    </button>
                                </div>
                            </article>
                        @endforelse
                    </div>
                </div>

                {{-- Single-Shop Governance & Future Milestone Notice --}}
                <div class="bg-[#FAF8F2] rounded-2xl border border-[#245C45]/20 p-6 sm:p-8 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center shrink-0 mt-1">
                            <svg class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-[#173F32]">Centrally Managed PawHaven Store</h4>
                            <p class="text-xs sm:text-sm text-[#69736B] mt-1 leading-relaxed">
                                PawHaven is the sole provider and retailer for pet products. We do not support third-party vendor stores or marketplace commissions in our shop.
                            </p>
                            <p class="text-xs text-[#245C45] font-semibold mt-2">
                                Digital cart, checkout, Cash on Delivery (COD), and order management will launch in our dedicated e-commerce development milestone.
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0 w-full md:w-auto">
                        <a href="#how-it-works" class="w-full md:w-auto inline-flex items-center justify-center px-6 py-3 rounded-full text-xs font-semibold text-[#173F32] bg-white border border-[#202820]/15 hover:bg-[#FAF8F2] transition-colors">
                            Learn How Store Operates
                        </a>
                    </div>
                </div>
            </div>
        </section>
        @endif

        {{-- ADOPTION & RESCUE COMMUNITY (DISTINCT MAJOR SECTION) --}}
        @if($sectionAdoption)
        <section id="adoption-rescue" class="py-16 sm:py-24 bg-[#173F32] text-white relative overflow-hidden">
            {{-- Community Anchor for Navigation --}}
            <div id="community" class="scroll-mt-24"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    <div class="lg:col-span-7 space-y-6">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 text-[#F28C75] text-xs sm:text-sm font-semibold">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                            </svg>
                            <span>Adoption &amp; Rescue Mission</span>
                        </div>

                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight leading-tight">
                            Every pet deserves a safe place to call home.
                        </h2>

                        <p class="text-white/80 text-base sm:text-lg leading-relaxed">
                            PawHaven unites local rescue centers, foster parents, and families facing unavoidable transitions. We believe adoption should be compassionate, transparent, and focused entirely on animal welfare.
                        </p>

                        {{-- Entry points for adoption, rescue, lost and found, and rehoming --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            {{-- Entry 1: Direct Shelter & Rescue Adoption --}}
                            <div class="bg-white/5 border border-white/10 rounded-2xl p-5 hover:bg-white/10 transition-colors">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-lg bg-[#F28C75]/20 text-[#F28C75] flex items-center justify-center font-bold text-sm">1</div>
                                    <h3 class="font-bold text-white text-sm">Shelter &amp; Rescue Network</h3>
                                </div>
                                <p class="text-xs text-white/70 leading-relaxed">Direct visibility for verified shelters without commercial markups or broker fees.</p>
                            </div>

                            {{-- Entry 2: Gentle Rehoming --}}
                            <div class="bg-white/5 border border-white/10 rounded-2xl p-5 hover:bg-white/10 transition-colors">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-lg bg-[#F28C75]/20 text-[#F28C75] flex items-center justify-center font-bold text-sm">2</div>
                                    <h3 class="font-bold text-white text-sm">Gentle Rehoming Service</h3>
                                </div>
                                <p class="text-xs text-white/70 leading-relaxed">A compassionate alternative when life circumstances change, keeping pets out of cages.</p>
                            </div>

                            {{-- Entry 3: Lost & Found --}}
                            <div class="bg-white/5 border border-white/10 rounded-2xl p-5 hover:bg-white/10 transition-colors">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-lg bg-[#F28C75]/20 text-[#F28C75] flex items-center justify-center font-bold text-sm">3</div>
                                    <h3 class="font-bold text-white text-sm">Lost &amp; Found Community</h3>
                                </div>
                                <p class="text-xs text-white/70 leading-relaxed">A real-time neighborhood network helping reunite missing animals with worried owners.</p>
                            </div>

                            {{-- Entry 4: Foster Volunteer Network --}}
                            <div class="bg-white/5 border border-white/10 rounded-2xl p-5 hover:bg-white/10 transition-colors">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-lg bg-[#F28C75]/20 text-[#F28C75] flex items-center justify-center font-bold text-sm">4</div>
                                    <h3 class="font-bold text-white text-sm">Foster &amp; Volunteer Network</h3>
                                </div>
                                <p class="text-xs text-white/70 leading-relaxed">Open your heart and home temporarily to support injured or recovering rescue animals.</p>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="pt-4 flex flex-col sm:flex-row gap-4">
                            @if(Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-3.5 rounded-full text-sm font-semibold text-[#173F32] bg-white hover:bg-[#FAF8F2] shadow-sm transition-all">
                                    Join the Adoption Community
                                </a>
                            @endif
                            <a href="#how-it-works" class="inline-flex items-center justify-center px-8 py-3.5 rounded-full text-sm font-semibold text-white border border-white/30 hover:border-white transition-all">
                                Read Adoption Guidelines
                            </a>
                        </div>
                    </div>

                    <div class="lg:col-span-5">
                        <div class="relative overflow-hidden rounded-3xl border-4 border-white/20 shadow-2xl">
                            <img
                                src="https://images.unsplash.com/photo-1548767797-d8c844163c4c?auto=format&fit=crop&w=1000&q=80"
                                alt="Caring moment with rescued pet"
                                class="w-full h-[440px] object-cover"
                                loading="lazy"
                            >
                        </div>
                    </div>
                </div>

                {{-- Dynamic Adoption Listings Showcase --}}
                @if(isset($featuredAdoptions) && $featuredAdoptions->isNotEmpty())
                    <div class="mt-16 pt-12 border-t border-white/10">
                        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 gap-4">
                            <div>
                                <span class="text-xs uppercase tracking-widest font-bold text-[#F28C75]">Current Companions Waiting For Love</span>
                                <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-white mt-1">Ready for Adoption &amp; Foster Care</h3>
                            </div>
                            <a href="{{ route('listings.index', ['type' => 'adoption']) }}" class="text-xs font-semibold text-[#F28C75] hover:underline flex items-center gap-1">
                                <span>Browse All Rescue Posts</span>
                                <span>&rarr;</span>
                            </a>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            @foreach($featuredAdoptions as $adopt)
                                <article class="bg-white/10 backdrop-blur-md rounded-2xl overflow-hidden border border-white/15 shadow-sm hover:shadow-md transition-all flex flex-col text-white">
                                    <div class="relative aspect-video w-full bg-black/20 overflow-hidden">
                                        @if($adopt->images->isNotEmpty())
                                            <img src="{{ $adopt->images->first()->url() }}" alt="{{ $adopt->title }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-xs text-white/50">
                                                Photo pending
                                            </div>
                                        @endif
                                        <div class="absolute top-2.5 left-2.5">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[#F28C75] text-[#173F32] shadow-xs">
                                                Adoption Companion
                                            </span>
                                        </div>
                                    </div>
                                    <div class="p-5 flex-1 flex flex-col justify-between">
                                        <div>
                                            <div class="text-xs text-white/70 mb-1">
                                                {{ $adopt->petCategory?->name ?? 'Rescue' }} @if($adopt->breed) • {{ $adopt->breed->name }} @endif
                                            </div>
                                            <h4 class="text-lg font-bold text-white line-clamp-1">
                                                <a href="{{ route('listings.show', $adopt) }}">{{ $adopt->title }}</a>
                                            </h4>
                                            <p class="text-xs text-white/80 line-clamp-2 mt-2 leading-relaxed">{{ $adopt->description }}</p>
                                        </div>
                                        <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between">
                                            <span class="text-xs font-semibold text-white/90">
                                                {{ $adopt->location ?? 'Shelter / Foster' }}
                                            </span>
                                            <a
                                                href="{{ route('listings.show', $adopt) }}"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-[#173F32] bg-white hover:bg-[#FAF8F2] transition-colors"
                                            >
                                                <span>Apply to Adopt</span>
                                                <span>&rarr;</span>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
        @endif

        {{-- BREED ENCYCLOPEDIA (SUPPORTING TEASER FEATURE) --}}
        @if($sectionBreeds)
        <section id="breeds" class="py-16 sm:py-24 bg-white border-b border-[#202820]/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Curated Breed Spotlights • Encyclopedia Preview</span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#173F32] mt-1">Get to know your perfect match</h2>
                    <p class="text-[#69736B] mt-3">Learn about exercise needs, temperament, grooming, and lifespan before welcoming a new companion into your home. These curated editorial previews highlight popular breeds.</p>
                </div>

                @if(isset($featuredBreeds) && $featuredBreeds->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        @foreach($featuredBreeds as $b)
                            <div class="rounded-2xl border border-[#202820]/10 bg-[#FAF8F2] p-5 flex flex-col justify-between shadow-xs hover:shadow-md transition-all">
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#245C45]">
                                        {{ $b->petCategory?->name ?? 'Companion' }}
                                    </span>
                                    <h3 class="text-lg font-bold text-[#173F32] mt-1">{{ $b->name }}</h3>
                                    <p class="text-xs text-[#69736B] mt-2 leading-relaxed">
                                        {{ $b->description ?? 'Discover breed traits, temperament, and care guidelines.' }}
                                    </p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-[#202820]/10 flex items-center justify-between text-xs">
                                    <a href="{{ route('listings.index', ['breed' => $b->id]) }}" class="font-semibold text-[#245C45] hover:underline flex items-center gap-1">
                                        <span>Find {{ $b->name }} Listings</span>
                                        <span>&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        {{-- Golden Retriever --}}
                        <div class="rounded-2xl border border-[#202820]/10 bg-[#FAF8F2] overflow-hidden flex flex-col">
                            <img src="https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=600&q=80" alt="Golden Retriever breed" class="h-44 w-full object-cover" loading="lazy">
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#245C45]">Dog • Active Family</span>
                                    <h3 class="text-lg font-bold text-[#173F32] mt-1">Golden Retriever</h3>
                                    <p class="text-xs text-[#69736B] mt-2 leading-relaxed">Friendly, intelligent, and devoted. Thrives in active households with outdoor exercise and companionship.</p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-[#202820]/10 flex items-center justify-between text-xs text-[#69736B]">
                                    <span>High Energy</span>
                                    <span>10-12 yrs</span>
                                </div>
                            </div>
                        </div>

                        {{-- British Shorthair --}}
                        <div class="rounded-2xl border border-[#202820]/10 bg-[#FAF8F2] overflow-hidden flex flex-col">
                            <img src="{{ asset('images/british-shorthair.jpg') }}" alt="British Shorthair breed" class="h-44 w-full object-cover" loading="lazy">
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#245C45]">Cat • Calm Living</span>
                                    <h3 class="text-lg font-bold text-[#173F32] mt-1">British Shorthair</h3>
                                    <p class="text-xs text-[#69736B] mt-2 leading-relaxed">Easygoing, patient, and quiet. Well-suited for apartments, gentle with children, and low-maintenance coat.</p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-[#202820]/10 flex items-center justify-between text-xs text-[#69736B]">
                                    <span>Moderate Energy</span>
                                    <span>12-16 yrs</span>
                                </div>
                            </div>
                        </div>

                        {{-- Holland Lop Rabbit --}}
                        <div class="rounded-2xl border border-[#202820]/10 bg-[#FAF8F2] overflow-hidden flex flex-col">
                            <img src="{{ asset('images/holland-lop.webp') }}" alt="Holland Lop breed" class="h-44 w-full object-cover" loading="lazy">
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#245C45]">Rabbit • Gentle Companion</span>
                                    <h3 class="text-lg font-bold text-[#173F32] mt-1">Holland Lop</h3>
                                    <p class="text-xs text-[#69736B] mt-2 leading-relaxed">Sweet-tempered and curious with distinctive lopped ears. Needs gentle handling and fresh timothy hay daily.</p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-[#202820]/10 flex items-center justify-between text-xs text-[#69736B]">
                                    <span>Quiet &amp; Social</span>
                                    <span>7-10 yrs</span>
                                </div>
                            </div>
                        </div>

                        {{-- Cockatiel --}}
                        <div class="rounded-2xl border border-[#202820]/10 bg-[#FAF8F2] overflow-hidden flex flex-col">
                            <img src="https://images.unsplash.com/photo-1549608276-5786777e6587?auto=format&fit=crop&w=600&q=80" alt="Cockatiel bird breed" class="h-44 w-full object-cover" loading="lazy">
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#245C45]">Bird • Vocal Friend</span>
                                    <h3 class="text-lg font-bold text-[#173F32] mt-1">Cockatiel</h3>
                                    <p class="text-xs text-[#69736B] mt-2 leading-relaxed">Playful, vocal, and affectionate flock bird. Enjoys whistling tunes and gentle out-of-cage companionship.</p>
                                </div>
                                <div class="mt-4 pt-3 border-t border-[#202820]/10 flex items-center justify-between text-xs text-[#69736B]">
                                    <span>Interactive</span>
                                    <span>15-20 yrs</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Notice Banner (Clarifies that demo spotlights are editorial previews) --}}
                <div class="mt-10 p-5 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 text-center text-sm text-[#69736B] max-w-2xl mx-auto">
                    <span class="font-semibold text-[#173F32]">Comprehensive database breed encyclopedia is scheduled for a future milestone.</span>
                    Upcoming updates will introduce database-backed breed guides, health screening checklists, and personalized pet compatibility matching.
                </div>
            </div>
        </section>
        @endif

        {{-- HOW IT WORKS --}}
        <section id="how-it-works" class="py-16 sm:py-24 bg-white border-b border-[#202820]/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Simple Journey</span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#173F32] mt-1">How PawHaven Works</h2>
                    <p class="text-[#69736B] mt-3">A transparent, three-step journey designed to ensure animal welfare and peace of mind.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                    {{-- Step 1 --}}
                    <div class="text-center p-6 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 relative">
                        <div class="w-14 h-14 rounded-full bg-[#245C45] text-white flex items-center justify-center text-xl font-bold mx-auto mb-5 shadow-sm">
                            1
                        </div>
                        <h3 class="text-xl font-bold text-[#173F32]">Discover</h3>
                        <p class="text-sm text-[#69736B] mt-3 leading-relaxed">
                            Search moderated listings and explore detailed breed profiles to understand exercise, temperament, and care requirements.
                        </p>
                    </div>

                    {{-- Step 2 --}}
                    <div class="text-center p-6 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 relative">
                        <div class="w-14 h-14 rounded-full bg-[#245C45] text-white flex items-center justify-center text-xl font-bold mx-auto mb-5 shadow-sm">
                            2
                        </div>
                        <h3 class="text-xl font-bold text-[#173F32]">Connect</h3>
                        <p class="text-sm text-[#69736B] mt-3 leading-relaxed">
                            Reach out to verified sellers, foster homes, or rescue organizations directly to ask questions and arrange responsible visits.
                        </p>
                    </div>

                    {{-- Step 3 --}}
                    <div class="text-center p-6 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 relative">
                        <div class="w-14 h-14 rounded-full bg-[#245C45] text-white flex items-center justify-center text-xl font-bold mx-auto mb-5 shadow-sm">
                            3
                        </div>
                        <h3 class="text-xl font-bold text-[#173F32]">Welcome Home</h3>
                        <p class="text-sm text-[#69736B] mt-3 leading-relaxed">
                            Finalize adoption or purchase agreements with confidence, and prepare your home with quality supplies from our curated store.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- TRUST & SAFETY --}}
        <section id="trust-safety" class="py-16 sm:py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <span class="text-xs uppercase tracking-widest font-bold text-[#245C45]">Integrity First</span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#173F32] mt-1">Built on Trust, Care &amp; Community</h2>
                    <p class="text-[#69736B] mt-3">We hold animal welfare as our highest priority, maintaining rigorous standards across all interactions.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Listing Moderation</h3>
                        <p class="text-xs text-[#69736B] mt-2 leading-relaxed">
                            Every pet listing is checked by our team before going public to protect against illegal trade, puppy mills, and fraudulent posts.
                        </p>
                    </div>

                    <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Role Accountability</h3>
                        <p class="text-xs text-[#69736B] mt-2 leading-relaxed">
                            Distinct customer, seller, and administrator accounts ensure clear ownership, verified contact channels, and traceable listings.
                        </p>
                    </div>

                    <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Data &amp; Record Integrity</h3>
                        <p class="text-xs text-[#69736B] mt-2 leading-relaxed">
                            Accounts with active listings cannot be removed without resolution, and moderation history is permanently retained for auditing.
                        </p>
                    </div>

                    <div class="bg-white rounded-2xl p-6 border border-[#202820]/10 shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-[#245C45]/10 text-[#245C45] flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 fill-none stroke-current" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-[#173F32] text-base">Animal Welfare Focus</h3>
                        <p class="text-xs text-[#69736B] mt-2 leading-relaxed">
                            Zero tolerance for misleading descriptions, animal distress, or unethical breeding practices. Violations result in immediate removal.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    {{-- FOOTER --}}
    <footer class="bg-[#173F32] text-white pt-16 pb-12 border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 pb-12 border-b border-white/10">
                {{-- Brand Info --}}
                <div class="lg:col-span-2 space-y-4">
                    <a href="{{ route('public.home') }}" class="flex items-center gap-3 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded-lg">
                        <img
                            src="{{ str_starts_with((string) Settings::get('brand.logo_dark_path', ''), 'http') ? Settings::get('brand.logo_dark_path') : asset(ltrim((string) Settings::get('brand.logo_dark_path', 'images/pawhaven-logo-white.png'), '/')) }}"
                            alt="{{ $brandName }} Logo"
                            height="48"
                            class="h-12 w-auto max-h-12 max-w-[200px] shrink-0 object-contain transition-transform group-hover:scale-105"
                            style="height: 48px !important; max-height: 48px !important; width: auto !important; max-width: 200px !important; object-fit: contain !important;"
                        >
                        <div>
                            <span class="text-2xl font-bold tracking-tight text-white block leading-none">{{ $brandName }}</span>
                            <span class="text-[11px] tracking-wider uppercase font-semibold text-white/60 block mt-1">Pet Marketplace &amp; Adoption</span>
                        </div>
                    </a>
                    <p class="text-sm text-white/70 max-w-sm leading-relaxed">
                        Find Pets. Give Hope. Shop with Love. PawHaven is an ethical platform connecting pet owners, adoption partners, and prospective families.
                    </p>
                    <div class="text-xs text-white/50">
                        Ethical Pet Marketplace &amp; Adoption Community
                    </div>
                </div>

                {{-- Platform Links --}}
                <div>
                    <h4 class="text-xs uppercase tracking-wider font-bold text-white/50 mb-4">Platform</h4>
                    <ul class="space-y-2.5 text-sm text-white/80">
                        <li><a href="{{ route('public.home') }}" class="hover:text-white transition-colors">Home</a></li>
                        <li><a href="{{ route('listings.index') }}" class="hover:text-white transition-colors">Find Pets</a></li>
                        <li><a href="#pet-shop" class="hover:text-white transition-colors">Pet Shop</a></li>
                        <li><a href="#categories" class="hover:text-white transition-colors">Pet Categories</a></li>
                        <li><a href="#breeds" class="hover:text-white transition-colors">Breed Directory</a></li>
                    </ul>
                </div>

                {{-- Adoption & Community --}}
                <div>
                    <h4 class="text-xs uppercase tracking-wider font-bold text-white/50 mb-4">Community</h4>
                    <ul class="space-y-2.5 text-sm text-white/80">
                        <li><a href="{{ route('listings.index', ['type' => 'adoption']) }}" class="hover:text-white transition-colors">Adoption &amp; Rescue</a></li>
                        <li><a href="#community" class="hover:text-white transition-colors">Community Network</a></li>
                        <li><a href="#how-it-works" class="hover:text-white transition-colors">How It Works</a></li>
                        <li><a href="#trust-safety" class="hover:text-white transition-colors">Trust &amp; Safety</a></li>
                    </ul>
                </div>

                {{-- Account Links --}}
                <div>
                    <h4 class="text-xs uppercase tracking-wider font-bold text-white/50 mb-4">Account</h4>
                    <ul class="space-y-2.5 text-sm text-white/80">
                        @auth
                            @if(auth()->user()->isSeller())
                                <li><a href="{{ route('seller.dashboard') }}" class="hover:text-white transition-colors">Seller Dashboard</a></li>
                            @elseif(! auth()->user()->isAdmin())
                                <li><a href="{{ route('dashboard') }}" class="hover:text-white transition-colors">Customer Dashboard</a></li>
                            @endif
                            <li><a href="{{ route('profile.edit') }}" class="hover:text-white transition-colors">Profile Settings</a></li>
                            <li><a href="{{ route('security.edit') }}" class="hover:text-white transition-colors">Security &amp; Password</a></li>
                        @else
                            @if(Route::has('login'))
                                <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Log In</a></li>
                            @endif
                            @if(Route::has('register'))
                                <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Create Account</a></li>
                            @endif
                        @endauth
                    </ul>
                </div>
            </div>

            {{-- Bottom Legal & Dynamic Year --}}
            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-white/60 gap-4">
                <p>&copy; {{ date('Y') }} PawHaven. All rights reserved.</p>
                <p class="text-center sm:text-right">Dedicated to ethical pet parenthood and lifelong companion care.</p>
            </div>
        </div>
    </footer>

    {{-- Buy Request Modal for Authenticated Customers --}}
    @auth
    <div
        x-show="buyModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
    >
        <div
            @click.away="buyModalOpen = false"
            class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-[#202820]/10 space-y-6"
        >
            <div class="flex items-center justify-between pb-4 border-b border-[#202820]/10">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#245C45]">Direct Shop Order</span>
                    <h3 class="text-xl font-bold text-[#173F32] mt-0.5">Submit Buy Request</h3>
                </div>
                <button
                    type="button"
                    @click="buyModalOpen = false"
                    class="p-2 text-zinc-400 hover:text-zinc-600 rounded-lg hover:bg-zinc-100"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('orders.request') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="product_id" :value="buyProduct.id">

                {{-- Product Summary Card --}}
                <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-[#173F32] block" x-text="buyProduct.name"></span>
                        <span class="text-[11px] text-[#69736B]">Payment: Cash on Delivery (COD)</span>
                    </div>
                    <span class="text-base font-bold text-[#245C45]" x-text="buyProduct.price"></span>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-1.5">Quantity</label>
                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        max="10"
                        value="1"
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                        required
                    >
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-1.5">Contact Phone</label>
                    <input
                        type="tel"
                        name="customer_phone"
                        placeholder="Include country calling code"
                        class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                        required
                    >
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-1.5">Shipping Address</label>
                        <input
                            type="text"
                            name="shipping_address"
                            placeholder="Street address & Apt"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                            required
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-1.5">City</label>
                        <input
                            type="text"
                            name="shipping_city"
                            placeholder="City / Region"
                            class="w-full px-4 py-2.5 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                            required
                        >
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-[#69736B] mb-1.5">Delivery Notes (Optional)</label>
                    <textarea
                        name="notes"
                        rows="2"
                        placeholder="Special delivery instructions..."
                        class="w-full px-4 py-2 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm text-[#202820] focus:ring-2 focus:ring-[#245C45] focus:bg-white focus:outline-none"
                    ></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="buyModalOpen = false"
                        class="px-5 py-2.5 rounded-full text-xs font-semibold text-[#69736B] hover:text-[#202820] border border-[#202820]/15 hover:bg-[#FAF8F2]"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-6 py-2.5 rounded-full text-xs font-semibold text-white bg-[#245C45] hover:bg-[#173F32] shadow-sm transition-all"
                    >
                        Confirm Buy Request &rarr;
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endauth

    @fluxScripts
</body>
</html>
