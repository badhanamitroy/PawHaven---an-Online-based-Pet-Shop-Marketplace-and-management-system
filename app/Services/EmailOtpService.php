<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailOtpService
{
    public const EXPIRY_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    private const LOCK_SECONDS = 15;

    /**
     * Reserve a versioned challenge under a short lock, then deliver outside it.
     *
     * @return array{success: bool, message: string, cooldown?: int}
     */
    public function generateAndSendOtp(User $user, string $purpose, string $challengeId): array
    {
        $otpKey = $this->getOtpCacheKey($user, $purpose);
        $version = bin2hex(random_bytes(16));
        $otp = sprintf('%06d', random_int(100000, 999999));
        $expiresAt = now()->addMinutes(self::EXPIRY_MINUTES);
        $challengeHash = $this->challengeHash($challengeId);

        try {
            $reserved = Cache::lock($otpKey.':lock', self::LOCK_SECONDS)->get(function () use ($challengeId, $user, $purpose, $otpKey, $version, $otp, $expiresAt, $challengeHash): array {
                $cooldownKey = $this->getCooldownCacheKey($user, $purpose);
                $remaining = (int) Cache::get($cooldownKey, 0) - now()->timestamp;

                if ($remaining > 0) {
                    return [
                        'success' => false,
                        'message' => "Please wait {$remaining} seconds before requesting another code.",
                        'cooldown' => $remaining,
                    ];
                }

                $this->clearLegacyDevelopmentOtp($user, $purpose);
                if (! Cache::put($otpKey, [
                    'otp_hash' => $this->otpHash($user, $purpose, $challengeId, $otp),
                    'challenge_hash' => $challengeHash,
                    'version' => $version,
                    'expires_at' => $expiresAt->timestamp,
                    'attempts' => 0,
                    'ready' => false,
                ], $expiresAt)) {
                    throw new \RuntimeException('Unable to reserve the verification challenge.');
                }
                if (! Cache::put($cooldownKey, now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)->timestamp, self::RESEND_COOLDOWN_SECONDS)) {
                    throw new \RuntimeException('Unable to reserve the verification cooldown.');
                }

                return ['success' => true];
            });
        } catch (Throwable $exception) {
            $this->logCacheFailure($user, $purpose, $exception);

            return $this->unavailableResult();
        }

        if (! is_array($reserved) || ! ($reserved['success'] ?? false)) {
            return is_array($reserved) ? $reserved : [
                'success' => false,
                'message' => 'Please wait a moment before requesting another code.',
            ];
        }

        try {
            Mail::to($user->email)->send(new LoginOtpMail($otp, $user));
        } catch (Throwable $exception) {
            $this->forgetChallengeVersion($user, $purpose, $challengeHash, $version);
            Log::error('Failed to deliver login verification email.', [
                'user_id' => $user->id,
                'purpose' => $purpose,
                'error_type' => get_class($exception),
            ]);

            return [
                'success' => false,
                'message' => 'We could not deliver your verification email. Please try again shortly.',
            ];
        }

        try {
            $activated = Cache::lock($otpKey.':lock', self::LOCK_SECONDS)->get(function () use ($otpKey, $challengeHash, $version): bool {
                $data = Cache::get($otpKey);
                if (! $this->isCurrentVersion($data, $challengeHash, $version)
                    || (int) $data['expires_at'] <= now()->timestamp) {
                    return false;
                }

                $data['ready'] = true;
                if (! Cache::put($otpKey, $data, max(1, (int) $data['expires_at'] - now()->timestamp))) {
                    throw new \RuntimeException('Unable to activate the verification challenge.');
                }

                return true;
            });
        } catch (Throwable $exception) {
            $this->logCacheFailure($user, $purpose, $exception);
            $this->forgetChallengeVersion($user, $purpose, $challengeHash, $version);

            return $this->unavailableResult();
        }

        if ($activated !== true) {
            return [
                'success' => false,
                'message' => 'A newer verification request replaced this code. Please use the latest email.',
            ];
        }

        Log::info('Login verification email sent.', [
            'user_id' => $user->id,
            'purpose' => $purpose,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return [
            'success' => true,
            'message' => 'A 6-digit verification code has been emailed to your address.',
        ];
    }

    /**
     * Verify and consume a submitted OTP under the same short per-account lock.
     *
     * @return array{status: 'valid'|'invalid'|'expired'|'max_attempts'|'missing'|'busy'|'unavailable', message?: string}
     */
    public function verifyOtp(User $user, string $code, string $purpose, string $challengeId): array
    {
        $otpKey = $this->getOtpCacheKey($user, $purpose);

        try {
            $result = Cache::lock($otpKey.':lock', self::LOCK_SECONDS)->get(function () use ($challengeId, $otpKey, $purpose, $user, $code): array {
                $data = Cache::get($otpKey);

                if (! is_array($data)
                    || ! isset($data['otp_hash'], $data['challenge_hash'], $data['expires_at'], $data['attempts'], $data['version'], $data['ready'])
                    || ! is_string($data['otp_hash'])
                    || ! is_string($data['challenge_hash'])
                    || ! is_string($data['version'])
                    || $data['ready'] !== true
                    || ! hash_equals($data['challenge_hash'], $this->challengeHash($challengeId))) {
                    return ['status' => 'missing', 'message' => 'No active verification code found. Please request a new code.'];
                }

                if ((int) $data['expires_at'] <= now()->timestamp) {
                    $this->forgetChallengeVersion($user, $purpose, $data['challenge_hash'], $data['version'], true);

                    return ['status' => 'expired', 'message' => 'The verification code has expired. Please request a new code.'];
                }

                $attempts = (int) $data['attempts'];
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $this->forgetChallengeVersion($user, $purpose, $data['challenge_hash'], $data['version'], true);

                    return ['status' => 'max_attempts', 'message' => 'Maximum verification attempts exceeded. Please request a new code.'];
                }

                $matches = preg_match('/\A[0-9]{6}\z/D', $code) === 1
                    && hash_equals($data['otp_hash'], $this->otpHash($user, $purpose, $challengeId, $code));

                if ($matches) {
                    $data['ready'] = false;
                    if (! Cache::put($otpKey, $data, max(1, (int) $data['expires_at'] - now()->timestamp))) {
                        throw new \RuntimeException('Unable to consume the verification challenge.');
                    }
                    $this->forgetChallengeVersion($user, $purpose, $data['challenge_hash'], $data['version'], true);
                    Log::info('Login verification code accepted.', ['user_id' => $user->id, 'purpose' => $purpose]);

                    return ['status' => 'valid'];
                }

                $attempts++;
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $data['attempts'] = $attempts;
                    $data['ready'] = false;
                    if (! Cache::put($otpKey, $data, max(1, (int) $data['expires_at'] - now()->timestamp))) {
                        throw new \RuntimeException('Unable to invalidate the exhausted verification challenge.');
                    }
                    $this->forgetChallengeVersion($user, $purpose, $data['challenge_hash'], $data['version'], true);

                    return ['status' => 'max_attempts', 'message' => 'Maximum verification attempts exceeded. Please request a new code.'];
                }

                $data['attempts'] = $attempts;
                try {
                    if (! Cache::put($otpKey, $data, max(1, (int) $data['expires_at'] - now()->timestamp))) {
                        throw new \RuntimeException('Unable to record the verification attempt.');
                    }
                } catch (Throwable $exception) {
                    $this->forgetChallengeVersion($user, $purpose, $data['challenge_hash'], $data['version'], true);

                    throw $exception;
                }
                $remaining = self::MAX_ATTEMPTS - $attempts;

                return [
                    'status' => 'invalid',
                    'message' => "The verification code is incorrect. {$remaining} attempt".($remaining === 1 ? '' : 's').' remaining.',
                ];
            });
        } catch (Throwable $exception) {
            $this->logCacheFailure($user, $purpose, $exception);

            return ['status' => 'unavailable', 'message' => 'Verification is temporarily unavailable. Please try again.'];
        }

        return is_array($result)
            ? $result
            : ['status' => 'busy', 'message' => 'Verification is already being processed. Please try again.'];
    }

    public function getRemainingCooldown(User $user, string $purpose): int
    {
        try {
            return max(0, (int) Cache::get($this->getCooldownCacheKey($user, $purpose), 0) - now()->timestamp);
        } catch (Throwable $exception) {
            $this->logCacheFailure($user, $purpose, $exception);

            return self::RESEND_COOLDOWN_SECONDS;
        }
    }

    public function clearOtp(User $user, string $purpose, string $challengeId): void
    {
        $otpKey = $this->getOtpCacheKey($user, $purpose);

        try {
            Cache::lock($otpKey.':lock', self::LOCK_SECONDS)->get(function () use ($challengeId, $otpKey, $purpose, $user): void {
                $data = Cache::get($otpKey);
                if (is_array($data)
                    && isset($data['challenge_hash'])
                    && is_string($data['challenge_hash'])
                    && hash_equals($data['challenge_hash'], $this->challengeHash($challengeId))) {
                    $this->forgetChallengeData($user, $purpose);
                }

                $this->clearLegacyDevelopmentOtp($user, $purpose);
            });
        } catch (Throwable $exception) {
            $this->logCacheFailure($user, $purpose, $exception);
        }
    }

    private function forgetChallengeVersion(User $user, string $purpose, string $challengeHash, string $version, bool $lockHeld = false): void
    {
        $otpKey = $this->getOtpCacheKey($user, $purpose);

        try {
            $clear = function () use ($user, $purpose, $otpKey, $challengeHash, $version): void {
                $data = Cache::get($otpKey);
                if ($this->isCurrentVersion($data, $challengeHash, $version)) {
                    $this->forgetChallengeData($user, $purpose);
                }
            };

            if ($lockHeld) {
                $clear();
            } else {
                Cache::lock($otpKey.':lock', self::LOCK_SECONDS)->get($clear);
            }
        } catch (Throwable $exception) {
            $this->logCacheFailure($user, $purpose, $exception);

            if ($lockHeld) {
                throw $exception;
            }
        }
    }

    private function forgetChallengeData(User $user, string $purpose): void
    {
        Cache::forget($this->getOtpCacheKey($user, $purpose));
        Cache::forget($this->getCooldownCacheKey($user, $purpose));
        $this->clearLegacyDevelopmentOtp($user, $purpose);
    }

    private function isCurrentVersion(mixed $data, string $challengeHash, string $version): bool
    {
        return is_array($data)
            && isset($data['challenge_hash'], $data['version'])
            && is_string($data['challenge_hash'])
            && is_string($data['version'])
            && hash_equals($data['challenge_hash'], $challengeHash)
            && hash_equals($data['version'], $version);
    }

    private function challengeHash(string $challengeId): string
    {
        return hash_hmac('sha256', $challengeId, $this->applicationKey());
    }

    private function otpHash(User $user, string $purpose, string $challengeId, string $otp): string
    {
        return hash_hmac('sha256', $user->getKey().'|'.$purpose.'|'.$challengeId.'|'.$otp, $this->applicationKey());
    }

    private function applicationKey(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('The application encryption key is not configured.');
        }

        return $key;
    }

    private function clearLegacyDevelopmentOtp(User $user, string $purpose): void
    {
        Cache::forget("email_otp_dev:{$user->id}:{$purpose}");
    }

    private function logCacheFailure(User $user, string $purpose, Throwable $exception): void
    {
        Log::warning('Email verification challenge storage is unavailable.', [
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'error_type' => get_class($exception),
            'error_location' => basename($exception->getFile()).':'.$exception->getLine(),
        ]);
    }

    /** @return array{success: false, message: string} */
    private function unavailableResult(): array
    {
        return ['success' => false, 'message' => 'Verification is temporarily unavailable. Please try again.'];
    }

    private function getOtpCacheKey(User $user, string $purpose): string
    {
        return "email_otp:{$purpose}:{$user->getKey()}";
    }

    private function getCooldownCacheKey(User $user, string $purpose): string
    {
        return "email_otp_cooldown:{$purpose}:{$user->getKey()}";
    }
}
