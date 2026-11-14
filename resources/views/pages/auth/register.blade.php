<x-layouts::auth :title="__('Create an Account - PawHaven')">
    <div class="flex flex-col gap-6">
        <div class="text-center space-y-2 pt-1">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                Customer Registration
            </span>
            <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Join PawHaven</h2>
            <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed mt-1">
                Create an account to adopt pets, buy quality pet nutrition &amp; toys, and track your orders.
            </p>
        </div>

        {{-- Session Status --}}
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="auth-form flex flex-col gap-5">
            @csrf

            <!-- Name -->
            <div>
                <flux:input
                    name="name"
                    :label="__('Full Name')"
                    :value="old('name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Your full name"
                    class="[&_input]:!text-[#245C45] [&_input]:!font-bold [&_label]:!text-[#173F32] [&_label]:!font-bold"
                />
            </div>

            <!-- Email Address -->
            <div>
                <flux:input
                    name="email"
                    :label="__('Email Address')"
                    :value="old('email')"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                    class="[&_input]:!text-[#245C45] [&_input]:!font-bold [&_label]:!text-[#173F32] [&_label]:!font-bold"
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
                    :placeholder="__('Create a strong password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                    class="[&_input]:!text-[#245C45] [&_input]:!font-bold [&_label]:!text-[#173F32] [&_label]:!font-bold"
                />
            </div>

            <!-- Confirm Password -->
            <div>
                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Re-enter your password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                    class="[&_input]:!text-[#245C45] [&_input]:!font-bold [&_label]:!text-[#173F32] [&_label]:!font-bold"
                />
            </div>

            {{-- Animal Welfare Agreement Notice --}}
            <div class="p-3 rounded-xl bg-[#FAF8F2] border border-[#202820]/10 text-[11px] text-[#69736B] leading-relaxed">
                By creating an account, you agree to PawHaven's
                <span class="font-semibold text-[#173F32]">Ethical Companion Animal Policy</span>,
                prioritizing pet health, safety, and transparent transactions.
            </div>

            {{-- Submit Button --}}
            <div class="pt-1">
                <flux:button type="submit" variant="primary" class="w-full !py-3 !text-sm !font-semibold !rounded-full shadow-sm hover:shadow transition-all" data-test="register-user-button">
                    Create Customer Account &rarr;
                </flux:button>
            </div>
        </form>

        {{-- Switcher Link to Login --}}
        <div class="pt-2 border-t border-[#202820]/10 text-xs text-center text-[#69736B]">
            <span>Already have an account?</span>
            <a href="{{ route('login') }}" class="font-bold text-[#245C45] hover:underline ml-1" wire:navigate>
                Sign In &rarr;
            </a>
        </div>
    </div>
</x-layouts::auth>
