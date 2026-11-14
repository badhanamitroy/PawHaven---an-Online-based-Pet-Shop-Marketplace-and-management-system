<?php

namespace App\Actions\Fortify;

use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class AuthenticateWithoutLegacyRecaller
{
    public function __construct(
        private StatefulGuard $guard,
        private LoginRateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->guard->attempt($request->only(Fortify::username(), 'password'), false)) {
            return $next($request);
        }

        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            Fortify::username() => [trans('auth.failed')],
        ]);
    }
}
