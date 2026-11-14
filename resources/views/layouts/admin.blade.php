@props([
    'title' => null,
    'breadcrumbs' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#FAF8F2]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? 'Admin Dashboard') — {{ \App\Facades\Settings::get('brand.name', 'PawHaven') }} Admin</title>

    @include('partials.favicon')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-[#202820] bg-[#FAF8F2] flex" x-data="{ sidebarOpen: false }">

    {{-- Mobile Sidebar Backdrop --}}
    <div
        x-show="sidebarOpen"
        x-cloak
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-xs lg:hidden transition-opacity"
    ></div>

    {{-- Administrative Sidebar --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[#173F32] text-white transition-transform duration-300 ease-in-out lg:static lg:flex shrink-0 shadow-xl lg:shadow-none"
    >
        {{-- Brand Bar --}}
        <div class="flex h-20 items-center justify-between px-6 border-b border-white/10">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group focus-visible:outline-none">
                <img
                    src="{{ str_starts_with((string) \App\Facades\Settings::get('brand.logo_dark_path', ''), 'http') ? \App\Facades\Settings::get('brand.logo_dark_path') : asset(ltrim((string) \App\Facades\Settings::get('brand.logo_dark_path', 'images/pawhaven-logo-white.png'), '/')) }}"
                    alt="{{ \App\Facades\Settings::get('brand.name', 'PawHaven') }} Logo"
                    class="h-10 w-auto max-h-10 object-contain transition-transform group-hover:scale-105"
                    style="height: 40px; width: auto; max-height: 40px;"
                >
                <div>
                    <span class="text-lg font-bold tracking-tight text-white block leading-none">{{ \App\Facades\Settings::get('brand.name', 'PawHaven') }}</span>
                    <span class="text-[10px] tracking-wider uppercase font-semibold text-white/60 block mt-1">Admin Portal</span>
                </div>
            </a>

            {{-- Close mobile button --}}
            <button
                type="button"
                @click="sidebarOpen = false"
                class="lg:hidden p-1.5 rounded-lg text-white/70 hover:text-white hover:bg-white/10"
                aria-label="Close sidebar"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Scrollable Navigation --}}
        <div class="flex flex-1 flex-col overflow-y-auto px-4 py-6 scrollbar-thin scrollbar-thumb-white/20">
            @include('layouts.admin.nav')
        </div>

        {{-- Logged In User Footer --}}
        <div class="p-4 border-t border-white/10 bg-black/10 flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-full bg-[#245C45] text-white font-bold flex items-center justify-center text-xs shrink-0 border border-white/20">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="truncate">
                    <span class="text-xs font-semibold text-white block truncate">{{ auth()->user()->name ?? 'Staff' }}</span>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-[#F28C75] block">{{ ucfirst(auth()->user()->role ?? 'admin') }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button
                    type="submit"
                    class="p-2 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                    title="Log Out"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Column Area --}}
    <div class="flex flex-1 flex-col min-w-0 overflow-hidden">
        {{-- Top Application Header --}}
        <header class="h-20 bg-white border-b border-[#202820]/10 flex items-center justify-between px-4 sm:px-8 shrink-0">
            <div class="flex items-center gap-4">
                {{-- Hamburger Mobile Button --}}
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="lg:hidden p-2 rounded-lg text-[#202820] hover:bg-[#202820]/5 focus-visible:outline-none"
                    aria-label="Open sidebar"
                >
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                {{-- Breadcrumbs / Page Title --}}
                <div>
                    @if (isset($breadcrumbs) || View::hasSection('breadcrumbs'))
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-[#69736B]">
                            {{ $breadcrumbs ?? '' }}@yield('breadcrumbs')
                        </div>
                    @endif
                    <h1 class="text-base sm:text-lg font-bold tracking-tight text-[#173F32]">@yield('title', $title ?? 'Admin Dashboard')</h1>
                </div>
            </div>

            {{-- Right Controls --}}
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('public.home') }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-[#245C45] bg-[#245C45]/10 hover:bg-[#245C45]/20 transition-colors"
                >
                    <span>View Public Store</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            </div>
        </header>

        {{-- Flash Alerts --}}
        @if (session('success'))
            <div class="px-4 sm:px-8 pt-6">
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs sm:text-sm font-medium text-emerald-900 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="px-4 sm:px-8 pt-6">
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs sm:text-sm font-medium text-rose-900 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Main Page Body --}}
        <main class="flex-1 overflow-y-auto p-4 sm:p-8">
            {!! $slot ?? '' !!}
            @yield('content')
        </main>
    </div>

</body>
</html>
