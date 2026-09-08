<?php

namespace Tests\Feature\Music;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MusicNestedApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_artist_albums_returns_published_albums_with_artist_reference(): void
    {
        $artist = Artist::factory()->published()->create(['name' => 'Nested Singer']);
        Album::factory()->published()->for($artist)->create(['title' => 'Nested Album']);
        Album::factory()->for($artist)->create(['status' => Album::STATUS_DRAFT]);

        $this->getJson("/api/v1/artists/{$artist->slug}/albums")
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.0.title', 'Nested Album')
            ->assertJsonPath('data.0.artist_name', 'Nested Singer');
    }

    public function test_artist_tracks_returns_published_tracks(): void
    {
        $artist = Artist::factory()->published()->create();
        Track::factory()->published()->for($artist)->create(['title' => 'Nested Track']);

        $this->getJson("/api/v1/artists/{$artist->slug}/tracks")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Nested Track')
            ->assertJsonPath('data.0.artist_name', $artist->name);
    }

    public function test_album_tracks_are_ordered_by_track_number(): void
    {
        $artist = Artist::factory()->published()->create();
        $album = Album::factory()->published()->for($artist)->create();
        Track::factory()->published()->for($artist)->for($album)->create(['title' => 'Second', 'track_number' => 2]);
        Track::factory()->published()->for($artist)->for($album)->create(['title' => 'First', 'track_number' => 1]);

        $response = $this->getJson("/api/v1/albums/{$album->slug}/tracks")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame('First', $response->json('data.0.title'));
        $this->assertSame('Second', $response->json('data.1.title'));
    }

    public function test_nested_lists_respect_the_configured_cap(): void
    {
        config(['shirin.catalogue.nested_max' => 2]);

        $artist = Artist::factory()->published()->create();
        Album::factory()->count(3)->published()->for($artist)->create();

        $this->getJson("/api/v1/artists/{$artist->slug}/albums")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_draft_parent_is_hidden_from_nested_endpoints(): void
    {
        $artist = Artist::factory()->create(['status' => Artist::STATUS_DRAFT]);
        Album::factory()->published()->for($artist)->create();

        $this->getJson("/api/v1/artists/{$artist->slug}/albums")
            ->assertNotFound()
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);
    }

    public function test_renamed_slug_301s_to_the_new_nested_url(): void
    {
        $artist = Artist::factory()->published()->create(['name' => 'Renamed Artist']);
        $oldSlug = $artist->slug;

        // HasSlug records the C-01 redirect row automatically on rename.
        $artist->slug = 'renamed-artist-new';
        $artist->save();

        $this->get("/api/v1/artists/{$oldSlug}/albums")
            ->assertStatus(301)
            ->assertRedirect(route('api.v1.artists.albums', $artist->slug));
    }

    public function test_unknown_slug_returns_enveloped_404(): void
    {
        $this->getJson('/api/v1/artists/no-such-artist/albums')
            ->assertNotFound()
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);

        $this->getJson('/api/v1/albums/no-such-album/tracks')
            ->assertNotFound()
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);
    }
}
