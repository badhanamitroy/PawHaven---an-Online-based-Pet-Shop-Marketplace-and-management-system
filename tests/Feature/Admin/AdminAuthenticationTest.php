<?php

namespace Tests\Feature\Admin;

use App\Mail\LoginOtpMail;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertSee('Admin Email Address');
        $response->assertSee('Administration');
        $response->assertDontSee('Customer Account');
    }

    public function test_admin_can_authenticate_via_admin_login(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'role' => 'admin',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.two-factor'));
        $this->assertGuest();

        $sentOtp = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($admin, &$sentOtp) {
            $sentOtp = $mail->otp;

            return $mail->hasTo($admin->email);
        });

        $this->assertNotNull($sentOtp);

        $originalSessionId = $response->getSession()->getId();
        $verifyResponse = $this->post(route('admin.two-factor.store'), [
            'code' => $sentOtp,
        ]);

        $verifyResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($originalSessionId, $verifyResponse->getSession()->getId());
        $this->assertDatabaseHas('user_device_sessions', [
            'user_id' => $admin->id,
            'revoked_at' => null,
        ]);
    }

    public function test_staff_can_authenticate_via_admin_login(): void
    {
        Mail::fake();

        $staff = User::factory()->create([
            'email' => 'staff@example.test',
            'role' => 'staff',
        ]);
        $company = Company::query()->withoutGlobalScopes()->where('slug', 'pawhaven')->firstOrFail();
        $owner = User::factory()->create([
            'email' => 'owner@example.test',
            'role' => 'staff',
        ]);
        BusinessMembership::create([
            'user_id' => $owner->id,
            'company_id' => $company->id,
            'branch_id' => null,
            'role' => 'owner',
            'is_active' => true,
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Staff Login Branch',
            'code' => 'STAFF-LOGIN',
            'address' => '1 Example Road',
            'city' => 'Example City',
            'is_active' => true,
        ]);
        BusinessMembership::create([
            'user_id' => $staff->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'seller',
            'is_active' => true,
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $staff->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.two-factor'));
        $this->assertGuest();

        $sentOtp = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($staff, &$sentOtp) {
            $sentOtp = $mail->otp;

            return $mail->hasTo($staff->email);
        });

        $this->assertNotNull($sentOtp);

        $verifyResponse = $this->post(route('admin.two-factor.store'), [
            'code' => $sentOtp,
        ]);

        $verifyResponse->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseHas('user_device_sessions', [
            'user_id' => $staff->id,
            'revoked_at' => null,
        ]);
    }

    public function test_admin_with_fortify_two_factor_must_complete_both_email_and_authenticator_challenges(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        Mail::fake();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $admin = User::factory()->withTwoFactor()->create(['role' => 'admin']);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.two-factor'));

        $this->assertGuest();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($admin, &$code): bool {
            $code = $mail->otp;

            return $mail->hasTo($admin->email);
        });

        $this->post(route('admin.two-factor.store'), ['code' => $code])
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($admin->getKey(), session('login.id'));
    }

    public function test_seller_cannot_authenticate_via_admin_login(): void
    {
        Mail::fake();

        $seller = User::factory()->create([
            'email' => 'seller@pawhaven.com',
            'role' => 'seller',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $seller->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
        Mail::assertNothingSent();
    }

    public function test_admin_role_is_rechecked_before_completing_two_factor_login(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.two-factor'));

        $sentOtp = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$sentOtp) {
            $sentOtp = $mail->otp;

            return true;
        });

        $admin->forceFill(['role' => 'customer'])->save();

        $this->post(route('admin.two-factor.store'), [
            'code' => $sentOtp,
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_customer_cannot_authenticate_via_admin_login(): void
    {
        $customer = User::factory()->create([
            'email' => 'customer@pawhaven.com',
            'role' => 'customer',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_invalid_credentials_fail_admin_login(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_admin_can_logout(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.logout'));

        $response->assertRedirect(route('public.home'));
        $this->assertGuest();
    }

    public function test_unauthenticated_user_accessing_admin_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }
}
