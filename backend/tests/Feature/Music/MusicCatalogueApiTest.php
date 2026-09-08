<?php

namespace Tests\Feature\Music;

use App\Contracts\MusicProvider;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Setting;
use App\Models\Track;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fixtures\FakeMusicProvider;
use Tests\TestCase;

class MusicCatalogueApiTest extends TestCase
{
    use RefreshDatabase;

    protected FakeMusicProvider $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->fake = new FakeMusicProvider;
        $this->app->instance(MusicProvider::class, $this->fake);
    }

    public function test_featured_prefers_the_owned_featured_artist(): void
    {
        $artist = Artist::factory()->published()->create(['name' => 'Owned Hero', 'is_featured' => true]);
        Album::factory()->published()->for($artist)->create(['title' => 'Owned Album']);
        Track::factory()->published()->for($artist)->create(['title' => 'Owned Track']);

        $this->fake->artists['7312776'] = ['id' => '7312776', 'name' => 'Provider Artist', 'source' => 'deezer'];

        $this->getJson('/api/v1/catalogue/featured')
            ->assertOk()
            ->assertJsonPath('data.artist.name', 'Owned Hero')
            ->assertJsonPath('data.artist.source', 'owned')
            ->assertJsonPath('data.albums.0.title', 'Owned Album')
            ->assertJsonPath('data.tracks.0.title', 'Owned Track');
    }

    public function test_featured_falls_back_to_the_provider_artist(): void
    {
        $this->fake->artists['7312776'] = [
            'id' => '7312776',
            'name' => 'Provider Artist',
            'source' => 'deezer',
            'provider_id' => '7312776',
        ];
        $this->fake->artistAlbums['7312776'] = [
            ['id' => '123', 'title' => 'Provider Album', 'source' => 'deezer', 'provider_id' => '123'],
        ];

        $response = $this->getJson('/api/v1/catalogue/featured')->assertOk();

        $this->assertSame('Provider Artist', $response->json('data.artist.name'));
        $this->assertSame('deezer', $response->json('data.artist.source'));
        $this->assertSame('Provider Album', $response->json('data.albums.0.title'));
    }

    public function test_featured_provider_outage_degrades_to_owned_content(): void
    {
        Artist::factory()->published()->create(['name' => 'Plain Artist']);
        Album::factory()->published()->create(['title' => 'Owned Album']);
        $this->fake->failing = true;

        $response = $this->getJson('/api/v1/catalogue/featured')->assertOk();

        $this->assertNull($response->json('data.artist'));
        $this->assertSame('Owned Album', $response->json('data.albums.0.title'));
    }

    public function test_featured_flag_off_never_calls_the_provider(): void
    {
        app(SettingsService::class)->set('features.catalogue_provider', false, Setting::TYPE_BOOLEAN, 'features');
        $this->fake->artists['7312776'] = ['id' => '7312776', 'name' => 'Provider Artist', 'source' => 'deezer'];

        $response = $this->getJson('/api/v1/catalogue/featured')->assertOk();

        $this->assertNull($response->json('data.artist'));
        $this->assertSame(0, $this->fake->getCalls);
        $this->assertSame(0, $this->fake->searchCalls);
    }

    public function test_featured_suppresses_owned_twins_in_provider_items(): void
    {
        $owned = Artist::factory()->published()->create([
            'name' => 'Twin Album Owner',
            'source' => 'deezer',
            'provider_id' => '7312776',
        ]);
        Album::factory()->published()->for($owned)->create([
            'title' => 'Owned Twin',
            'source' => 'deezer',
            'provider_id' => '123',
        ]);
        $this->fake->artistAlbums['7312776'] = [
            ['id' => '123', 'title' => 'Owned Twin (provider)', 'source' => 'deezer', 'provider_id' => '123'],
            ['id' => '124', 'title' => 'Not In Catalogue', 'source' => 'deezer', 'provider_id' => '124'],
        ];

        $response = $this->getJson('/api/v1/catalogue/featured')->assertOk();

        $titles = array_column($response->json('data.albums'), 'title');

        $this->assertContains('Owned Twin', $titles);
        $this->assertContains('Not In Catalogue', $titles);
        $this->assertNotContains('Owned Twin (provider)', $titles);
    }

    public function test_resolve_owned_twin_returns_seo_url(): void
    {
        $artist = Artist::factory()->published()->create([
            'name' => 'Owned Twin',
            'source' => 'deezer',
            'provider_id' => '7312776',
        ]);

        $this->getJson('/api/v1/resolve?type=artist&provider_id=7312776')
            ->assertOk()
            ->assertJsonPath('data.matched', true)
            ->assertJsonPath('data.source', 'owned')
            ->assertJsonPath('data.slug', $artist->slug)
            ->assertJsonPath('data.url', route('artists.show', $artist->slug));
    }

    public function test_resolve_provider_album_carries_children(): void
    {
        $this->fake->albums['123'] = [
            'id' => '123',
            'title' => 'Provider Album',
            'source' => 'deezer',
            'provider_id' => '123',
            'url' => 'https://www.deezer.com/album/123',
        ];
        $this->fake->albumTracks['123'] = [
            ['id' => '456', 'title' => 'Provider Track', 'source' => 'deezer', 'provider_id' => '456', 'preview' => 'https://cdns-preview.dzcdn.net/stream/a.mp3'],
        ];

        $response = $this->getJson('/api/v1/resolve?type=album&provider_id=123')
            ->assertOk()
            ->assertJsonPath('data.matched', true)
            ->assertJsonPath('data.source', 'deezer')
            ->assertJsonPath('data.item.title', 'Provider Album');

        $this->assertSame('Provider Track', $response->json('data.tracks.0.title'));
        $this->assertSame('https://cdns-preview.dzcdn.net/stream/a.mp3', $response->json('data.tracks.0.preview'));
    }

    public function test_resolve_unpublished_owned_twin_misses_without_provider_fallthrough(): void
    {
        Artist::factory()->create([
            'name' => 'Draft Twin',
            'status' => 'draft',
            'source' => 'deezer',
            'provider_id' => '7312776',
        ]);
        $this->fake->artists['7312776'] = ['id' => '7312776', 'name' => 'Provider Artist', 'source' => 'deezer'];

        $this->getJson('/api/v1/resolve?type=artist&provider_id=7312776')
            ->assertOk()
            ->assertJsonPath('data.matched', false)
            ->assertJsonPath('data.item', null);

        // The managed-but-unpublished twin must not fall through to the
        // provider either.
        $this->assertSame(0, $this->fake->getCalls);
    }

    public function test_resolve_unknown_provider_id_misses_cleanly(): void
    {
        $this->getJson('/api/v1/resolve?type=track&provider_id=999999')
            ->assertOk()
            ->assertJsonPath('data.matched', false);
    }

    public function test_resolve_rejects_invalid_input(): void
    {
        $this->getJson('/api/v1/resolve?type=playlist&provider_id=1')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'VALIDATION']);

        $this->getJson('/api/v1/resolve?type=track&provider_id=../etc/passwd')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'VALIDATION']);
    }
}
