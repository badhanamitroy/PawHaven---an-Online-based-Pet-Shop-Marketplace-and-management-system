<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('partials.head', ['title' => 'Admin Two-Factor Verification'])
    </head>
    <body class="min-h-full font-sans antialiased bg-[#FAF8F2] text-[#202820] flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        @php
            $brandName = \App\Facades\Settings::get('brand.name', config('app.name', 'PawHaven'));
            $whiteLogo = \App\Facades\Settings::get('brand.logo_dark_path', asset('images/pawhaven-logo-white.png'));
        @endphp

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            {{-- Header Branding Card --}}
            <div class="bg-[#173F32] rounded-t-3xl p-8 text-center text-white relative overflow-hidden shadow-lg border-b border-white/10">
                <div class="absolute -top-16 -left-16 w-48 h-48 rounded-full bg-emerald-600/20 blur-2xl pointer-events-none"></div>
                <div class="absolute -bottom-16 -right-16 w-48 h-48 rounded-full bg-[#245C45]/40 blur-2xl pointer-events-none"></div>

                <div class="relative z-10 space-y-3">
                    <div class="inline-flex items-center justify-center">
                        <img
                            src="{{ $whiteLogo }}"
                            alt="{{ $brandName }} Logo"
                            class="h-11 w-auto max-h-11 object-contain drop-shadow-sm"
                            style="height: 44px !important; max-height: 44px !important; width: auto !important; object-fit: contain !important;"
                        >
                    </div>

                    <div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-white/10 text-emerald-200 border border-white/10">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Two-Factor Authentication
                        </span>
                        <h1 class="text-2xl font-extrabold text-white tracking-tight mt-2">Security Verification</h1>
                        <p class="text-xs text-emerald-100/80 mt-1">Code sent to <span class="font-bold text-white">{{ $maskedEmail }}</span></p>
                    </div>
                </div>
            </div>

            {{-- Form Container --}}
            <div class="bg-white rounded-b-3xl p-8 sm:p-10 shadow-xl border border-[#202820]/10 border-t-0 space-y-6">
                {{-- Session Status Alert --}}
                @if (session('status'))
                    <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800 text-center">
                        {{ session('status') }}
                    </div>
                @endif

                {{-- Validation Errors --}}
                @if ($errors->any())
                    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Verification Notice</span>
                        </div>
                        <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="text-xs text-[#69736B] leading-relaxed text-center">
                    Enter the 6-digit numeric verification code sent to your email. The code expires in <strong class="text-[#173F32]">5 minutes</strong>.
                </p>

                <form method="POST" action="{{ route('admin.two-factor.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="otp_code" class="block text-xs font-bold uppercase tracking-wider text-[#173F32] mb-2 text-center">
                            6-Digit Security Code
                        </label>
                        <input
                            id="otp_code"
                            type="text"
                            name="code"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            required
                            autofocus
                            autocomplete="one-time-code"
                            class="w-full text-center text-3xl font-mono font-extrabold tracking-[10px] py-3.5 px-4 rounded-xl border border-[#202820]/20 bg-[#FAF8F2] text-[#245C45] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45] transition-all"
                        >
                    </div>

                    <div>
                        <button
                            type="submit"
                            class="w-full py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-[#173F32] hover:bg-[#245C45] transition-all shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#245C45] focus:ring-offset-2 flex items-center justify-center gap-2"
                        >
                            <span>Verify Code &amp; Access Dashboard &rarr;</span>
                        </button>
                    </div>
                </form>

                {{-- Resend & Cancel Actions --}}
                <div class="pt-3 border-t border-[#202820]/10 flex flex-col sm:flex-row items-center justify-between text-xs gap-3">
                    <form method="POST" action="{{ route('admin.two-factor.resend') }}">
                        @csrf
                        <button
                            type="submit"
                            class="font-bold text-[#245C45] hover:text-[#173F32] hover:underline transition-colors"
                        >
                            {{ __('Resend Verification Code') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.two-factor.cancel') }}">
                        @csrf
                        <button
                            type="submit"
                            class="text-[#69736B] hover:text-rose-600 transition-colors"
                        >
                            {{ __('Cancel & Sign In as Different User') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @fluxScripts
    </body>
</html>

