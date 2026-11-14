<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserDeviceSession;
use App\Services\UserDeviceSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestoreUserDeviceSession
{
    public function __construct(private UserDeviceSessionService $deviceSessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $clearDeviceCookie = false;
        $legacyRecaller = $guard->getRecallerName();
        $clearLegacyRecaller = $request->cookies->has($legacyRecaller);

        if ($clearLegacyRecaller) {
            $request->cookies->remove($legacyRecaller);
        }

        $user = $guard->user();
        $deviceSessionId = $request->session()->get(UserDeviceSessionService::SESSION_KEY);

        if ($deviceSessionId) {
            $deviceSession = $user instanceof User
                ? UserDeviceSession::query()
                    ->whereKey($deviceSessionId)
                    ->where('user_id', $user->getKey())
                    ->first()
                : null;

            if (! $deviceSession || ! $this->deviceSessions->activeSessionForRequest($request, $user)) {
                $request->session()->forget(UserDeviceSessionService::SESSION_KEY);

                if ($user instanceof User) {
                    $this->deviceSessions->createForRequest($user, $request);
                } else {
                    $guard->logout();
                    $clearDeviceCookie = true;
                    $user = null;
                }
            }
        } elseif ($user instanceof User) {
            $this->deviceSessions->createForRequest($user, $request);
        } else {
            $token = $request->cookie((string) config('device_sessions.cookie', 'pawhaven-device'));
            if (is_string($token) && $token !== '') {
                $restored = $this->deviceSessions->restoreFromToken($token, $request);
                $clearDeviceCookie = $restored === null;
            }
        }

        $response = $next($request);
        $authenticatedUser = $guard->user();
        if ($authenticatedUser instanceof User) {
            $currentId = $request->session()->get(UserDeviceSessionService::SESSION_KEY);
            if (! $currentId) {
                $this->deviceSessions->createForRequest($authenticatedUser, $request);
                $clearDeviceCookie = false;
            } elseif (! $this->deviceSessions->activeSessionForRequest($request, $authenticatedUser)) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $clearDeviceCookie = true;
            }
        }

        $cookie = $request->attributes->get('device_session_cookie');
        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        if ($clearLegacyRecaller) {
            $response->headers->setCookie(cookie(
                $legacyRecaller,
                '',
                -2628000,
                config('session.path', '/'),
                config('session.domain'),
                config('session.secure'),
                config('session.http_only', true),
                false,
                config('session.same_site', 'lax'),
            ));
        }

        if ($clearDeviceCookie && ! $cookie) {
            $response->headers->setCookie($this->deviceSessions->persistentCookie('', -2628000));
        }

        return $response;
    }
}
