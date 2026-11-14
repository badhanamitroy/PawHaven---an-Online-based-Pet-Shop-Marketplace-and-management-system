<x-layouts::auth :title="__('Reset Password - PawHaven')">
    <div class="flex flex-col gap-6">
        <div class="text-center space-y-1">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                Account Recovery
            </span>
            <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Forgot Password?</h2>
            <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed">
                Enter your account email below and we'll send you instructions to safely reset your password.
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Email Address -->
            <div>
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    type="email"
                    required
                    autofocus
                    placeholder="email@example.com"
                />
            </div>

            <div class="pt-1">
                <flux:button variant="primary" type="submit" class="w-full !py-3 !text-sm !font-semibold !rounded-full shadow-sm hover:shadow transition-all" data-test="email-password-reset-link-button">
                    {{ __('Email password reset link') }} &rarr;
                </flux:button>
            </div>
        </form>

        <div class="pt-2 border-t border-[#202820]/10 text-xs text-center text-[#69736B]">
            <span>{{ __('Or, return to') }}</span>
            <a href="{{ route('login') }}" class="font-bold text-[#245C45] hover:underline ml-1" wire:navigate>{{ __('Sign In') }}</a>
        </div>
    </div>
</x-layouts::auth>
