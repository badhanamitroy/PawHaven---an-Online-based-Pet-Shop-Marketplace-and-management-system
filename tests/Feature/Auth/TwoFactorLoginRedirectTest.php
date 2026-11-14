<?php

namespace Tests\Feature\Auth;

use App\Http\Responses\TwoFactorLoginResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TwoFactorLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_response_rejects_an_external_intended_url(): void
    {
        $response = $this->respondAs(User::factory()->create(['role' => 'customer']), 'https://attacker.invalid/account');

        $this->assertSame(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_two_factor_response_rejects_another_roles_intended_url(): void
    {
        $response = $this->respondAs(User::factory()->create(['role' => 'customer']), '/admin/users');

        $this->assertSame(route('dashboard'), $response->headers->get('Location'));
    }

    public function test_two_factor_response_keeps_a_safe_same_role_intended_url(): void
    {
        $response = $this->respondAs(User::factory()->create(['role' => 'seller']), '/seller/products');

        $this->assertSame(url('/seller/products'), $response->headers->get('Location'));
    }

    public function test_two_factor_response_uses_admin_and_staff_role_destinations(): void
    {
        $admin = $this->respondAs(User::factory()->create(['role' => 'admin']), 'https://attacker.invalid/');
        $staff = $this->respondAs(User::factory()->create(['role' => 'staff']), '/seller/products');

        $this->assertSame(route('admin.dashboard'), $admin->headers->get('Location'));
        $this->assertSame(route('admin.dashboard'), $staff->headers->get('Location'));
    }

    public function test_two_factor_response_rejects_a_staff_only_forbidden_intended_destination(): void
    {
        $response = $this->respondAs(User::factory()->create(['role' => 'staff']), '/admin/users');

        $this->assertSame(route('admin.dashboard'), $response->headers->get('Location'));
    }

    public function test_two_factor_response_rejects_an_unrecognized_role(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        DB::table('users')->where('id', $user->id)->update(['role' => 'unrecognized']);
        $user->refresh();

        $this->expectException(HttpException::class);
        $this->respondAs($user, '/dashboard');
    }

    private function respondAs(User $user, string $intended): Response
    {
        $request = Request::create('/login/two-factor', 'POST');
        $session = $this->app['session.store'];
        $session->start();
        $session->put('url.intended', $intended);
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn () => $user);

        return app(TwoFactorLoginResponse::class)->toResponse($request);
    }
}
