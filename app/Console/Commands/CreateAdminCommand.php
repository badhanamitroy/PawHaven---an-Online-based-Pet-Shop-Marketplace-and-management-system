<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create
                            {--name= : The full name of the administrator}
                            {--email= : The email address of the administrator}
                            {--password= : The password for the administrator account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or promote a user to the PawHaven Administrator role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Administrator Full Name');
        $email = $this->option('email') ?: $this->ask('Administrator Email Address');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($this->confirm("User [{$email}] already exists with role [{$existingUser->role}]. Promote to administrator?", true)) {
                $existingUser->role = 'admin';
                if (! $existingUser->hasVerifiedEmail()) {
                    $existingUser->markEmailAsVerified();
                }

                $password = $this->option('password') ?: $this->secret('New Password (leave empty to keep current password)');
                if (! empty($password)) {
                    if (strlen($password) < 8) {
                        $this->error('Password must be at least 8 characters long.');

                        return self::FAILURE;
                    }
                    $existingUser->password = Hash::make($password);
                }

                $existingUser->save();
                $this->info("User [{$email}] has been successfully promoted to Administrator.");

                return self::SUCCESS;
            }

            $this->warn('Operation cancelled.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Administrator Password (min. 8 characters)');

        if (strlen((string) $password) < 8) {
            $this->error('Password must be at least 8 characters long.');

            return self::FAILURE;
        }

        $user = new User;
        $user->name = (string) $name;
        $user->email = (string) $email;
        $user->password = Hash::make((string) $password);
        $user->role = 'admin';
        $user->save();
        $user->markEmailAsVerified();

        $this->info("Administrator account [{$email}] created successfully!");
        $this->line('You can now log in at: '.route('admin.login'));

        return self::SUCCESS;
    }
}
