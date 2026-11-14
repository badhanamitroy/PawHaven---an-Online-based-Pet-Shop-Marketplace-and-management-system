<x-layouts::auth :title="__('Email verification')">
    <div class="flex flex-col gap-6">
        <div class="text-center">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-900">
                {{ $accountLabel }} sign-in
            </span>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-[#173F32]">
                {{ __('Verify your email') }}
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-[#69736B]">
                {{ __('We sent a 6-digit sign-in code to') }}
                <strong class="text-[#173F32]">{{ $maskedEmail }}</strong>.
                {{ __('The code expires in 5 minutes.') }}
            </p>
        </div>

        @if (session('status'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route($verifyRoute) }}" class="flex flex-col gap-5">
            @csrf
            <div>
                <label for="email_otp_code" class="mb-2 block text-center text-xs font-bold uppercase tracking-wider text-[#173F32]">
                    {{ __('6-digit verification code') }}
                </label>
                <input
                    id="email_otp_code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    required
                    autofocus
                    class="w-full rounded-xl border border-[#202820]/20 bg-[#FAF8F2] px-4 py-3.5 text-center font-mono text-3xl font-extrabold tracking-[0.5em] text-[#245C45] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#245C45]"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-[#173F32] px-4 py-3.5 text-sm font-bold text-white shadow-md transition-colors hover:bg-[#245C45] focus:outline-none focus:ring-2 focus:ring-[#245C45] focus:ring-offset-2"
            >
                {{ __('Verify and continue') }}
            </button>
        </form>

        <div class="flex flex-col items-center gap-3 border-t border-[#202820]/10 pt-5 text-sm">
            <form method="POST" action="{{ route($resendRoute) }}">
                @csrf
                <button
                    id="resend-email-otp"
                    type="submit"
                    @disabled($cooldown > 0)
                    class="font-semibold text-[#245C45] hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ __('Resend verification code') }}
                </button>
                <p
                    id="email-otp-cooldown"
                    data-seconds="{{ $cooldown }}"
                    class="mt-1 text-center text-xs text-[#69736B]"
                    aria-live="polite"
                >@if ($cooldown > 0){{ __('Resend available in :seconds seconds.', ['seconds' => $cooldown]) }}@else{{ __('You can request another code if needed.') }}@endif</p>
            </form>

            <form method="POST" action="{{ route($cancelRoute) }}">
                @csrf
                <button type="submit" class="text-xs text-[#69736B] hover:text-rose-700">
                    {{ __('Cancel and sign in again') }}
                </button>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const countdown = document.getElementById('email-otp-cooldown');
            const resend = document.getElementById('resend-email-otp');
            if (!countdown || !resend) return;

            let seconds = Number(countdown.dataset.seconds || 0);
            if (seconds <= 0) return;

            const interval = window.setInterval(() => {
                seconds -= 1;
                if (seconds <= 0) {
                    window.clearInterval(interval);
                    resend.disabled = false;
                    countdown.textContent = 'You can request another code if needed.';
                    return;
                }

                countdown.textContent = `Resend available in ${seconds} seconds.`;
            }, 1000);
        })();
    </script>
</x-layouts::auth>
