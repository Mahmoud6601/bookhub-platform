<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookhub:create-admin
                            {email : Email address for the admin user}
                            {--name=Admin : Display name when creating a new user}
                            {--password= : Password (min 8 chars; prompted if creating a new user without it)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user or promote an existing user to admin';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $password = $this->option('password');
            if ($password !== null && $password !== '') {
                if (strlen((string) $password) < 8) {
                    $this->error('Password must be at least 8 characters.');

                    return self::FAILURE;
                }
                $user->password = Hash::make((string) $password);
            }
            $user->is_admin = true;
            $user->save();

            $this->info("Promoted existing user {$user->email} to admin.");

            return self::SUCCESS;
        }

        $password = $this->option('password');
        if (! $password) {
            $password = $this->secret('Enter admin password (min 8 characters)');
        }

        if (! $password || strlen((string) $password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = new User();
        $user->name = (string) $this->option('name');
        $user->email = $email;
        $user->password = Hash::make((string) $password);
        $user->is_admin = true;
        $user->save();

        $this->info("Created admin user {$user->email}.");

        return self::SUCCESS;
    }
}
