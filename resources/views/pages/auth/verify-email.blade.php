<x-layouts::auth :title="__('Verify Email - PawHaven')">
    <div class="flex flex-col gap-6">
        <div class="text-center space-y-1">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                Email Verification
            </span>
            <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Check Your Inbox</h2>
            <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed">
                {{ __('Please verify your email address by clicking on the link we just sent to your inbox.') }}
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 text-center font-medium">
                {{ __('A new verification link has been sent to the email address you provided.') }}
            </div>
        @endif

        <div class="flex flex-col gap-3 pt-2">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full !py-3 !text-sm !font-semibold !rounded-full shadow-sm hover:shadow transition-all">
                    {{ __('Resend verification email') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button type="submit" class="text-xs font-semibold text-[#69736B] hover:text-[#173F32] transition-colors" data-test="logout-button">
                    {{ __('Log out of account') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts::auth>
