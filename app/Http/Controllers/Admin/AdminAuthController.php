<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\UserDeviceSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;

class AdminAuthController extends Controller
{
    private const CHALLENGE_SESSION_KEY = 'auth.email_otp';

    public function __construct(
        protected EmailOtpService $otpService
    ) {}

    /**
     * Display the dedicated internal admin login view.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($user->isAdmin() || $user->isStaff()) {
                return redirect()->route('admin.dashboard');
            }

            if ($user->isSeller()) {
                return redirect()->route('seller.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return view('admin.auth.login');
    }

    /**
     * Handle the primary administrative credential verification step.
     *
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->forget(UserDeviceSessionService::POLICY_PROOF_SESSION_KEY);

        // Enforce administrative privileges
        if (! ($user->isAdmin() || $user->isStaff())) {
            throw ValidationException::withMessages([
                'email' => 'Access denied. This account does not possess administrative privileges.',
            ]);
        }

        if ($user->isStaff() && ! $user->isAdmin()
            && ! app(\App\Services\BusinessAccessService::class)->hasBusinessAccess($user)) {
            throw ValidationException::withMessages([
                'email' => 'This employee account is awaiting an active Shop Owner approved assignment.',
            ]);
        }

        $previousChallenge = $request->session()->get(self::CHALLENGE_SESSION_KEY);
        if (is_array($previousChallenge)
            && isset($previousChallenge['user_id'], $previousChallenge['purpose'], $previousChallenge['challenge_id'])
            && (int) $previousChallenge['user_id'] === (int) $user->getKey()
            && is_string($previousChallenge['purpose'])
            && is_string($previousChallenge['challenge_id'])) {
            $this->otpService->clearOtp(
                $user,
                $previousChallenge['purpose'],
                $previousChallenge['challenge_id'],
            );
        }

        $request->session()->regenerate();
        $challengeId = bin2hex(random_bytes(32));
        $request->session()->put(self::CHALLENGE_SESSION_KEY, [
            'user_id' => $user->getKey(),
            'purpose' => 'admin_login',
            'challenge_id' => $challengeId,
            'remember' => false,
            'requires_totp' => Features::enabled(Features::twoFactorAuthentication())
                && $user->two_factor_confirmed_at !== null,
        ]);

        $otpResult = $this->otpService->generateAndSendOtp($user, 'admin_login', $challengeId);

        if (! $otpResult['success']) {
            $request->session()->forget(self::CHALLENGE_SESSION_KEY);

            return back()->withErrors(['email' => $otpResult['message']]);
        }

        return redirect()->route('admin.two-factor');
    }

    /**
     * Display the administrative 2FA verification screen.
     */
    public function showTwoFactorForm(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingAdminChallenge($request);
        if ($pending === null) {
            $this->clearAdminChallenge($request);

            return redirect()->route('admin.login');
        }

        /** @var User $user */
        $user = $pending['user'];
        $maskedEmail = $this->maskEmail($user->email);
        $cooldown = $this->otpService->getRemainingCooldown($user, 'admin_login');

        return view('auth.email-otp-challenge', [
            'accountLabel' => 'Administration',
            'maskedEmail' => $maskedEmail,
            'cooldown' => $cooldown,
            'verifyRoute' => 'admin.two-factor.store',
            'resendRoute' => 'admin.two-factor.resend',
            'cancelRoute' => 'admin.two-factor.cancel',
        ]);
    }

