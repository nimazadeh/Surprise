<?php

namespace Tests\Feature\Music;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Track;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MusicWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_artist_page_renders_with_seo_meta(): void
    {
        $artist = Artist::factory()->published()->create([
            'name' => 'Seo Singer',
            'seo_title' => 'Seo Singer | SHIRIN',
            'seo_description' => 'Official Seo Singer page.',
        ]);

        $this->get("/artists/{$artist->slug}")
            ->assertOk()
            ->assertSee('Seo Singer | SHIRIN')
            ->assertSee('Official Seo Singer page.', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('MusicGroup', false);
    }

    public function test_album_page_lists_its_tracks(): void
    {
        $artist = Artist::factory()->published()->create();
        $album = Album::factory()->published()->for($artist)->create();
        Track::factory()->published()->for($artist)->for($album)
            ->create(['title' => 'Album Opener']);

        $this->get("/albums/{$album->slug}")
            ->assertOk()
            ->assertSee('Album Opener')
            ->assertSee('MusicAlbum', false);
    }

    public function test_track_page_shows_metadata(): void
    {
        $artist = Artist::factory()->published()->create();
        $album = Album::factory()->published()->for($artist)->create();
        $genre = Genre::factory()->create(['name' => 'Dreampop']);
        $track = Track::factory()->published()
            ->for($artist)->for($album)->for($genre)
            ->create(['duration_sec' => 200]);

        $this->get("/tracks/{$track->slug}")
            ->assertOk()
            ->assertSee($artist->name)
            ->assertSee($album->title)
            ->assertSee('Dreampop')
            ->assertSee('3:20')
            ->assertSee('MusicRecording', false);
    }

    public function test_draft_pages_return_404(): void
    {
        $artist = Artist::factory()->create(['status' => Artist::STATUS_DRAFT]);
        $album = Album::factory()->create(['status' => Album::STATUS_DRAFT]);
        $track = Track::factory()->create(['status' => Track::STATUS_DRAFT]);

        $this->get("/artists/{$artist->slug}")->assertNotFound();
        $this->get("/albums/{$album->slug}")->assertNotFound();
        $this->get("/tracks/{$track->slug}")->assertNotFound();
    }

    public function test_renamed_slug_redirects_with_301(): void
    {
        $artist = Artist::factory()->published()->create(['slug' => 'old-name']);
        $artist->update(['slug' => 'new-name']);

        $this->get('/artists/old-name')
            ->assertStatus(301)
            ->assertRedirect('/artists/new-name');
    }

    public function test_media_cover_serves_file_and_rejects_unknown(): void
    {
        Storage::fake('media');
        Storage::disk('media')->put(
            'covers/albums/123e4567-e89b-12d3-a456-426614174000.jpg',
            'fake-image-bytes'
        );

        $this->get('/media/covers/albums/123e4567-e89b-12d3-a456-426614174000.jpg')
            ->assertOk()
            ->assertHeader('Cache-Control', 'public, max-age=86400');

        $this->get('/media/covers/albums/123e4567-e89b-12d3-a456-426614174001.jpg')
            ->assertNotFound();
        $this->get('/media/covers/nope/123e4567-e89b-12d3-a456-426614174000.jpg')
            ->assertNotFound();
    }
}
