<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_download_center_modes(): void
    {
        $settings = app(SettingsService::class);
        $flags = app(FeatureFlagService::class);

        $user = User::factory()->create();
        $user->assignRole('user');

        $settings->set('features.download_center', 'off', Setting::TYPE_STRING);
        $this->assertFalse($flags->downloadCenterVisibleTo(null));
        $this->assertFalse($flags->downloadCenterVisibleTo($user));

        $settings->set('features.download_center', 'public', Setting::TYPE_STRING);
        $this->assertTrue($flags->downloadCenterVisibleTo(null));

        $settings->set('features.download_center', 'registered', Setting::TYPE_STRING);
        $this->assertFalse($flags->downloadCenterVisibleTo(null));
        $this->assertTrue($flags->downloadCenterVisibleTo($user));

        $settings->set('features.download_center', 'premium', Setting::TYPE_STRING);
        $this->assertFalse($flags->downloadCenterVisibleTo($user));

        $user->assignRole('premium_user');
        $this->assertTrue($flags->downloadCenterVisibleTo($user->fresh()));
    }

    public function test_owner_always_has_premium_access(): void
    {
        $flags = app(FeatureFlagService::class);
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->assertTrue($flags->hasPremiumAccess($owner));
        $this->assertFalse($flags->hasPremiumAccess(null));
    }

    public function test_music_lab_requires_flag_and_tier(): void
    {
        $settings = app(SettingsService::class);
        $flags = app(FeatureFlagService::class);

        $user = User::factory()->create();
        $user->assignRole('user');

        $settings->set('features.music_lab', false, Setting::TYPE_BOOLEAN);
        $this->assertFalse($flags->musicLabAvailableTo($user));

        $settings->set('features.music_lab', true, Setting::TYPE_BOOLEAN);
        $settings->set('features.music_lab_premium_only', true, Setting::TYPE_BOOLEAN);
        $this->assertFalse($flags->musicLabAvailableTo($user));

        $settings->set('features.music_lab_premium_only', false, Setting::TYPE_BOOLEAN);
        $this->assertTrue($flags->musicLabAvailableTo($user));
        $this->assertFalse($flags->musicLabAvailableTo(null));
    }
}
