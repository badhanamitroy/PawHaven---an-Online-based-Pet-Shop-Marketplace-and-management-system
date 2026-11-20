<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_new_administrator_via_artisan_command(): void
    {
        $this->artisan('admin:create', [
            '--name' => 'Root Admin',
            '--email' => 'root@pawhaven.local',
        ])
            ->expectsQuestion('Administrator Password (min. 8 characters)', 'secretPassword123!')
            ->expectsOutputToContain('Administrator account [root@pawhaven.local] created successfully!')
            ->assertSuccessful();

        $admin = User::where('email', 'root@pawhaven.local')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('admin', $admin->role);
        $this->assertTrue(Hash::check('secretPassword123!', $admin->password));
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_can_promote_existing_user_to_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@pawhaven.local',
            'role' => 'customer',
        ]);

        $this->artisan('admin:create', [
            '--name' => $user->name,
            '--email' => $user->email,
        ])
            ->expectsConfirmation("User [{$user->email}] already exists with role [customer]. Promote to administrator?", 'yes')
            ->expectsQuestion('New Password (leave empty to keep current password)', 'newAdminPassword123!')
            ->expectsOutputToContain("User [{$user->email}] has been successfully promoted to Administrator.")
            ->assertSuccessful();

        $this->assertEquals('admin', $user->fresh()->role);
        $this->assertTrue(Hash::check('newAdminPassword123!', $user->fresh()->password));
    }

    public function test_invalid_password_does_not_partially_promote_an_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@pawhaven.local',
            'role' => 'customer',
            'email_verified_at' => null,
        ]);
        $originalPassword = $user->password;

        $this->artisan('admin:create', [
            '--name' => $user->name,
            '--email' => $user->email,
        ])
            ->expectsConfirmation("User [{$user->email}] already exists with role [customer]. Promote to administrator?", 'yes')
            ->expectsQuestion('New Password (leave empty to keep current password)', 'short')
            ->assertFailed();

        $this->assertSame('customer', $user->fresh()->role);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame($originalPassword, $user->fresh()->password);
    }
}
