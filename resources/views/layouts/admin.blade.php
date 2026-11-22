@props([
    'title' => null,
    'breadcrumbs' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? 'Admin Dashboard') — {{ \App\Facades\Settings::get('brand.name', 'PawHaven') }} Admin</title>

    @include('partials.favicon')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { margin: 0; font-family: Inter, system-ui, -apple-system, sans-serif; color: #1B2420; background: #F4F6F5; }
        a { color: #17603F; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .nav a { display: flex; align-items: center; gap: 11px; padding: 0 12px; min-height: 40px; border-radius: 8px; color: #C3D4CB; font-size: 13.5px; font-weight: 500; margin-bottom: 2px; position: relative; }
        .nav a svg { flex: none; color: #8FB09F; }
        .nav a:hover { background: rgba(255,255,255,.07); color: #fff; text-decoration: none; }
        .nav a:hover svg { color: #D5E3DB; }
        .nav a.on { background: rgba(255,255,255,.12); color: #fff; font-weight: 600; }
        .nav a.on svg { color: #4ADE80; }
        .nav a.on::before { content: ""; position: absolute; left: -12px; top: 8px; bottom: 8px; width: 3px; border-radius: 0 3px 3px 0; background: #4ADE80; }
        .grp { font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: #7E9E8D; margin: 18px 12px 6px; font-weight: 600; }
        .card { background: #fff; border: 1px solid #E3E7E4; border-radius: 10px; box-shadow: 0 1px 2px rgba(16,24,20,.04); }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .top { display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px; }
        .grp-btn { display: flex; align-items: center; gap: 11px; width: 100%; min-height: 42px; padding: 0 12px; border: 0; border-radius: 8px; background: transparent; color: #C3D4CB; font: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; margin-bottom: 2px; text-align: left; }
        .grp-btn svg:first-child { color: #8FB09F; }
        .grp-btn:hover { background: rgba(255,255,255,.07); color: #fff; }
        .grp-btn[aria-expanded="true"] { background: rgba(255,255,255,.07); color: #fff; }
        .grp-btn[aria-expanded="true"] svg:first-child { color: #4ADE80; }
        .sub { margin: 2px 0 8px 22px; padding-left: 10px; border-left: 1px solid rgba(255,255,255,.14); }
        .sub a { min-height: 36px; font-size: 13px; font-weight: 500; padding: 0 12px; }
        .sub a:hover { background: rgba(255,255,255,.07); }
        .shell { display: flex; min-height: 100vh; background: #F4F6F5; }
        .btn { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 18px; border-radius: 8px; font-size: 14px; font-weight: 600; box-sizing: border-box; text-decoration: none; cursor: pointer; }
        .btn:hover { text-decoration: none; opacity: .95; }
        @media(max-width:1000px) { .kpis { grid-template-columns: 1fr 1fr; } .row2, .top { grid-template-columns: 1fr; } }
        @media(max-width:760px) { .shell { flex-direction: column; } .side { width: auto !important; height: auto !important; position: static !important; } .kpis { grid-template-columns: 1fr; } }
        .sub[hidden] { display: none; }
        .chev { margin-left: auto; flex: none; transition: transform .18s ease; }
        .grp-btn[aria-expanded="true"] .chev { transform: rotate(180deg); }
        * { box-sizing: border-box; }

        /* Shared Design System Form & Control Styles */
        .f label, .lbl { display: block; font-size: 12px; font-weight: 600; color: #3B4540; margin-bottom: 6px; }
        .inp { width: 100%; min-height: 44px; padding: 0 12px; border: 1px solid #D3D9D5; border-radius: 8px; background: #fff; font: inherit; font-size: 14px; color: #101814; box-sizing: border-box; }
        textarea.inp { padding: 10px 12px; min-height: 96px; resize: vertical; line-height: 1.5; }
        .inp:focus, .hex:focus { outline: 2px solid #17603F; outline-offset: 1px; border-color: #17603F; }
        .inp[aria-invalid="true"] { border-color: #B42318; }
        select.inp.sel, .sel { appearance: none; background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%235B6460' stroke-width='2' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center; padding-right: 36px; }
        .err { color: #B42318; font-size: 12px; margin-top: 6px; min-height: 16px; }
        .hint { font-size: 12px; color: #5B6460; margin-top: 6px; }
        .chip { display: inline-flex; align-items: center; min-height: 36px; padding: 0 12px; border: 1px solid #D3D9D5; border-radius: 999px; background: #fff; font: inherit; font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; }
        .chip:hover { background: #EEF5F0; border-color: #17603F; text-decoration: none; }
        .toast { position: fixed; right: 24px; top: 20px; background: #12392A; color: #fff; padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; opacity: 0; transform: translateY(-8px); transition: .2s; pointer-events: none; z-index: 100; }
        .toast.show { opacity: 1; transform: none; }
    </style>
</head>
<body class="min-h-full font-sans antialiased text-[#1B2420] bg-[#F4F6F5]" x-data="{ sidebarOpen: false }">

    {{-- Mobile Sidebar Backdrop --}}
    <div
        x-show="sidebarOpen"
        x-cloak
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-black/60 lg:hidden transition-opacity"
    ></div>

    <div class="shell">
        {{-- Administrative Sidebar --}}
        <aside
            class="side"
            style="width:260px;flex:none;background:#0F2A20;color:#fff;box-sizing:border-box;position:sticky;top:0;height:100vh;display:flex;flex-direction:column;z-index:50"
        >
            {{-- Brand Bar --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.1)">
                <a href="{{ route('admin.dashboard') }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:#fff">
                    <div style="width:34px;height:34px;border-radius:8px;background:#fff;color:#0F2A20;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;flex-shrink:0">
                        {{ strtoupper(substr(\App\Facades\Settings::get('brand.name', 'PawHaven'), 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:15px;letter-spacing:-.01em;color:#fff;line-height:1.2">{{ \App\Facades\Settings::get('brand.name', 'PawHaven') }}</div>
                        <div style="font-size:11px;letter-spacing:.08em;color:#9DB8AA;line-height:1.2">ADMIN PORTAL</div>
                    </div>
                </a>
                <button
                    type="button"
                    @click="sidebarOpen = false"
                    class="lg:hidden"
                    style="border:0;background:transparent;color:#C3D4CB;cursor:pointer;padding:4px"
                    aria-label="Close sidebar"
                >
                    <svg style="width:20px;height:20px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Scrollable Navigation --}}
            <nav class="nav" style="flex:1;overflow-y:auto;padding:12px">
                @include('layouts.admin.nav')
            </nav>

            {{-- Logged In User Footer --}}
            <div style="display:flex;align-items:center;gap:10px;padding:14px 16px;border-top:1px solid rgba(255,255,255,.1);background:rgba(0,0,0,.14)">
                <div style="width:34px;height:34px;border-radius:50%;background:#1F6B4A;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:13px;color:#fff;flex-shrink:0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'P', 0, 1)) }}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:600;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ auth()->user()->name ?? 'PawHaven Admin' }}</div>
                    <div style="font-size:11px;color:#9DB8AA;letter-spacing:.06em">{{ strtoupper(auth()->user()->role ?? 'ADMINISTRATOR') }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}" style="margin:0;flex-shrink:0">
                    @csrf
                    <button
                        type="submit"
                        aria-label="Sign out"
                        title="Sign out"
                        style="width:44px;height:44px;border:0;border-radius:8px;background:transparent;color:#C3D4CB;cursor:pointer;display:flex;align-items:center;justify-content:center"
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5V3h4M16 17l5-5-5-5M21 12H9"/>
                        </svg>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main Column Area --}}
        <div style="flex:1;min-width:0;display:flex;flex-direction:column">
            {{-- Mobile header for self-headered pages --}}
            @if(request()->routeIs('admin.dashboard', 'admin.brand.*', 'admin.marketplace.categories.*', 'admin.homepage.*', 'admin.marketplace.listings.*', 'admin.marketplace.moderation.*', 'admin.business.*', 'admin.breeds.*', 'admin.community.*'))
                <div class="lg:hidden flex items-center justify-between px-4 py-3 bg-[#0F2A20] text-white border-b border-white/10">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                        <div style="width:28px;height:28px;border-radius:6px;background:#fff;color:#0F2A20;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px">P</div>
                        <span class="font-bold text-sm">{{ \App\Facades\Settings::get('brand.name', 'PawHaven') }}</span>
                    </a>
                    <button type="button" @click="sidebarOpen = !sidebarOpen" class="p-1.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10" aria-label="Toggle navigation">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            @else
                {{-- Top Application Header (for standard admin sub-pages) --}}
                <header class="h-16 bg-white border-b border-[#E3E7E4] flex items-center justify-between px-4 sm:px-8 shrink-0">
                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            @click="sidebarOpen = !sidebarOpen"
                            class="lg:hidden p-2 rounded-lg text-[#1B2420] hover:bg-black/5 focus-visible:outline-none"
                            aria-label="Open sidebar"
                        >
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div>
                            @if (isset($breadcrumbs) || View::hasSection('breadcrumbs'))
                                <div class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6460]">
                                    {{ $breadcrumbs ?? '' }}@yield('breadcrumbs')
                                </div>
                            @endif
                            <h1 class="text-base sm:text-lg font-bold tracking-tight text-[#101814]">@yield('title', $title ?? 'Admin Dashboard')</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a
                            href="{{ route('public.home') }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-[#17603F] bg-[#17603F]/10 hover:bg-[#17603F]/20 transition-colors"
                        >
                            <span>View Public Store</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>
                </header>
            @endif

            {{-- Flash Alerts --}}
            @if (session('success'))
                <div style="padding:16px 32px 0">
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-xs sm:text-sm font-medium text-emerald-900 flex items-center justify-between">
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
                <div style="padding:16px 32px 0">
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs sm:text-sm font-medium text-rose-900 flex items-center justify-between">
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
            <div style="flex:1">
                {!! $slot ?? '' !!}
                @yield('content')
            </div>
        </div>
    </div>

    {{-- Interactive Sidebar JS from Reference --}}
    <script>
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.grp-btn');
            if (!btn) return;
            e.preventDefault();
            var isOpen = btn.getAttribute('aria-expanded') === 'true';
            var group = btn.dataset.group;

            // Close all groups (one section open at a time)
            document.querySelectorAll('.grp-btn').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
                var sub = document.getElementById('sub-' + b.dataset.group);
                if (sub) {
                    sub.hidden = true;
                }
            });

            // Open clicked section if it wasn't open
            if (!isOpen) {
                btn.setAttribute('aria-expanded', 'true');
                var currentSub = document.getElementById('sub-' + group);
                if (currentSub) {
                    currentSub.hidden = false;
                }
            }
        });
    </script>
</body>
</html>
