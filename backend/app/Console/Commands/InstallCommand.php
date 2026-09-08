<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'shirin:install';

    protected $description = 'Seed roles/settings and interactively create the OWNER account.';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $email = text('Owner email', required: true);
        $name = text('Owner display name', default: 'SHIRIN Owner', required: true);

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['email', 'max:255']]
        );

        if ($validator->fails()) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        $plainPassword = password('Owner password (min 10 chars)', required: true);

        if (strlen($plainPassword) < 10) {
            $this->error('Password must be at least 10 characters.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => 'owner',
                'password' => Hash::make($plainPassword),
                'email_verified_at' => now(),
                'locale' => config('app.locale', 'fa'),
                'status' => User::STATUS_ACTIVE,
            ]
        );

        if (! $user->wasRecentlyCreated && ! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole('owner');

        $this->info('SHIRIN installed. OWNER role granted to '.$user->email.'.');
        $this->line('Admin panel: '.rtrim(config('app.url'), '/').'/admin');

        return self::SUCCESS;
    }
}
