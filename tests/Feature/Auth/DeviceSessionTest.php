<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Models\UserDeviceSession;
use App\Services\UserDeviceSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_a_persistent_device_record_without_a_laravel_remember_token(): void
    {
        Mail::fake();
        $user = $this->customer();
        $originalRememberToken = $user->remember_token;
        $originalSessionId = session()->getId();

        $challenge = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SecurePassword123!',
        ]);

        $challenge->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
        $response = $this->verifyOtp($user);
        $response->assertRedirect(route('dashboard'));
        $this->assertNotSame($originalSessionId, $response->getSession()->getId());
        $device = UserDeviceSession::query()->where('user_id', $user->id)->sole();

        $this->assertSame(64, strlen($device->token_hash));
        $this->assertNotSame('SecurePassword123!', $device->token_hash);
        $this->assertNull($device->previous_token_hash);
        $this->assertSame('desktop', $device->device_type);
        $this->assertTrue($device->expires_at->isFuture());
        $this->assertSame($originalRememberToken, $user->fresh()->remember_token);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('device_sessions.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $this->assertGreaterThan(now()->timestamp, $cookie->getExpiresTime());
    }

    public function test_seller_login_also_creates_a_persistent_device_record(): void
    {
        Mail::fake();
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $challenge = $this->post(route('login.store'), [
            'email' => $seller->email,
            'password' => 'SecurePassword123!',
        ]);

        $challenge->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
        $response = $this->verifyOtp($seller);
        $response->assertRedirect(route('seller.dashboard'));
        $this->assertDatabaseHas('user_device_sessions', [
            'user_id' => $seller->id,
            'revoked_at' => null,
        ]);
    }

    public function test_device_cookie_restores_authentication_after_session_expiration_and_rotates(): void
    {
        $user = $this->customer();
        $token = 'first-device-secret-which-is-never-stored-in-plaintext';
        $device = $this->createDevice($user, $token);
        $previousHash = $device->token_hash;

        $this->withCookie(config('device_sessions.cookie'), $token)
            ->get(route('dashboard'))
            ->assertOk();

        $device->refresh();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousHash, $device->token_hash);
        $this->assertSame($previousHash, $device->previous_token_hash);
        $this->assertNotNull($device->last_rotated_at);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_logout_revokes_the_current_device_and_its_old_cookie_cannot_restore_access(): void
    {
        $user = $this->customer();
        $token = 'logout-device-secret-never-stored-in-plaintext';
        $device = $this->createDevice($user, $token);

        $response = $this->withCookie(config('device_sessions.cookie'), $token)
            ->get(route('dashboard'))
            ->assertOk();

        $this->post(route('logout'))
            ->assertRedirect(route('public.home'));

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->flushSession();
        $this->withCookie(config('device_sessions.cookie'), $token)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_revoking_one_device_does_not_invalidate_another_device(): void
    {
        $user = $this->customer();
        $tokenA = 'browser-profile-a-token';
        $tokenB = 'browser-profile-b-token';
        $deviceA = $this->createDevice($user, $tokenA);
        $deviceB = $this->createDevice($user, $tokenB);

        app(UserDeviceSessionService::class)->revoke($deviceA);

        $this->withCookie(config('device_sessions.cookie'), $tokenA)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->flushSession();
        $this->withCookie(config('device_sessions.cookie'), $tokenB)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertNotNull($deviceA->fresh()->revoked_at);
        $this->assertNull($deviceB->fresh()->revoked_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_device_list_and_revoke_routes_enforce_ownership(): void
    {
        $owner = $this->customer();
        $otherUser = $this->customer();
        $foreignDevice = $this->createDevice($otherUser, 'foreign-device-token');

        $this->actingAs($owner)
            ->withSession([UserDeviceSessionService::POLICY_PROOF_SESSION_KEY => $owner->id])
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Active devices');

        $this->delete(route('devices.revoke', $foreignDevice->id))
            ->assertNotFound();

        $this->assertNull($foreignDevice->fresh()->revoked_at);
    }

    public function test_logout_all_other_devices_keeps_the_current_device_active(): void
    {
        $user = $this->customer();
        $otherDevice = $this->createDevice($user, 'other-device-token');

        $this->actingAs($user)
            ->withSession([UserDeviceSessionService::POLICY_PROOF_SESSION_KEY => $user->id])
            ->get(route('devices.index'))->assertOk();
        $currentId = (int) session(UserDeviceSessionService::SESSION_KEY);
        $currentDevice = UserDeviceSession::query()->findOrFail($currentId);

        $this->delete(route('devices.revoke-others'))
            ->assertRedirect();

        $this->assertNull($currentDevice->fresh()->revoked_at);
        $this->assertNotNull($otherDevice->fresh()->revoked_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_all_devices_requires_confirmation_and_revokes_every_token(): void
    {
        $user = $this->customer();
        $otherDevice = $this->createDevice($user, 'other-all-device-token');

        $this->actingAs($user)
            ->withSession([UserDeviceSessionService::POLICY_PROOF_SESSION_KEY => $user->id])
            ->get(route('devices.index'))->assertOk();
        $currentDevice = UserDeviceSession::query()
            ->whereKey(session(UserDeviceSessionService::SESSION_KEY))
            ->firstOrFail();

        $this->delete(route('devices.revoke-all'), [
            'confirmation' => 'wrong phrase',
        ])->assertSessionHasErrors('confirmation');

        $this->delete(route('devices.revoke-all'), [
            'confirmation' => 'LOG OUT ALL DEVICES',
        ])->assertRedirect(route('public.home'));

        $this->assertGuest();
        $this->assertNotNull($currentDevice->fresh()->revoked_at);
        $this->assertNotNull($otherDevice->fresh()->revoked_at);
    }

    public function test_password_change_revokes_all_device_sessions(): void
    {
        $user = $this->customer();
        $device = $this->createDevice($user, 'password-change-device-token');

        $user->forceFill(['password' => 'ChangedPassword123!'])->save();

        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_password_reset_revokes_all_device_sessions(): void
    {
        $user = $this->customer();
        $device = $this->createDevice($user, 'password-reset-device-token');

        app(ResetUserPassword::class)->reset($user, [
            'password' => 'ResetPassword123!',
            'password_confirmation' => 'ResetPassword123!',
        ]);

        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_expired_device_token_and_replayed_rotated_token_cannot_restore_authentication(): void
    {
        $user = $this->customer();
        $expiredToken = 'expired-device-token-secret';
        $expiredDevice = $this->createDevice($user, $expiredToken);
        $expiredDevice->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withCookie(config('device_sessions.cookie'), $expiredToken)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $rotatedToken = 'token-for-rotation-replay-check';
        $rotatedDevice = $this->createDevice($user, $rotatedToken);
        $this->flushSession();
        $this->withCookie(config('device_sessions.cookie'), $rotatedToken)
            ->get(route('dashboard'))
            ->assertOk();

        $this->travel(31)->seconds();
        Auth::forgetGuards();
        $this->flushSession();
        $this->withCookie(config('device_sessions.cookie'), $rotatedToken)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue($rotatedDevice->fresh()->previous_token_valid_until->isPast());
    }

    public function test_legacy_unproven_device_hash_cannot_restore_a_session(): void
    {
        $user = $this->customer();
        $legacyToken = 'old-unproven-device-cookie';
        $device = $this->createDevice($user, $legacyToken);
        $device->forceFill(['token_hash' => hash('sha256', $legacyToken)])->save();

        $this->withCookie(config('device_sessions.cookie'), $legacyToken)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session(UserDeviceSessionService::POLICY_PROOF_SESSION_KEY));
    }

    public function test_authenticated_session_without_email_otp_proof_does_not_mint_a_trusted_device(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertDatabaseMissing('user_device_sessions', ['user_id' => $user->id]);
        $this->assertNull(session(UserDeviceSessionService::SESSION_KEY));
    }

    private function customer(): User
    {
        return User::factory()->create([
            'role' => 'customer',
            'email_verified_at' => now(),
            'password' => bcrypt('SecurePassword123!'),
        ]);
    }

    private function verifyOtp(User $user): TestResponse
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user, &$code): bool {
            $code = $mail->otp;

            return $mail->hasTo($user->email);
        });

        $this->assertIsString($code);

        return $this->post(route('login.otp.verify'), ['code' => $code]);
    }

    private function createDevice(User $user, string $token): UserDeviceSession
    {
        return $user->deviceSessions()->create([
            'token_hash' => app(UserDeviceSessionService::class)->hashToken($token),
            'device_name' => 'Linux · Chrome',
            'browser_name' => 'Chrome',
            'browser_version' => '123.0',
            'platform' => 'Linux',
            'device_type' => 'desktop',
            'ip_address' => '192.0.2.10',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
