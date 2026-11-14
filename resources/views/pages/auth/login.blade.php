@php
    $initialRole = request()->query('role', 'customer');
    if (! in_array($initialRole, ['customer', 'seller'], true)) {
        $initialRole = 'customer';
    }
@endphp

<x-layouts::auth :title="__('Sign In - PawHaven')">
    <div
        x-data="{
            role: '{{ $initialRole }}',
            setRole(newRole) {
                this.role = newRole;
                const url = new URL(window.location.href);
                url.searchParams.set('role', newRole);
                window.history.replaceState({}, '', url.toString());
            }
        }"
        class="flex flex-col gap-6"
    >
        {{-- Public Role Selector: Customer vs Seller (Admin is internal and strictly excluded) --}}
        <div>
            <div class="grid grid-cols-2 gap-1.5 p-1 bg-[#FAF8F2] rounded-2xl border border-[#202820]/10 text-xs font-semibold">
                <button
                    type="button"
                    @click="setRole('customer')"
                    :class="role === 'customer' ? 'bg-[#245C45] text-white shadow-xs' : 'text-[#69736B] hover:text-[#202820] hover:bg-white/60'"
                    class="py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-center"
                >
                    <span class="text-sm">👤</span>
                    <span>Customer Portal</span>
                </button>

                <button
                    type="button"
                    @click="setRole('seller')"
                    :class="role === 'seller' ? 'bg-[#245C45] text-white shadow-xs' : 'text-[#69736B] hover:text-[#202820] hover:bg-white/60'"
                    class="py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-center"
                >
                    <span class="text-sm">🏪</span>
                    <span>Seller / Shopkeeper</span>
                </button>
            </div>
        </div>

        {{-- Dynamic Role Header & Guidance --}}
        <div class="text-center space-y-2 pt-1">
            <template x-if="role === 'customer'">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                        Customer &amp; Pet Parent Portal
                    </span>
                    <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Welcome Back</h2>
                    <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed mt-1">
                        Sign in to track your store orders, manage pet adoption inquiries, and browse vetted listings.
                    </p>
                </div>
            </template>

            <template x-if="role === 'seller'">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-900 border border-amber-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                        Shopkeeper &amp; Seller Portal
                    </span>
                    <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Merchant Sign In</h2>
                    <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed mt-1">
                        Sign in to review customer buy requests, manage shop inventory, and fulfill store orders.
                    </p>
                </div>
            </template>
        </div>

        {{-- Session Status --}}
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="auth-form flex flex-col gap-5">
            @csrf

            <!-- Email Address -->
            <div>
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="[&_input]:!text-[#245C45] [&_input]:!font-bold [&_label]:!text-[#173F32] [&_label]:!font-bold"
                />
            </div>

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                    class="[&_input]:!text-[#245C45] [&_input]:!font-bold [&_label]:!text-[#173F32] [&_label]:!font-bold"
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-xs end-0 text-[#245C45] hover:text-[#173F32] font-semibold" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot password?') }}
                    </flux:link>
                @endif
            </div>

            <p class="text-xs text-[#69736B]">
                This browser stays signed in until you log out, with a 30-day inactivity limit. Manage other sign-ins in Active devices.
            </p>

            {{-- Submit Button --}}
            <div class="pt-1">
                <flux:button variant="primary" type="submit" class="w-full !py-3 !text-sm !font-semibold !rounded-full shadow-sm hover:shadow transition-all" data-test="login-button">
                    <span x-show="role === 'customer'">Sign In as Customer &rarr;</span>
                    <span x-show="role === 'seller'">Sign In to Seller Console &rarr;</span>
                </flux:button>
            </div>
        </form>

        {{-- Role-Tailored Registration & Switcher Links --}}
        <div class="pt-2 border-t border-[#202820]/10 text-xs text-center space-y-2">
            <template x-if="role === 'customer'">
                <p class="text-[#69736B]">
                    Don't have a customer account?
                    <a href="{{ route('register') }}" class="font-bold text-[#245C45] hover:underline" wire:navigate>Create Free Account</a>
                </p>
            </template>

            <template x-if="role === 'seller'">
                <p class="text-[#69736B]">Seller accounts are managed by the site administrator.</p>
            </template>
        </div>
    </div>
</x-layouts::auth>
