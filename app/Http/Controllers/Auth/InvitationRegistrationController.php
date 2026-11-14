<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Services\EmailOtpService;
use App\Services\BusinessAccessService;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvitationRegistrationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService,
        protected EmailOtpService $otpService
    ) {}

    public function show(string $token): View
    {
        $invitation = $this->invitationService->findByRawToken($token);

        if (! $invitation) {
            return view('auth.invitation-invalid', [
                'reason' => 'not_found',
            ]);
        }

        if ($invitation->isRevoked()) {
            return view('auth.invitation-invalid', [
                'reason' => 'revoked',
                'invitation' => $invitation,
            ]);
        }

        if ($invitation->isAccepted()) {
            return view('auth.invitation-invalid', [
                'reason' => 'accepted',
                'invitation' => $invitation,
            ]);
        }

        if ($invitation->isExpired()) {
            return view('auth.invitation-invalid', [
                'reason' => 'expired',
                'invitation' => $invitation,
            ]);
        }

        try {
            $company = app(BusinessAccessService::class)->installationCompany((int) $invitation->company_id);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return view('auth.invitation-invalid', [
                'reason' => 'company_unavailable',
                'invitation' => $invitation,
            ]);
        }

        if (! $company->is_active) {
            return view('auth.invitation-invalid', [
                'reason' => 'company_unavailable',
                'invitation' => $invitation,
            ]);
        }

        if ($this->invitationService->hasExistingAccount($invitation->email)) {
            return view('auth.invitation-invalid', [
                'reason' => 'user_exists',
                'invitation' => $invitation,
            ]);
        }

        if ($invitation->isBranchManager()) {
            $branch = $invitation->branch;

            if (! $branch || ! $branch->is_active || (int) $branch->company_id !== (int) $invitation->company_id) {
                return view('auth.invitation-invalid', [
                    'reason' => 'branch_unavailable',
                    'invitation' => $invitation,
                ]);
            }
        }

        return view('auth.invitation-register', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function complete(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->invitationService->findByRawToken($token);

        if (! $invitation || ! $invitation->isPending()) {
            return redirect()->route('invitations.register', ['token' => $token]);
        }

        if ($this->invitationService->hasExistingAccount($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => 'An account with this email address already exists.',
            ]);
        }

        $user = $this->invitationService->acceptInvitation(
            $invitation,
            $token,
            $request->validated()
        );

        $purpose = $user->isSeller() ? 'seller_login' : 'admin_login';
        $challengeId = bin2hex(random_bytes(32));

        $request->session()->regenerate();
        $request->session()->put('auth.email_otp', [
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'challenge_id' => $challengeId,
            'remember' => false,
            'requires_totp' => false,
        ]);

        $otpResult = $this->otpService->generateAndSendOtp($user, $purpose, $challengeId);

        if (! $otpResult['success']) {
            $request->session()->forget('auth.email_otp');
            $fallbackLogin = $user->isSeller() ? route('login', ['role' => 'seller']) : route('admin.login');

            return redirect($fallbackLogin)->withErrors(['email' => $otpResult['message']]);
        }

        return redirect()->route($user->isSeller() ? 'login.otp.show' : 'admin.two-factor');
    }
}
