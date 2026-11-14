<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use App\Mail\LoginOtpMail;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertDontSee('your-email@pawhaven.com')
            ->assertDontSee('placeholder=')
            ->assertDontSee('value="');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('login.otp.show'));

        $this->assertGuest();
        $code = $this->sentOtpFor($user);

        $this->post(route('login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Mail::fake();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login.otp.show'));
        $this->assertGuest();

        $this->post(route('login.otp.verify'), [
            'code' => $this->sentOtpFor($user),
        ])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('public.home'));

        $this->assertGuest();
    }

    public function test_login_validation_does_not_repopulate_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee($user->email)
            ->assertDontSee('wrong-password')
            ->assertDontSee('value="');
    }

    private function sentOtpFor(User $user): string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user, &$code): bool {
            $code = $mail->otp;

            return $mail->hasTo($user->email);
        });

        $this->assertIsString($code);

        return $code;
    }
}
