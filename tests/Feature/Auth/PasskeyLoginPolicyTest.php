<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PasskeyLoginPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_login_is_disabled_while_registration_and_management_routes_remain(): void
    {
        $this->assertTrue(Route::has('passkey.login-options'));
        $this->assertTrue(Route::has('passkey.login'));
        $this->assertTrue(Route::has('passkey.registration-options'));
        $this->assertTrue(Route::has('passkey.store'));
        $this->assertTrue(Route::has('passkey.confirm-options'));
        $this->assertTrue(Route::has('passkey.confirm'));
        $this->assertTrue(Route::has('passkey.destroy'));

        $this->get(route('passkey.login-options'))->assertNotFound();
        $this->post(route('passkey.login'), [])->assertNotFound();
        $this->assertGuest();
    }
}
