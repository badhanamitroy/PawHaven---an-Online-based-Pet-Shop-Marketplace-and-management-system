<x-layouts::auth :title="__('Invitation Unavailable - PawHaven')">
    <div class="flex flex-col gap-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 border border-amber-200 text-amber-700">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <div class="space-y-2">
            <h2 class="text-2xl font-extrabold text-[#173F32] tracking-tight">
                @if($reason === 'expired')
                    Invitation Has Expired
                @elseif($reason === 'revoked')
                    Invitation Has Been Revoked
                @elseif($reason === 'accepted')
                    Invitation Already Accepted
                @elseif($reason === 'user_exists')
                    Account Already Registered
                @elseif($reason === 'branch_unavailable')
                    Invitation No Longer Valid
                @else
                    Invalid Invitation Link
                @endif
            </h2>

            <p class="text-xs text-[#69736B] max-w-sm mx-auto leading-relaxed">
                @if($reason === 'expired')
                    This invitation link has passed its 72-hour validity window. Please contact the PawHaven Administrator to request a fresh invitation.
                @elseif($reason === 'revoked')
                    This onboarding invitation was cancelled or updated by the Administrator. If you believe this is in error, please reach out to PawHaven Administration.
                @elseif($reason === 'accepted')
                    This invitation link has already been used to register an account. You can sign in using your established credentials.
                @elseif($reason === 'user_exists')
                    A user account with this email address is already active on PawHaven. Please proceed to sign in directly.
                @elseif($reason === 'branch_unavailable')
                    The branch assigned to this Branch Manager invitation is no longer active. Please contact PawHaven Administration for a new assignment.
                @else
                    The invitation link you followed is incomplete, malformed, or does not exist. Please check your email or contact administration.
                @endif
            </p>
        </div>

        <div class="pt-4 flex flex-col gap-3">
            @if($reason === 'accepted' || $reason === 'user_exists')
                @if(isset($invitation) && $invitation->isBranchManager())
                    <a href="{{ route('admin.login') }}" class="w-full py-3 px-4 rounded-full text-xs font-bold text-white bg-[#173F32] hover:bg-[#245C45] transition-all shadow-sm">
                        Go to Staff &amp; Administrator Login &rarr;
                    </a>
                @else
                    <a href="{{ route('login', ['role' => 'seller']) }}" class="w-full py-3 px-4 rounded-full text-xs font-bold text-white bg-[#173F32] hover:bg-[#245C45] transition-all shadow-sm">
                        Go to Seller Login &rarr;
                    </a>
                @endif
            @endif

            <a href="{{ route('public.home') }}" class="text-xs font-semibold text-[#245C45] hover:underline">
                &larr; Return to PawHaven Home
            </a>
        </div>
    </div>
</x-layouts::auth>
