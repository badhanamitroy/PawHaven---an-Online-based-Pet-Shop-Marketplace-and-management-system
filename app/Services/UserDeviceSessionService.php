<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDeviceSession;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cookie\Factory as CookieFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class UserDeviceSessionService
{
    public const SESSION_KEY = 'auth.device_session_id';

    public const POLICY_PROOF_SESSION_KEY = 'auth.email_otp_verified_user_id';

    public function __construct(private CookieFactory $cookies) {}

    public function createForRequest(User $user, Request $request): ?UserDeviceSession
    {
        if (! $this->hasPolicyProof($user, $request)) {
            return null;
        }

        $token = $this->newToken();
        $now = now();
        $metadata = $this->deviceMetadata($request);

        $deviceSession = DB::transaction(function () use ($user, $request, $token, $now, $metadata): UserDeviceSession {
            $previousId = $request->session()->get(self::SESSION_KEY);
            if ($previousId) {
                UserDeviceSession::query()
                    ->where('user_id', $user->getKey())
                    ->whereKey($previousId)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => $now]);
            }

            $deviceSession = $user->deviceSessions()->create([
                'token_hash' => $this->hashToken($token),
                ...$metadata,
                'last_activity_at' => $now,
                'expires_at' => $this->expiresAt($now),
            ]);

            $request->session()->put(self::SESSION_KEY, $deviceSession->getKey());

            return $deviceSession;
        });

        $request->attributes->set('device_session_cookie', $this->persistentCookie($token));

        return $deviceSession;
    }

    public function markPolicyVerified(User $user, Request $request): void
    {
        $request->session()->put(self::POLICY_PROOF_SESSION_KEY, $user->getKey());
    }

    public function restoreFromToken(string $token, Request $request): ?UserDeviceSession
    {
        $hash = $this->hashToken($token);
        $deviceSession = $this->activeSessions()
            ->where(function (Builder $query) use ($hash): void {
                $query->where('token_hash', $hash)
                    ->orWhere(function (Builder $query) use ($hash): void {
                        $query->where('previous_token_hash', $hash)
                            ->where('previous_token_valid_until', '>', now());
                    });
            })
            ->first();

        if (! $deviceSession || $deviceSession->last_activity_at->lt(now()->subDays($this->idleDays()))) {
            return null;
        }

        $rotation = DB::transaction(function () use ($deviceSession, $hash): string|false {
            $locked = UserDeviceSession::query()->lockForUpdate()->find($deviceSession->getKey());

            if (! $locked || ! $this->isActive($locked)) {
                return false;
            }

            if (hash_equals($locked->token_hash, $hash)) {
                $newToken = $this->newToken();
                $locked->forceFill([
                    'previous_token_hash' => $locked->token_hash,
                    'previous_token_valid_until' => now()->addSeconds(
                        (int) config('device_sessions.rotation_grace_seconds', 30),
                    ),
                    'token_hash' => $this->hashToken($newToken),
                    'last_rotated_at' => now(),
                ])->save();

                return $newToken;
            }

            if ($locked->previous_token_hash && hash_equals($locked->previous_token_hash, $hash)) {
                return 'grace';
            }

            return false;
        });

        if ($rotation === false) {
            return null;
        }

        $user = $deviceSession->user;
        if (! $user) {
            return null;
        }

        $deviceSession->refresh();
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $deviceSession->getKey());
        $this->markPolicyVerified($user, $request);
        auth()->guard('web')->login($user, false);
        $this->touch($deviceSession, $request);

        if ($rotation !== 'grace') {
            $request->attributes->set('device_session_cookie', $this->persistentCookie($rotation));
        }

        return $deviceSession;
    }

    public function activeSessionForRequest(Request $request, User $user): ?UserDeviceSession
    {
        $id = $request->session()->get(self::SESSION_KEY);
        if (! $id) {
            return null;
        }

        $deviceSession = UserDeviceSession::query()
            ->whereKey($id)
            ->where('user_id', $user->getKey())
            ->first();

        if (! $deviceSession || ! $this->isActive($deviceSession)) {
            return null;
        }

        $this->touch($deviceSession, $request);

        return $deviceSession;
    }

    public function activeSessions(): Builder
    {
        return UserDeviceSession::query()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function revoke(UserDeviceSession $deviceSession): void
    {
        DB::transaction(function () use ($deviceSession): void {
            UserDeviceSession::query()
                ->whereKey($deviceSession->getKey())
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });
    }

    public function revokeAllForUser(int $userId, ?int $exceptId = null): void
    {
        DB::transaction(function () use ($userId, $exceptId): void {
            $query = UserDeviceSession::query()
                ->where('user_id', $userId)
                ->whereNull('revoked_at');

            if ($exceptId !== null) {
                $query->where('id', '<>', $exceptId);
            }

            $query->update(['revoked_at' => now()]);
        });
    }

    public function revokeCurrentFromLogoutEvent(): void
    {
        $request = request();
        if (! $request->hasSession()) {
            return;
        }

        $id = $request->session()->get(self::SESSION_KEY);
        if (! $id) {
            return;
        }

        DB::transaction(function () use ($id): void {
            UserDeviceSession::query()
                ->whereKey($id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });

        $this->queueExpiredCookie();
    }

    public function queueExpiredCookie(): void
    {
        $this->cookies->queue($this->persistentCookie('', -2628000));
    }

    public function persistentCookie(string $token, ?int $minutes = null): Cookie
    {
        $minutes ??= $this->idleDays() * 24 * 60;

        return $this->cookies->make(
            (string) config('device_sessions.cookie', 'pawhaven-device'),
            $token,
            $minutes,
            (string) config('session.path', '/'),
            config('session.domain'),
            config('session.secure'),
            (bool) config('session.http_only', true),
            false,
            (string) config('session.same_site', 'lax'),
        );
    }

    public function hashToken(string $token): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('The application encryption key is not configured.');
        }

        return hash_hmac('sha256', 'pawhaven-device-session|'.$token, $key);
    }

    public function idleDays(): int
    {
        return max(1, (int) config('device_sessions.idle_days', 30));
    }

    private function isActive(UserDeviceSession $deviceSession): bool
    {
        return $deviceSession->revoked_at === null
            && $deviceSession->expires_at->isFuture()
            && $deviceSession->last_activity_at->gte(now()->subDays($this->idleDays()));
    }

    private function hasPolicyProof(User $user, Request $request): bool
    {
        return (string) $request->session()->get(self::POLICY_PROOF_SESSION_KEY) === (string) $user->getKey();
    }

    private function touch(UserDeviceSession $deviceSession, Request $request): void
    {
        $now = now();
        $updates = [
            'expires_at' => $this->expiresAt($now),
            'ip_address' => $request->ip(),
        ];

        if ($deviceSession->last_activity_at->addMinutes(5)->lte($now)) {
            $updates['last_activity_at'] = $now;
        }

        $deviceSession->forceFill($updates)->save();

        $token = $request->cookie((string) config('device_sessions.cookie', 'pawhaven-device'));
        if ($token && hash_equals($deviceSession->token_hash, $this->hashToken($token))) {
            $request->attributes->set('device_session_cookie', $this->persistentCookie($token));
        }
    }

    /**
     * @return array{device_name: string, browser_name: string|null, browser_version: string|null, platform: string|null, device_type: string, ip_address: string|null}
     */
    private function deviceMetadata(Request $request): array
    {
        $agent = substr((string) $request->userAgent(), 0, 2048);
        [$browser, $version] = match (true) {
            preg_match('/Edg\/([\d.]+)/', $agent, $matches) === 1 => ['Edge', $matches[1]],
            preg_match('/Firefox\/([\d.]+)/', $agent, $matches) === 1 => ['Firefox', $matches[1]],
            preg_match('/Chrome\/([\d.]+)/', $agent, $matches) === 1 => ['Chrome', $matches[1]],
            preg_match('/Version\/([\d.]+).*Safari/', $agent, $matches) === 1 => ['Safari', $matches[1]],
            default => ['Unknown browser', null],
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown platform',
        };

        $deviceType = match (true) {
            str_contains($agent, 'iPad'), str_contains($agent, 'Tablet') => 'tablet',
            str_contains($agent, 'Mobile'), str_contains($agent, 'iPhone'), str_contains($agent, 'Android') => 'mobile',
            default => 'desktop',
        };

        return [
            'device_name' => Str::limit($platform.' · '.$browser, 120, ''),
            'browser_name' => $browser,
            'browser_version' => $version ? Str::limit($version, 30, '') : null,
            'platform' => $platform,
            'device_type' => $deviceType,
            'ip_address' => $request->ip(),
        ];
    }

    private function expiresAt(CarbonInterface $from): CarbonInterface
    {
        return $from->copy()->addDays($this->idleDays());
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
