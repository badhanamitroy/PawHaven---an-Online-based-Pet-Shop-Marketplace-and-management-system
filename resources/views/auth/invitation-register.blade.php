<x-layouts::auth :title="__('Accept Invitation - PawHaven')">
    <div class="flex flex-col gap-6">
        {{-- Header & Role Badge --}}
        <div class="text-center space-y-2 pt-1">
            @if($invitation->isSeller())
                <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-800 border border-purple-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-purple-600"></span>
                    Official Seller Onboarding
                </span>
                <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Welcome, Partner</h2>
                <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed mt-1">
                    Complete your registration to activate your PawHaven Marketplace Seller console and list pets or companion products.
                </p>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                    Branch Management Appointment
                </span>
                <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight mt-2">Staff Onboarding</h2>
                <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed mt-1">
                    Accept your appointment as Branch Manager for the <strong>{{ $invitation->branch?->name }}</strong> branch.
                </p>
            @endif
        </div>

        {{-- Invitation Context Summary Card --}}
        <div class="p-4 rounded-2xl bg-[#FAF8F2] border border-[#202820]/10 text-xs space-y-2">
            <div class="flex justify-between items-center text-[#69736B]">
                <span class="font-semibold">Authorized Email:</span>
                <span class="font-mono text-[#173F32] font-bold">{{ $invitation->email }}</span>
            </div>
            <div class="flex justify-between items-center text-[#69736B]">
                <span class="font-semibold">Position:</span>
                <span class="font-bold text-[#245C45]">{{ $invitation->roleLabel() }}</span>
            </div>
            @if($invitation->isBranchManager() && $invitation->branch)
                <div class="flex justify-between items-center text-[#69736B]">
                    <span class="font-semibold">Assigned Branch:</span>
                    <span class="font-bold text-[#173F32]">{{ $invitation->branch->name }}</span>
                </div>
            @endif
            <div class="flex justify-between items-center text-[#69736B]">
                <span class="font-semibold">Invitation Valid Until:</span>
                <span class="text-[#69736B]">{{ $invitation->expires_at->format('M d, Y, g:i A') }}</span>
            </div>
        </div>

        {{-- Errors Alert --}}
        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Please review the errors below:</span>
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Registration Form --}}
        <form method="POST" action="{{ route('invitations.complete', ['token' => $token]) }}" class="auth-form flex flex-col gap-5">
            @csrf

            <!-- Candidate Name -->
            <div>
                <flux:input
                    name="name"
                    :label="__('Full Name')"
                    :value="old('name', $invitation->name)"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Your legal or trade name"
                    class="[&_input]:!text-[#245C45] [&_input]:!font-bold [&_label]:!text-[#173F32] [&_label]:!font-bold"
                />
            </div>

            <!-- Email (Display Only / Immutable) -->
            <div>
                <label class="block text-xs font-bold text-[#173F32] mb-1.5">
                    Registered Email Address
                </label>
                <div class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-xs font-mono text-gray-500 flex items-center justify-between">
                    <span>{{ $invitation->email }}</span>
                    <span class="text-[10px] uppercase font-bold text-emerald-700 bg-emerald-100/60 px-2 py-0.5 rounded">Verified Invitation</span>
                </div>
            </div>

            <!-- Password -->
            <div>
                <flux:input
                    name="password"
                    :label="__('Create Security Password')"
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

            {{-- Policy & Agreement Notice --}}
            <div class="p-3 rounded-xl bg-[#FAF8F2] border border-[#202820]/10 text-[11px] text-[#69736B] leading-relaxed">
                By accepting this invitation, you agree to comply with PawHaven's
                <span class="font-semibold text-[#173F32]">Ethical Companion Animal &amp; Operations Policy</span>.
                Your account will require Email OTP verification before first access.
            </div>

            {{-- Submit Button --}}
            <div class="pt-1">
                <flux:button type="submit" variant="primary" class="w-full !py-3.5 !text-sm !font-semibold !rounded-full shadow-sm hover:shadow transition-all">
                    <span>Complete Registration &amp; Verify Email &rarr;</span>
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
