<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Phase 1 role set (single-owner platform). Content/Ads manager split
     * arrives with the admin modules in Phase 4.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin.access',
            'users.manage',
            'roles.assign',
            'music.manage',
            'uploads.review',
            'ads.manage',
            'settings.manage',
            'analytics.view',
            'audit.view',
            'downloads.access',
            'music_lab.access',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        $matrix = [
            // OWNER is unlimited via Gate::before too; explicit grants keep
            // admin UIs truthful even if the gate is ever bypassed in tests.
            'owner' => $permissions,
            'admin' => $permissions,
            'editor' => ['admin.access', 'music.manage', 'uploads.review', 'analytics.view'],
            'premium_user' => ['downloads.access', 'music_lab.access'],
            'user' => [],
        ];

        foreach ($matrix as $role => $grants) {
            Role::findOrCreate($role)->syncPermissions($grants);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