    /**
     * Verify the administrative 2FA OTP code.
     */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D'],
        ]);

        $pending = $this->pendingAdminChallenge($request);
        if ($pending === null) {
            $this->clearAdminChallenge($request);

            return redirect()->route('admin.login');
        }

        /** @var User $user */
        $user = $pending['user'];

        if (! $user->isAdmin() && ! $user->isStaff()) {
            $this->clearAdminChallenge($request, $user);

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This account no longer has administrative privileges.']);
        }

        if ($user->isStaff() && ! $user->isAdmin()
            && ! app(\App\Services\BusinessAccessService::class)->hasBusinessAccess($user)) {
            $this->clearAdminChallenge($request, $user);

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This employee account is awaiting an active Shop Owner approved assignment.']);
        }

        $code = (string) $request->input('code');

        $result = $this->otpService->verifyOtp($user, $code, 'admin_login', $pending['challenge_id']);

        if ($result['status'] !== 'valid') {
            return back()->withErrors([
                'code' => $result['message'] ?? 'The verification code is invalid.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Complete the mandatory email step before continuing to Fortify's TOTP step.
        $request->session()->forget(self::CHALLENGE_SESSION_KEY);
        $request->session()->regenerate();
        app(UserDeviceSessionService::class)->markPolicyVerified($user, $request);

        if (Features::enabled(Features::twoFactorAuthentication())
            && $user->two_factor_confirmed_at !== null) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        $destination = $this->authorizedAdminIntendedUrl($request, $user) ?? route('admin.dashboard');
        $request->session()->forget('url.intended');

        return redirect()->to($destination);
    }

    /**
     * Resend a fresh 2FA code to the administrator's email.
     */
    public function resendTwoFactor(Request $request): RedirectResponse
    {
        $pending = $this->pendingAdminChallenge($request);
        if ($pending === null) {
            $this->clearAdminChallenge($request);

            return redirect()->route('admin.login');
        }

        /** @var User $user */
        $user = $pending['user'];

        $result = $this->otpService->generateAndSendOtp($user, 'admin_login', $pending['challenge_id']);

        if (! $result['success']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        return back()->with('status', 'A fresh 6-digit verification code has been dispatched to your email.');
    }

    /**
     * Cancel the pending 2FA challenge and return to login.
     */
    public function cancelTwoFactor(Request $request): RedirectResponse
    {
        $this->clearAdminChallenge($request);
        $request->session()->forget('url.intended');
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }

    /**
     * Destroy an authenticated admin session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.home');
    }

    private function authorizedAdminIntendedUrl(Request $request, User $user): ?string
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $url = parse_url($intended);
        $path = $url['path'] ?? null;
        $expectedHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($path)
            || ! ($path === '/admin' || str_starts_with($path, '/admin/'))
            || str_starts_with($path, '//')
            || (isset($url['host']) && strtolower($url['host']) !== strtolower((string) $expectedHost))) {
            return null;
        }

        if ($user->isStaff() && preg_match('#^/admin/(brand|homepage|marketplace/(moderation|categories)|users|audit-logs|system|settings)(/|$)#', $path)) {
            return null;
        }

        return $intended;
    }

    /**
     * Mask an email address for safe frontend presentation.
     */
    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        if (strlen($name) <= 3) {
            $maskedName = substr($name, 0, 1).str_repeat('*', max(1, strlen($name) - 1));
        } else {
            $maskedName = substr($name, 0, 2).str_repeat('*', strlen($name) - 3).substr($name, -1);
        }

        return $maskedName.'@'.$domain;
    }

    /**
     * @return array{user: User, challenge_id: string}|null
     */
    private function pendingAdminChallenge(Request $request): ?array
    {
        $challenge = $request->session()->get(self::CHALLENGE_SESSION_KEY);
        if (! is_array($challenge)
            || ! isset($challenge['user_id'], $challenge['purpose'], $challenge['challenge_id'])
            || ! is_numeric($challenge['user_id'])
            || $challenge['purpose'] !== 'admin_login'
            || ! is_string($challenge['challenge_id'])) {
            return null;
        }

        $user = User::query()->find((int) $challenge['user_id']);
        if (! $user) {
            return null;
        }

        return [
            'user' => $user,
            'challenge_id' => $challenge['challenge_id'],
        ];
    }

    private function clearAdminChallenge(Request $request, ?User $user = null): void
    {
        $challenge = $request->session()->get(self::CHALLENGE_SESSION_KEY);
        if ($user === null && is_array($challenge) && isset($challenge['user_id'])) {
            $user = User::query()->find((int) $challenge['user_id']);
        }

        if ($user
            && is_array($challenge)
            && isset($challenge['purpose'], $challenge['challenge_id'])
            && $challenge['purpose'] === 'admin_login'
            && is_string($challenge['challenge_id'])) {
            $this->otpService->clearOtp($user, 'admin_login', $challenge['challenge_id']);
        }

        $request->session()->forget([
            self::CHALLENGE_SESSION_KEY,
            'login.id',
            'login.remember',
        ]);
    }
}
