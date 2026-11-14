<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\UserDeviceSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class EmailOtpChallengeController extends Controller
{
    public function __construct(private EmailOtpService $otpService) {}

    public function show(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingChallenge($request);
        if ($pending === null) {
            $this->clearPendingChallenge($request);

            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification session has expired. Please sign in again.']);
        }

        /** @var User $user */
        $user = $pending['user'];
        $accountLabel = $user->isSeller() ? 'Seller' : 'Customer';

        return view('auth.email-otp-challenge', [
            'accountLabel' => $accountLabel,
            'maskedEmail' => $this->maskEmail($user->email),
            'cooldown' => $this->otpService->getRemainingCooldown($user, $pending['purpose']),
            'verifyRoute' => 'login.otp.verify',
            'resendRoute' => 'login.otp.resend',
            'cancelRoute' => 'login.otp.cancel',
        ]);
    }

    public function verify(Request $request): Response
    {
        $request->validate([
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D'],
        ]);

        $pending = $this->pendingChallenge($request);
        if ($pending === null) {
            $this->clearPendingChallenge($request);

            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification session has expired. Please sign in again.']);
        }

        /** @var User $user */
        $user = $pending['user'];
        $result = $this->otpService->verifyOtp(
            $user,
            (string) $request->input('code'),
            $pending['purpose'],
            $pending['challenge_id'],
        );

        if ($result['status'] !== 'valid') {
            throw ValidationException::withMessages([
                'code' => $result['message'] ?? 'The verification code is invalid.',
            ]);
        }

        $user->refresh();
        if (! $this->purposeMatchesRole($pending['purpose'], $user)) {
            $this->clearPendingChallenge($request, $user);

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account access changed. Please sign in again.']);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $request->session()->forget('auth.email_otp');
        $request->session()->regenerate();
        app(UserDeviceSessionService::class)->markPolicyVerified($user, $request);

        if (Features::enabled(Features::twoFactorAuthentication())
            && $user->two_factor_confirmed_at !== null) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => (bool) $pending['remember'],
            ]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::guard('web')->login($user, (bool) $pending['remember']);

        return app(LoginResponse::class)->toResponse($request);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pendingChallenge($request);
        if ($pending === null) {
            $this->clearPendingChallenge($request);

            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification session has expired. Please sign in again.']);
        }

        /** @var User $user */
        $user = $pending['user'];
        $result = $this->otpService->generateAndSendOtp(
            $user,
            $pending['purpose'],
            $pending['challenge_id'],
        );

        if (! $result['success']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        return back()->with('status', 'A new verification code was sent to your registered email address.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->clearPendingChallenge($request);
        $request->session()->forget('url.intended');
        $request->session()->regenerate();

        return redirect()->route('login');
    }

    /**
     * @return array{user: User, purpose: string, challenge_id: string, remember: bool}|null
     */
    private function pendingChallenge(Request $request): ?array
    {
        $challenge = $request->session()->get('auth.email_otp');
        if (! is_array($challenge)
            || ! isset($challenge['user_id'], $challenge['purpose'], $challenge['challenge_id'])
            || ! is_numeric($challenge['user_id'])
            || ! is_string($challenge['purpose'])
            || ! is_string($challenge['challenge_id'])) {
            return null;
        }

        $user = User::query()->find((int) $challenge['user_id']);
        if (! $user || ! $this->purposeMatchesRole($challenge['purpose'], $user)) {
            return null;
        }

        return [
            'user' => $user,
            'purpose' => $challenge['purpose'],
            'challenge_id' => $challenge['challenge_id'],
            'remember' => (bool) ($challenge['remember'] ?? false),
        ];
    }

    private function purposeMatchesRole(string $purpose, User $user): bool
    {
        return match ($purpose) {
            'seller_login' => $user->isSeller(),
            'customer_login' => $user->isCustomer(),
            default => false,
        };
    }

    private function clearPendingChallenge(Request $request, ?User $user = null): void
    {
        $challenge = $request->session()->get('auth.email_otp');
        if ($user === null && is_array($challenge) && isset($challenge['user_id'])) {
            $user = User::query()->find((int) $challenge['user_id']);
        }

        if ($user
            && is_array($challenge)
            && isset($challenge['purpose'], $challenge['challenge_id'])
            && is_string($challenge['purpose'])
            && is_string($challenge['challenge_id'])) {
            $this->otpService->clearOtp($user, $challenge['purpose'], $challenge['challenge_id']);
        }

        $request->session()->forget([
            'auth.email_otp',
            'login.id',
            'login.remember',
        ]);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        [$local, $domain] = $parts;
        $maskedLocal = substr($local, 0, 1).str_repeat('*', max(3, strlen($local) - 1));
        $domainParts = explode('.', $domain);
        $domainName = array_shift($domainParts) ?? '';
        $maskedDomain = substr($domainName, 0, 1).str_repeat('*', max(3, strlen($domainName) - 1));

        if ($domainParts !== []) {
            $maskedDomain .= '.'.implode('.', $domainParts);
        }

        return $maskedLocal.'@'.$maskedDomain;
    }
}
