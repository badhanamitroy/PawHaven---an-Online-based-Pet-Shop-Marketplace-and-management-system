<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('login.otp.show'));

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'customer',
        ]);

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code): bool {
            $code = $mail->otp;

            return $mail->hasTo('test@example.com');
        });

        $this->post(route('login.otp.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_public_registration_cannot_assign_a_seller_or_admin_role(): void
    {
        foreach (['seller', 'admin'] as $role) {
            $email = "{$role}@example.com";

            $this->post(route('register.store'), [
                'name' => 'Acme Pet Supplies',
                'email' => $email,
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => $role,
            ])->assertSessionHasErrors('role');

            $this->assertDatabaseMissing('users', ['email' => $email]);
            $this->assertGuest();
        }
    }

    public function test_seller_portal_routes_redirect_correctly(): void
    {
        $this->get(route('seller.login'))
            ->assertRedirect(route('login', ['role' => 'seller']));

        $this->get(route('seller.register'))
            ->assertRedirect(route('register'));

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee('Seller Account')
            ->assertDontSee('Seller &amp; Merchant Onboarding');
    }
}
