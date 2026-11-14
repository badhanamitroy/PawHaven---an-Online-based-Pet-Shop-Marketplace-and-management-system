<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('partials.head', ['title' => 'Admin Console Sign In'])
    </head>
    <body class="min-h-full font-sans antialiased bg-[#FAF8F2] text-[#202820] flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        @php
            $brandName = \App\Facades\Settings::get('brand.name', config('app.name', 'PawHaven'));
            $whiteLogo = \App\Facades\Settings::get('brand.logo_dark_path', asset('images/pawhaven-logo-white.png'));
            $greenLogo = \App\Facades\Settings::get('brand.logo_light_path', asset('images/pawhaven-logo-green.png'));
        @endphp

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            {{-- Header Branding Card --}}
            <div class="bg-[#173F32] rounded-t-3xl p-8 text-center text-white relative overflow-hidden shadow-lg border-b border-white/10">
                {{-- Decorative background gradient blobs --}}
                <div class="absolute -top-16 -left-16 w-48 h-48 rounded-full bg-emerald-600/20 blur-2xl pointer-events-none"></div>
                <div class="absolute -bottom-16 -right-16 w-48 h-48 rounded-full bg-[#245C45]/40 blur-2xl pointer-events-none"></div>

                <div class="relative z-10 space-y-4">
                    <div class="inline-flex items-center justify-center">
                        <img
                            src="{{ $whiteLogo }}"
                            alt="{{ $brandName }} Logo"
                            class="h-12 w-auto max-h-12 max-w-[180px] object-contain drop-shadow-sm"
                            style="height: 48px !important; max-height: 48px !important; width: auto !important; object-fit: contain !important;"
                        >
                    </div>

                    <div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-white/10 text-emerald-200 border border-white/10">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Internal Gateway
                        </span>
                        <h1 class="text-2xl font-extrabold text-white tracking-tight mt-2">{{ $brandName }} Administration</h1>
                        <p class="text-xs text-emerald-100/80 mt-1">Authorized Staff &amp; Administrator Console</p>
                    </div>
                </div>
            </div>

            {{-- Form Container --}}
            <div class="bg-white rounded-b-3xl p-8 sm:p-10 shadow-xl border border-[#202820]/10 border-t-0 space-y-6">
                {{-- Session Status --}}
                <x-auth-session-status class="text-center" :status="session('status')" />

                {{-- Errors Alert --}}
                @if ($errors->any())
                    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Authentication Error</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-5" autocomplete="off">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <label for="admin_email" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                            Admin Email Address
                        </label>
                        <div class="relative">
                            <input
                                id="admin_email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                autocomplete="off"
                                class="w-full px-4 py-3 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm font-bold text-[#245C45] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45] transition-all"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="admin_password" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-1.5">
                            Security Password
                        </label>
                        <div class="relative" x-data="{ show: false }">
                            <input
                                id="admin_password"
                                :type="show ? 'text' : 'password'"
                                name="password"
                                required
                                autocomplete="new-password"
                                class="w-full px-4 py-3 rounded-xl border border-[#202820]/15 bg-[#FAF8F2] text-sm font-bold text-[#245C45] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45] transition-all pr-10"
                            >
                            <button
                                type="button"
                                @click="show = !show"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#69736B] hover:text-[#202820]"
                                tabindex="-1"
                            >
                                <svg x-show="!show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="show" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <p class="text-xs text-[#69736B]">
                        This browser stays signed in until you log out, with a 30-day inactivity limit.
                    </p>

                    {{-- Submit Button --}}
                    <div>
                        <button
                            type="submit"
                            class="w-full py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-[#173F32] hover:bg-[#245C45] transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#245C45] focus:ring-offset-2 flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>Sign In to Admin Console</span>
                        </button>
                    </div>
                </form>

                {{-- Security Compliance Advisory --}}
                <div class="pt-4 border-t border-[#202820]/10 text-center space-y-2">
                    <div class="flex items-center justify-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#69736B]">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Security &amp; Audit Policy</span>
                    </div>
                    <p class="text-[11px] text-[#69736B] leading-relaxed max-w-xs mx-auto">
                        This console is restricted to Admin and Staff accounts and requires email verification.
                    </p>
                </div>
            </div>

            {{-- Return to Public Site Link --}}
            <div class="mt-6 text-center">
                <a
                    href="{{ route('public.home') }}"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#69736B] hover:text-[#173F32] transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Return to PawHaven Public Site</span>
                </a>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
