<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_ping_uses_standard_envelope(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['app', 'version', 'time'], 'message'])
            ->assertJson(['success' => true]);
    }

    public function test_public_flags_endpoint_returns_booleans(): void
    {
        $this->getJson('/api/v1/meta/flags')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['downloads', 'music_lab', 'ads', 'uploads']]);
    }

    public function test_unknown_api_route_returns_envelope_404(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertNotFound()
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_user_resource(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissing(['password']);
    }
}
