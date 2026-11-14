<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Mail\LoginOtpMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RoleLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_renders_the_public_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Find Pets. Give Hope. Shop with Love.');
    }

    public function test_customer_root_redirects_to_customer_dashboard(): void
    {
        $customer = $this->verifiedUser('customer');

        $this->actingAs($customer)->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_seller_root_redirects_to_seller_dashboard(): void
    {
        $seller = $this->verifiedUser('seller');

        $this->actingAs($seller)->get('/')
            ->assertRedirect(route('seller.dashboard'));
    }

    public function test_staff_root_redirects_to_authorized_operations_dashboard(): void
    {
        $staff = $this->verifiedUser('staff');

        $this->actingAs($staff)->get('/')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_root_redirects_to_admin_dashboard(): void
    {
        $admin = $this->verifiedUser('admin');

        $this->actingAs($admin)->get('/')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_users_can_open_public_homepage_explicitly(): void
    {
        $customer = $this->verifiedUser('customer');

        $this->actingAs($customer)->get(route('public.home'))
            ->assertOk()
            ->assertSee('Find Pets. Give Hope. Shop with Love.');
    }

    public function test_unknown_role_is_rejected_at_root_dashboard_and_login(): void
    {
        $user = $this->verifiedUser('customer');
        DB::table('users')->where('id', $user->id)->update(['role' => 'unsupported-role']);
        $user->refresh();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SecurePassword123!',
        ])->assertSessionHasErrors(['email']);
        $this->assertGuest();

        $this->actingAs($user)->get('/')->assertForbidden();
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_customer_login_discards_admin_intended_url(): void
    {
        Mail::fake();
        $customer = $this->verifiedUser('customer');

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.store'), [
                'email' => $customer->email,
                'password' => 'SecurePassword123!',
            ])
            ->assertRedirect(route('login.otp.show'));

        $this->verifyLoginOtp($customer)->assertRedirect(route('dashboard'));
    }

    public function test_seller_login_discards_admin_intended_url(): void
    {
        Mail::fake();
        $seller = $this->verifiedUser('seller');

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.store'), [
                'email' => $seller->email,
                'password' => 'SecurePassword123!',
            ])
            ->assertRedirect(route('login.otp.show'));

        $this->verifyLoginOtp($seller)->assertRedirect(route('seller.dashboard'));
    }

    public function test_login_rejects_external_intended_url(): void
    {
        Mail::fake();
        $customer = $this->verifiedUser('customer');

        $this->withSession(['url.intended' => 'https://attacker.example/'])
            ->post(route('login.store'), [
                'email' => $customer->email,
                'password' => 'SecurePassword123!',
            ])
            ->assertRedirect(route('login.otp.show'));

        $this->verifyLoginOtp($customer)->assertRedirect(route('dashboard'));
    }

    private function verifiedUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'email_verified_at' => now(),
            'password' => bcrypt('SecurePassword123!'),
        ]);
    }

    private function verifyLoginOtp(User $user): TestResponse
    {
        $this->assertGuest();

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use ($user, &$code): bool {
            $code = $mail->otp;

            return $mail->hasTo($user->email);
        });

        $this->assertIsString($code);

        return $this->post(route('login.otp.verify'), ['code' => $code]);
    }
}
