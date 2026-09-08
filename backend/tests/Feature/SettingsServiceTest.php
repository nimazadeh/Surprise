<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_default_for_missing_key(): void
    {
        $service = app(SettingsService::class);

        $this->assertSame('fallback', $service->get('missing.key', 'fallback'));
    }

    public function test_set_and_get_roundtrip_with_types(): void
    {
        $service = app(SettingsService::class);

        $service->set('site.name', 'SHIRIN', Setting::TYPE_STRING, 'site');
        $service->set('player.limit', 30, Setting::TYPE_INTEGER, 'player');
        $service->set('features.ads', true, Setting::TYPE_BOOLEAN, 'features');
        $service->set('seo.meta', ['a' => 'b'], Setting::TYPE_JSON, 'seo');

        $this->assertSame('SHIRIN', $service->get('site.name'));
        $this->assertSame(30, $service->get('player.limit'));
        $this->assertTrue($service->get('features.ads'));
        $this->assertSame(['a' => 'b'], $service->get('seo.meta'));
    }

    public function test_get_is_cached_and_set_flushes_cache(): void
    {
        $service = app(SettingsService::class);
        $service->set('site.name', 'SHIRIN');

        $this->assertSame('SHIRIN', Cache::get('settings.site.name'));

        $service->set('site.name', 'CHANGED');
        $this->assertSame('CHANGED', $service->get('site.name'));
    }

    public function test_forget_removes_setting(): void
    {
        $service = app(SettingsService::class);
        $service->set('temp.key', 'x');

        $service->forget('temp.key');

        $this->assertNull($service->get('temp.key'));
        $this->assertDatabaseMissing('settings', ['key' => 'temp.key']);
    }
}
