<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Non-interactive safe seeds. OWNER account creation is interactive
     * via `php artisan shirin:install` (never default credentials).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
