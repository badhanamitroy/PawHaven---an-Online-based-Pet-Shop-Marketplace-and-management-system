<x-layouts::auth :title="__('Confirm Password - PawHaven')">
    <div class="flex flex-col gap-6">
        <div class="text-center space-y-1">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                Security Checkpoint
            </span>
            <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Confirm Password</h2>
            <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed">
                This is a secure area of the application. Please confirm your password before continuing.
            </p>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-5">
            @csrf

            <div>
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Enter your password')"
                    viewable
                />
            </div>

            <div class="pt-1">
                <flux:button variant="primary" type="submit" class="w-full !py-3 !text-sm !font-semibold !rounded-full shadow-sm hover:shadow transition-all" data-test="confirm-password-button">
                    {{ __('Confirm Password') }} &rarr;
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
