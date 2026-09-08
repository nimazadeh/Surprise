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

class MusicWebCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_artists_index_lists_published_artists_and_hides_drafts(): void
    {
        Artist::factory()->published()->create(['name' => 'Listed Artist']);
        Artist::factory()->create(['name' => 'Hidden Artist', 'status' => 'draft']);

        $this->get('/artists')
            ->assertOk()
            ->assertSee('Listed Artist')
            ->assertDontSee('Hidden Artist')
            ->assertSee('rel="canonical"', false)
            ->assertSee('CollectionPage', false);
    }

    public function test_artists_index_search_filters_results(): void
    {
        Artist::factory()->published()->create(['name' => 'Needle Singer']);
        Artist::factory()->published()->create(['name' => 'Unrelated Band']);

        $this->get('/artists?q=Needle')
            ->assertOk()
            ->assertSee('Needle Singer')
            ->assertDontSee('Unrelated Band');
    }

    public function test_albums_index_filters_by_type(): void
    {
        $artist = Artist::factory()->published()->create();
        Album::factory()->published()->for($artist)->create(['title' => 'Full Length', 'type' => 'album']);
        Album::factory()->published()->for($artist)->create(['title' => 'Small EP', 'type' => 'ep']);

        $this->get('/albums?type=ep')
            ->assertOk()
            ->assertSee('Small EP')
            ->assertDontSee('Full Length');
    }

    public function test_tracks_index_filters_by_genre(): void
    {
        $artist = Artist::factory()->published()->create();
        $pop = Genre::factory()->create(['name' => 'Pop', 'slug' => 'pop']);
        $folk = Genre::factory()->create(['name' => 'Folk', 'slug' => 'folk']);
        Track::factory()->published()->for($artist)->create(['title' => 'Pop Song', 'genre_id' => $pop->id]);
        Track::factory()->published()->for($artist)->create(['title' => 'Folk Song', 'genre_id' => $folk->id]);

        $this->get('/tracks?genre=pop')
            ->assertOk()
            ->assertSee('Pop Song')
            ->assertDontSee('Folk Song');
    }

    public function test_tracks_index_lists_published_tracks_with_metadata(): void
    {
        $artist = Artist::factory()->published()->create(['name' => 'Meta Artist']);
        Track::factory()->published()->for($artist)->create(['title' => 'Listed Track', 'duration_sec' => 125]);

        $this->get('/tracks')
            ->assertOk()
            ->assertSee('Listed Track')
            ->assertSee('2:05');
    }

    public function test_genre_page_lists_its_tracks(): void
    {
        $artist = Artist::factory()->published()->create();
        $genre = Genre::factory()->create(['name' => 'Jazz', 'slug' => 'jazz']);
        Track::factory()->published()->for($artist)->create(['title' => 'Jazz Tune', 'genre_id' => $genre->id]);

        $this->get("/genres/{$genre->slug}")
            ->assertOk()
            ->assertSee('Jazz')
            ->assertSee('Jazz Tune')
            ->assertSee('rel="canonical"', false);
    }

    public function test_unknown_genre_slug_returns_404(): void
    {
        $this->get('/genres/no-such-genre')->assertNotFound();
    }

    public function test_catalogue_navigation_renders_on_public_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('artists.index'))
            ->assertSee(route('albums.index'))
            ->assertSee(route('tracks.index'));
    }

    public function test_pagination_keeps_filters_in_links(): void
    {
        config(['shirin.music.api_per_page' => 1]);

        Artist::factory()->published()->create(['name' => 'Barbara Ana']);
        Artist::factory()->published()->create(['name' => 'Sarah Diana']);

        $this->get('/artists?q=a&page=2')
            ->assertOk()
            ->assertSee('q=a', false);
    }
}
