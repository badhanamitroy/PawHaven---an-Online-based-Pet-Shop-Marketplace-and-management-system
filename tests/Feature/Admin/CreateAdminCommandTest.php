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
            '--password' => 'secretPassword123!',
        ])
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
            '--password' => 'newAdminPassword123!',
        ])
            ->expectsConfirmation("User [{$user->email}] already exists with role [customer]. Promote to administrator?", 'yes')
            ->expectsOutputToContain("User [{$user->email}] has been successfully promoted to Administrator.")
            ->assertSuccessful();

        $this->assertEquals('admin', $user->fresh()->role);
        $this->assertTrue(Hash::check('newAdminPassword123!', $user->fresh()->password));
    }
}
