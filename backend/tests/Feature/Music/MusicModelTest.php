<?php

namespace Tests\Feature\Music;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\SlugRedirect;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MusicModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_has_many_albums_and_tracks(): void
    {
        $artist = Artist::factory()->create();
        Album::factory()->for($artist)->create();
        Track::factory()->for($artist)->create();

        $this->assertCount(1, $artist->albums);
        $this->assertCount(1, $artist->tracks);
        $this->assertTrue($artist->albums->first()->artist->is($artist));
    }

    public function test_album_belongs_to_artist_and_has_many_tracks(): void
    {
        $album = Album::factory()->create();
        Track::factory()->for($album)->for($album->artist)->count(3)->create();

        $this->assertInstanceOf(Artist::class, $album->artist);
        $this->assertCount(3, $album->tracks);
    }

    public function test_track_belongs_to_artist_album_and_genre(): void
    {
        $track = Track::factory()
            ->for(Artist::factory())
            ->for(Album::factory())
            ->for(Genre::factory())
            ->create();

        $this->assertInstanceOf(Artist::class, $track->artist);
        $this->assertInstanceOf(Album::class, $track->album);
        $this->assertInstanceOf(Genre::class, $track->genre);
    }

    public function test_track_can_exist_without_album_or_genre(): void
    {
        $track = Track::factory()->create(['album_id' => null, 'genre_id' => null]);

        $this->assertNull($track->album);
        $this->assertNull($track->genre);
    }

    public function test_genre_has_many_tracks(): void
    {
        $genre = Genre::factory()->create();
        Track::factory()->for($genre)->count(2)->create();

        $this->assertCount(2, $genre->tracks);
    }

    public function test_published_scope_only_returns_published(): void
    {
        Artist::factory()->published()->create();
        Artist::factory()->create(['status' => Artist::STATUS_DRAFT]);
        Album::factory()->published()->create();
        Album::factory()->create(['status' => Album::STATUS_ARCHIVED]);
        Track::factory()->published()->create();
        Track::factory()->create(['status' => Track::STATUS_DRAFT]);

        $this->assertCount(1, Artist::published()->get());
        $this->assertCount(1, Album::published()->get());
        $this->assertCount(1, Track::published()->get());
    }

    public function test_featured_scope_returns_featured_artists(): void
    {
        Artist::factory()->featured()->create();
        Artist::factory()->create();

        $this->assertCount(1, Artist::featured()->get());
    }

    public function test_slug_is_generated_from_name_when_empty(): void
    {
        $artist = Artist::factory()->create(['name' => 'Shirin Demo', 'slug' => null]);

        $this->assertSame('shirin-demo', $artist->slug);
    }

    public function test_slug_collisions_get_numeric_suffixes(): void
    {
        Artist::factory()->create(['name' => 'Same Name']);
        $second = Artist::factory()->create(['name' => 'Same Name']);

        $this->assertSame('same-name-2', $second->slug);
    }

    public function test_track_slug_collision_uses_artist_suffix(): void
    {
        $first = Artist::factory()->create(['name' => 'First Singer']);
        $second = Artist::factory()->create(['name' => 'Second Singer']);

        Track::factory()->for($first)->create(['title' => 'Love Song']);
        $collision = Track::factory()->for($second)->create(['title' => 'Love Song']);

        $this->assertSame('love-song-'.$second->slug, $collision->slug);
    }

    public function test_slug_rename_records_redirect(): void
    {
        $artist = Artist::factory()->create(['slug' => 'old-slug']);

        $artist->update(['slug' => 'new-slug']);

        $this->assertDatabaseHas('slug_redirects', [
            'subject_type' => SlugRedirect::SUBJECT_ARTIST,
            'old_slug' => 'old-slug',
            'new_slug' => 'new-slug',
        ]);
    }

    public function test_soft_deleted_rows_keep_slug_reserved(): void
    {
        $artist = Artist::factory()->create(['name' => 'Reserved Name']);
        $artist->delete();

        $recreated = Artist::factory()->create(['name' => 'Reserved Name']);

        $this->assertSame('reserved-name-2', $recreated->slug);
    }

    public function test_album_deletion_nullifies_track_album_id(): void
    {
        $album = Album::factory()->create();
        $track = Track::factory()->for($album)->for($album->artist)->create();

        $album->delete();

        $this->assertNull($track->fresh()->album_id);
        $this->assertNotNull($track->fresh());
    }

    public function test_hard_deleting_artist_cascades_to_albums_and_tracks(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->for($artist)->create();
        $track = Track::factory()->for($artist)->for($album)->create();

        $artist->forceDelete();

        $this->assertDatabaseMissing('albums', ['id' => $album->id]);
        $this->assertDatabaseMissing('tracks', ['id' => $track->id]);
    }

    public function test_genre_deletion_nullifies_track_genre_id(): void
    {
        $genre = Genre::factory()->create();
        $track = Track::factory()->for($genre)->create();

        $genre->delete();

        $this->assertNull($track->fresh()->genre_id);
    }
}
