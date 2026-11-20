<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EmailOtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_and_customer_credentials_start_an_unauthenticated_email_otp_challenge(): void
    {
        Mail::fake();

        foreach (['seller', 'customer'] as $role) {
            $user = $this->user($role);

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'SecurePassword123!',
            ])->assertRedirect(route('login.otp.show'));

            $this->assertGuest();
            $this->assertDatabaseMissing('user_device_sessions', ['user_id' => $user->id]);
            $this->assertMailContainsOtpFor($user);

            $this->post(route('login.otp.cancel'));
        }
    }

    public function test_wrong_otp_is_rejected_and_does_not_authenticate(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);

        $this->post(route('login.otp.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_expired_otp_is_rejected(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);
        $code = $this->otpFor($user);

        $this->travel(6)->minutes();
        $this->post(route('login.otp.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_otp_is_single_use_even_after_logout(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);
        $code = $this->otpFor($user);

        $this->post(route('login.otp.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        $this->post(route('login.otp.verify'), ['code' => $code])->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_otp_is_bound_to_the_current_session_challenge(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);
        $code = $this->otpFor($user);
        $challenge = session('auth.email_otp');
        $challenge['challenge_id'] = bin2hex(random_bytes(32));

        $this->withSession(['auth.email_otp' => $challenge])
            ->post(route('login.otp.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_resending_otp_invalidates_the_previous_code(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);
        $oldCode = $this->otpFor($user);

        $this->travel(61)->seconds();
        $this->post(route('login.otp.resend'))->assertSessionHas('status');
        Mail::assertSent(LoginOtpMail::class, 2);
        $newCode = $this->otpFor($user, 1);

        $this->post(route('login.otp.verify'), ['code' => $oldCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('login.otp.verify'), ['code' => $newCode])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_resend_requests_are_throttled(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);

        for ($request = 0; $request < 3; $request++) {
            $this->post(route('login.otp.resend'));
        }

        $this->post(route('login.otp.resend'))->assertStatus(429);
        $this->assertGuest();
    }

    public function test_logout_and_fresh_login_create_a_new_otp_challenge(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);
        $firstChallenge = session('auth.email_otp.challenge_id');
        $firstCode = $this->otpFor($user);
        $this->post(route('login.otp.verify'), ['code' => $firstCode])->assertRedirect(route('dashboard'));
        $this->post(route('logout'))->assertRedirect(route('public.home'));

        $this->startLogin($user);
        $secondChallenge = session('auth.email_otp.challenge_id');

        $this->assertNotSame($firstChallenge, $secondChallenge);
        Mail::assertSent(LoginOtpMail::class, 2);
        $this->assertGuest();
        $this->post(route('login.otp.verify'), ['code' => $this->otpFor($user, 1)])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_excessive_wrong_attempts_invalidate_the_challenge(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);
        $code = $this->otpFor($user);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.otp.verify'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('login.otp.verify'), ['code' => $code])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_mail_delivery_failure_fails_closed_without_leaking_an_otp(): void
    {
        $user = $this->user('customer');
        Mail::shouldReceive('to')
            ->once()
            ->with($user->email)
            ->andThrow(new RuntimeException('SMTP details must not reach the response'));

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SecurePassword123!',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertDontSee('SMTP details')
            ->assertDontSee('verification code is 123456');
        $this->assertGuest();
    }

    public function test_remember_me_does_not_skip_email_verification(): void
    {
        Mail::fake();
        $user = $this->user('customer');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SecurePassword123!',
            'remember' => 'on',
        ])->assertRedirect(route('login.otp.show'));

        $this->assertGuest();
        $this->assertTrue((bool) session('auth.email_otp.remember'));
        $this->assertMailContainsOtpFor($user);
    }

    public function test_successful_otp_creates_the_customer_session_and_consumes_hashed_code(): void
    {
        Mail::fake();
        $user = $this->user('customer');
        $this->startLogin($user);
        $code = $this->otpFor($user);
        $challenge = session('auth.email_otp');
        $stored = Cache::get("email_otp:customer_login:{$user->id}");

        $this->assertIsArray($stored);
        $this->assertNotSame($code, $stored['otp_hash']);

        $this->post(route('login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($challenge['challenge_id'], session('auth.email_otp.challenge_id'));
        $this->assertNull(Cache::get("email_otp:customer_login:{$user->id}"));
    }

    public function test_login_forms_are_empty_and_have_no_application_placeholders(): void
    {
        foreach ([route('login'), route('login', ['role' => 'seller']), route('admin.login')] as $url) {
            $response = $this->get($url)->assertOk();
            $response->assertDontSee('value="')
                ->assertDontSee('placeholder=')
                ->assertDontSee('your-email@example.test');
        }
    }

    private function startLogin(User $user): void
    {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SecurePassword123!',
        ])->assertRedirect(route('login.otp.show'));
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'email_verified_at' => now(),
            'password' => bcrypt('SecurePassword123!'),
        ]);
    }

    private function otpFor(User $user, int $index = 0): string
    {
        $codes = [];
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user, &$codes): bool {
            if ($mail->hasTo($user->email)) {
                $codes[] = $mail->otp;
            }

            return $mail->hasTo($user->email);
        });

        $this->assertArrayHasKey($index, $codes);

        return $codes[$index];
    }

    private function assertMailContainsOtpFor(User $user): void
    {
        $this->assertIsString($this->otpFor($user));
    }
}
