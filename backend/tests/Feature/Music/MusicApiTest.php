<?php

namespace Tests\Feature\Music;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Track;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MusicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_artists_index_returns_envelope(): void
    {
        Artist::factory()->published()->create(['name' => 'Listed Singer']);

        $this->getJson('/api/v1/artists')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['data', 'links', 'meta'], 'message'])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.data.0.name', 'Listed Singer');
    }

    public function test_catalogue_indexes_hide_drafts(): void
    {
        Artist::factory()->published()->create();
        Artist::factory()->create(['status' => Artist::STATUS_DRAFT]);
        Album::factory()->published()->create();
        Album::factory()->create(['status' => Album::STATUS_DRAFT]);
        Track::factory()->published()->create();
        Track::factory()->create(['status' => Track::STATUS_DRAFT]);

        $this->assertCount(1, $this->getJson('/api/v1/artists')->json('data.data'));
        $this->assertCount(1, $this->getJson('/api/v1/albums')->json('data.data'));
        $this->assertCount(1, $this->getJson('/api/v1/tracks')->json('data.data'));
    }

    public function test_artist_show_returns_player_shape(): void
    {
        $artist = Artist::factory()->published()->create();

        $this->getJson("/api/v1/artists/{$artist->slug}")
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'name', 'slug', 'bio', 'artwork', 'country',
                    'language', 'featured', 'source', 'albums_count',
                    'tracks_count', 'url',
                ],
                'message',
            ])
            ->assertJsonPath('data.slug', $artist->slug);
    }

    public function test_unknown_artist_slug_returns_envelope_404(): void
    {
        $this->getJson('/api/v1/artists/no-such-artist')
            ->assertNotFound()
            ->assertJson(['success' => false, 'code' => 'NOT_FOUND']);
    }

    public function test_album_show_embeds_artist_reference(): void
    {
        $album = Album::factory()->published()->create();

        $this->getJson("/api/v1/albums/{$album->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', $album->title)
            ->assertJsonPath('data.artist_name', $album->artist->name)
            ->assertJsonPath('data.artist_slug', $album->artist->slug)
            ->assertJsonStructure(['data' => ['artwork', 'type', 'source', 'url']]);
    }

    public function test_track_show_embeds_relations_and_duration(): void
    {
        $artist = Artist::factory()->published()->create();
        $album = Album::factory()->published()->for($artist)->create();
        $genre = Genre::factory()->create();
        $track = Track::factory()->published()
            ->for($artist)->for($album)->for($genre)
            ->create(['title' => 'Deep Cut', 'duration_sec' => 195]);

        $this->getJson("/api/v1/tracks/{$track->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Deep Cut')
            ->assertJsonPath('data.artist_name', $artist->name)
            ->assertJsonPath('data.album_title', $album->title)
            ->assertJsonPath('data.genre.name', $genre->name)
            ->assertJsonPath('data.duration', 195)
            ->assertJsonPath('data.duration_human', '3:15');
    }

    public function test_drafts_and_trashed_records_are_not_found(): void
    {
        $draft = Track::factory()->create(['status' => Track::STATUS_DRAFT]);
        $trashed = Track::factory()->published()->create();
        $trashed->delete();

        $this->getJson("/api/v1/tracks/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/v1/tracks/{$trashed->slug}")->assertNotFound();
        $this->getJson('/api/v1/albums/no-such-album')->assertNotFound();
        $this->getJson('/api/v1/tracks/no-such-track')->assertNotFound();
    }

    public function test_pagination_per_page_is_capped(): void
    {
        Track::factory()->published()->count(3)->create();

        $this->getJson('/api/v1/tracks?per_page=500')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 100);
    }
}
