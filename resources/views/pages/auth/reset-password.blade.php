<x-layouts::auth :title="__('Set New Password - PawHaven')">
    <div class="flex flex-col gap-6">
        <div class="text-center space-y-1">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                Security Update
            </span>
            <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Set New Password</h2>
            <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed">
                Choose a strong, unique password to protect your PawHaven account.
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <div>
                <flux:input
                    name="email"
                    value="{{ request('email') }}"
                    :label="__('Email')"
                    type="email"
                    required
                    autocomplete="email"
                />
            </div>

            <!-- Password -->
            <div>
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('New password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />
            </div>

            <!-- Confirm Password -->
            <div>
                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm new password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />
            </div>

            <div class="pt-1">
                <flux:button type="submit" variant="primary" class="w-full !py-3 !text-sm !font-semibold !rounded-full shadow-sm hover:shadow transition-all" data-test="reset-password-button">
                    {{ __('Save New Password & Continue') }} &rarr;
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
