<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-full font-sans antialiased bg-[#FAF8F2] text-[#202820]">
        @php
            $brandName = \App\Facades\Settings::get('brand.name', config('app.name', 'PawHaven'));
            $rawWhite = \App\Facades\Settings::get('brand.logo_dark_path', 'images/pawhaven-logo-white.png');
            $whiteLogo = str_starts_with((string) $rawWhite, 'http') ? $rawWhite : '/' . ltrim((string) $rawWhite, '/');
            $rawGreen = \App\Facades\Settings::get('brand.logo_light_path', 'images/pawhaven-logo-green.png');
            $greenLogo = str_starts_with((string) $rawGreen, 'http') ? $rawGreen : '/' . ltrim((string) $rawGreen, '/');
        @endphp

        <div class="relative min-h-screen flex flex-col lg:flex-row">
            {{-- Left Brand Hero Panel (Desktop) --}}
            <aside class="relative hidden lg:flex lg:w-5/12 xl:w-1/2 bg-[#173F32] text-white flex-col justify-between p-12 overflow-hidden border-r border-[#202820]/10">
                {{-- Decorative background gradient blobs --}}
                <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-emerald-700/20 blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-[#245C45]/30 blur-3xl pointer-events-none"></div>

                {{-- Top Brand Lockup --}}
                <div class="relative z-10">
                    <a href="{{ route('public.home') }}" class="inline-flex items-center gap-3.5 group transition-transform hover:opacity-95" wire:navigate>
                        <img
                            src="{{ $whiteLogo }}"
                            alt="{{ $brandName }} Logo"
                            class="h-11 w-auto max-h-11 max-w-[180px] shrink-0 object-contain drop-shadow-sm"
                        >
                        <div>
                            <span class="text-2xl font-bold tracking-tight text-white block leading-none">{{ $brandName }}</span>
                            <span class="text-[11px] uppercase tracking-wider font-semibold text-emerald-200/80 block mt-1">Pet Marketplace &amp; Adoption</span>
                        </div>
                    </a>
                </div>

                {{-- Middle Content: Values & Trust Highlights --}}
                <div class="relative z-10 my-auto py-10 space-y-8 max-w-lg">
                    <div class="space-y-3">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-900/60 text-emerald-200 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Ethical Single-Shop &amp; Adoption Community
                        </span>
                        <h1 class="text-3xl xl:text-4xl font-extrabold text-white tracking-tight leading-snug">
                            Find Pets. Give Hope. Shop with Love.
                        </h1>
                        <p class="text-white/80 text-sm leading-relaxed">
                            PawHaven unites responsible pet parents, approved sellers, and rescue volunteers under a unified, ethical ecosystem.
                        </p>
                    </div>

                    {{-- Feature Bullets --}}
                    <div class="space-y-4 pt-2">
                        <div class="flex items-start gap-3.5">
                            <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center shrink-0 text-emerald-300 mt-0.5 border border-white/10">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                    <path d="M12 11.5c-2.2 0-4 1.8-4 4 0 1.9 1.5 3.5 3.5 3.5.7 0 1.3-.2 1.8-.5.5.3 1.1.5 1.8.5 2 0 3.5-1.6 3.5-3.5 0-2.2-1.8-4-4-4zm-5.5-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm11 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">Ethical Pet Marketplace</h3>
                                <p class="text-xs text-white/70 leading-relaxed mt-0.5">Strict moderation against mills, transparent breeder details, and rescue adoption connections.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3.5">
                            <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center shrink-0 text-emerald-300 mt-0.5 border border-white/10">
                                <svg class="w-4 h-4 fill-none stroke-current" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">PawHaven Pet Shop</h3>
                                <p class="text-xs text-white/70 leading-relaxed mt-0.5">Premium pet food, beds, and grooming essentials with direct Cash on Delivery checkout.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3.5">
                            <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center shrink-0 text-emerald-300 mt-0.5 border border-white/10">
                                <svg class="w-4 h-4 fill-none stroke-current" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white">Dedicated Role Portals</h3>
                                <p class="text-xs text-white/70 leading-relaxed mt-0.5">Tailored workspaces for Pet Parents, Store Keepers, and Administrators.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bottom Disclaimer & Copyright --}}
                <div class="relative z-10 pt-6 border-t border-white/10 flex flex-col gap-1 text-xs text-white/60">
                    <p>&ldquo;Dedicated to ethical pet parenthood and lifelong companion care.&rdquo;</p>
                    <p>&copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.</p>
                </div>
            </aside>

            {{-- Right Form Section --}}
            <main class="w-full lg:w-7/12 xl:w-1/2 min-h-screen flex flex-col justify-center items-center py-10 px-4 sm:px-6 lg:px-12 bg-[#FAF8F2]">
                <div class="w-full max-w-md space-y-6">
                    {{-- Mobile Logo Header --}}
                    <div class="lg:hidden flex flex-col items-center justify-center gap-2 text-center pb-2">
                        <a href="{{ route('public.home') }}" class="inline-flex items-center gap-2.5" wire:navigate>
                            <img
                                src="{{ $greenLogo }}"
                                alt="{{ $brandName }}"
                                class="h-9 w-auto max-h-9 max-w-[150px] object-contain"
                            >
                            <span class="text-xl font-bold tracking-tight text-[#173F32]">{{ $brandName }}</span>
                        </a>
                        <span class="text-xs text-[#69736B]">Pet Marketplace &amp; Adoption Community</span>
                    </div>

                    {{-- Form Container Card --}}
                    <div class="bg-white rounded-3xl border border-[#202820]/10 p-6 sm:p-10 shadow-sm transition-all">
                        {{ $slot }}
                    </div>

                    {{-- Footer Back Link --}}
                    <div class="text-center">
                        <a href="{{ route('public.home') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#69736B] hover:text-[#173F32] transition-colors" wire:navigate>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span>Back to Homepage</span>
                        </a>
                    </div>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
