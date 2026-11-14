<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class RejectPrivilegedFortifyLogin
{
    public function __construct(private StatefulGuard $guard) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $provider = $this->guard->getProvider();
        $user = $provider->retrieveByCredentials($request->only(Fortify::username(), 'password'));

        if ($user instanceof User
            && $provider->validateCredentials($user, ['password' => $request->input('password')])) {
            if (! ($user->isAdmin() || $user->isStaff() || $user->isSeller() || $user->isCustomer())) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['This account has an unsupported role and cannot sign in.'],
                ]);
            }

            if (! ($user->isAdmin() || $user->isStaff())) {
                return $next($request);
            }

            throw ValidationException::withMessages([
                Fortify::username() => ['Admin and Staff accounts must sign in through the secure internal login.'],
            ]);
        }

        return $next($request);
    }
}
