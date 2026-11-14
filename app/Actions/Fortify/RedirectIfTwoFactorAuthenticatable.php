<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\UserDeviceSessionService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable as RedirectsIfTwoFactorAuthenticatableContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class RedirectIfTwoFactorAuthenticatable implements RedirectsIfTwoFactorAuthenticatableContract
{
    /**
     * The guard implementation.
     *
     * @var StatefulGuard
     */
    protected $guard;

    /**
     * The login rate limiter instance.
     *
     * @var LoginRateLimiter
     */
    protected $limiter;

    /**
     * The Email OTP service.
     *
     * @var EmailOtpService
     */
    protected $otpService;

    public function __construct(
        StatefulGuard $guard,
        LoginRateLimiter $limiter,
        EmailOtpService $otpService
    ) {
        $this->guard = $guard;
        $this->limiter = $limiter;
        $this->otpService = $otpService;
    }

    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        /** @var User $user */
        $user = $this->validateCredentials($request);

        $purpose = $user->isSeller() ? 'seller_login' : 'customer_login';
        $request->session()->forget(UserDeviceSessionService::POLICY_PROOF_SESSION_KEY);
        $challengeId = bin2hex(random_bytes(32));
        $previousChallenge = $request->session()->get('auth.email_otp');

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
        $request->session()->put('auth.email_otp', [
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'challenge_id' => $challengeId,
            'remember' => $request->boolean('remember'),
            'requires_totp' => $user->two_factor_confirmed_at !== null,
        ]);

        $sendResult = $this->otpService->generateAndSendOtp($user, $purpose, $challengeId);

        if (! $sendResult['success']) {
            $request->session()->forget('auth.email_otp');

            throw ValidationException::withMessages([
                Fortify::username() => [$sendResult['message']],
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['email_otp' => true]);
        }

        return redirect()->route('login.otp.show');
    }

    /**
     * Attempt to validate the incoming credentials.
     *
     * @param  Request  $request
     * @return mixed
     */
    protected function validateCredentials($request)
    {
        if (Fortify::$authenticateUsingCallback) {
            return tap(call_user_func(Fortify::$authenticateUsingCallback, $request), function ($user) use ($request) {
                if (! $user) {
                    $this->fireFailedEvent($request);
                    $this->throwFailedAuthenticationException($request);
                }
            });
        }

        $provider = $this->guard->getProvider();

        return (new Timebox)->call(function () use ($request, $provider) {
            return tap($provider->retrieveByCredentials($request->only(Fortify::username(), 'password')), function ($user) use ($provider, $request) {
                if (! $user || ! $provider->validateCredentials($user, ['password' => $request->password])) {
                    $this->fireFailedEvent($request, $user);
                    $this->throwFailedAuthenticationException($request);
                }
            });
        }, 200 * 1000);
    }

    /**
     * Throw a failed authentication validation exception.
     *
     * @param  Request  $request
     * @return void
     *
     * @throws ValidationException
     */
    protected function throwFailedAuthenticationException($request)
    {
        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            Fortify::username() => [trans('auth.failed')],
        ]);
    }

    /**
     * Fire the failed authentication attempt event with the given arguments.
     *
     * @param  Request  $request
     * @param  Authenticatable|null  $user
     * @return void
     */
    protected function fireFailedEvent($request, $user = null)
    {
        event(new Failed(config('fortify.guard', 'web'), $user, [
            Fortify::username() => $request->{Fortify::username()},
        ]));
    }
}
