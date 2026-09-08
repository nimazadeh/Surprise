<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_plain_user_gets_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_editor_with_permission_can_access_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_banned_admin_cannot_access_admin(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_BANNED]);
        $user->assignRole('admin');

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_owner_bypass_is_role_based_not_id_based(): void
    {
        // Strip every explicit permission from the owner role: access must
        // still pass through the Gate::before role bypass (never an ID check).
        Role::findByName('owner')->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::factory()->create(); // filler so the owner is NOT id 1
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->assertNotSame(1, $owner->id);
        $this->actingAs($owner)->get('/admin')->assertOk();
        $this->assertTrue($owner->can('settings.manage'));
    }
}
