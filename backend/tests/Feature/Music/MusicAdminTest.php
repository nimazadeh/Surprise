<?php

namespace Tests\Feature\Music;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MusicAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function editor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        return $user;
    }

    public function test_guest_is_redirected_to_login_on_music_admin(): void
    {
        $this->get('/admin/artists')->assertRedirect('/login');
    }

    public function test_plain_user_is_forbidden_from_music_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/admin/artists')->assertForbidden();
        $this->actingAs($user)->post('/admin/artists', [])->assertForbidden();
    }

    public function test_editor_can_view_music_indexes(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get('/admin/artists')->assertOk();
        $this->actingAs($editor)->get('/admin/albums')->assertOk();
        $this->actingAs($editor)->get('/admin/tracks')->assertOk();
        $this->actingAs($editor)->get('/admin/genres')->assertOk();
    }

    public function test_editor_can_create_artist_with_auto_slug(): void
    {
        $response = $this->actingAs($this->editor())->post('/admin/artists', [
            'name' => 'New Singer',
            'language' => 'fa',
            'status' => Artist::STATUS_DRAFT,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('artists', ['name' => 'New Singer', 'slug' => 'new-singer']);
    }

    public function test_editor_can_update_artist(): void
    {
        $artist = Artist::factory()->create();

        $this->actingAs($this->editor())->put("/admin/artists/{$artist->slug}", [
            'name' => 'Renamed Singer',
            'language' => 'en',
            'status' => Artist::STATUS_PUBLISHED,
        ])->assertRedirect();

        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'name' => 'Renamed Singer',
            'status' => Artist::STATUS_PUBLISHED,
        ]);
    }

    public function test_editor_can_toggle_artist_status(): void
    {
        $artist = Artist::factory()->create(['status' => Artist::STATUS_DRAFT]);

        $this->actingAs($this->editor())
            ->post("/admin/artists/{$artist->slug}/toggle")
            ->assertRedirect();

        $this->assertSame(Artist::STATUS_PUBLISHED, $artist->fresh()->status);

        $this->actingAs($this->editor())
            ->post("/admin/artists/{$artist->slug}/toggle")
            ->assertRedirect();

        $this->assertSame(Artist::STATUS_DRAFT, $artist->fresh()->status);
    }

    public function test_editor_can_delete_artist(): void
    {
        $artist = Artist::factory()->create();

        $this->actingAs($this->editor())
            ->delete("/admin/artists/{$artist->slug}")
            ->assertRedirect('/admin/artists');

        $this->assertSoftDeleted('artists', ['id' => $artist->id]);
    }

    public function test_editor_can_create_album_for_artist(): void
    {
        $artist = Artist::factory()->create();

        $this->actingAs($this->editor())->post('/admin/albums', [
            'artist_id' => $artist->id,
            'title' => 'First Album',
            'type' => Album::TYPE_ALBUM,
            'release_year' => 2026,
            'status' => Album::STATUS_DRAFT,
        ])->assertRedirect();

        $this->assertDatabaseHas('albums', [
            'artist_id' => $artist->id,
            'title' => 'First Album',
            'slug' => 'first-album',
        ]);
    }

    public function test_editor_can_create_track_with_relations(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->for($artist)->create();
        $genre = Genre::factory()->create();

        $this->actingAs($this->editor())->post('/admin/tracks', [
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'genre_id' => $genre->id,
            'title' => 'First Track',
            'track_number' => 1,
            'duration_sec' => 195,
            'language' => 'fa',
            'status' => Track::STATUS_DRAFT,
        ])->assertRedirect();

        $this->assertDatabaseHas('tracks', [
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'genre_id' => $genre->id,
            'title' => 'First Track',
            'duration_sec' => 195,
        ]);
    }

    public function test_editor_can_manage_genres(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post('/admin/genres', [
            'name' => 'Chill',
        ])->assertRedirect();

        $genre = Genre::where('slug', 'chill')->firstOrFail();

        $this->actingAs($editor)->put("/admin/genres/{$genre->slug}", [
            'name' => 'Chill Out',
        ])->assertRedirect();

        $this->actingAs($editor)->delete("/admin/genres/{$genre->slug}")
            ->assertRedirect('/admin/genres');

        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    public function test_validation_rejects_invalid_artist(): void
    {
        $response = $this->actingAs($this->editor())->post('/admin/artists', [
            'name' => '',
            'slug' => 'bad slug!',
            'language' => 'de',
            'status' => 'nope',
        ]);

        $response->assertSessionHasErrors(['name', 'slug', 'language', 'status']);
        $this->assertDatabaseCount('artists', 0);
    }

    public function test_validation_rejects_invalid_track(): void
    {
        $response = $this->actingAs($this->editor())->post('/admin/tracks', [
            'artist_id' => 999999,
            'genre_id' => 999999,
            'title' => '',
            'track_number' => 0,
            'duration_sec' => 0,
            'language' => 'fa',
            'status' => Track::STATUS_DRAFT,
        ]);

        $response->assertSessionHasErrors([
            'artist_id', 'genre_id', 'title', 'track_number', 'duration_sec',
        ]);
        $this->assertDatabaseCount('tracks', 0);
    }

    public function test_cover_upload_is_stored_and_replaced(): void
    {
        Storage::fake('media');

        $editor = $this->editor();
        $artist = Artist::factory()->create();

        $this->actingAs($editor)->post('/admin/albums', [
            'artist_id' => $artist->id,
            'title' => 'Cover Album',
            'type' => Album::TYPE_ALBUM,
            'status' => Album::STATUS_DRAFT,
            'cover' => UploadedFile::fake()->image('cover.jpg', 600, 600),
        ])->assertRedirect();

        $album = Album::where('slug', 'cover-album')->firstOrFail();
        Storage::disk('media')->assertExists($album->cover);
        $oldCover = $album->cover;

        $this->actingAs($editor)->put("/admin/albums/{$album->slug}", [
            'title' => 'Cover Album',
            'cover' => UploadedFile::fake()->image('replacement.png', 600, 600),
        ])->assertRedirect();

        $album->refresh();
        Storage::disk('media')->assertExists($album->cover);
        Storage::disk('media')->assertMissing($oldCover);
    }

    public function test_owner_without_explicit_permission_can_manage_music(): void
    {
        Role::findByName('owner')->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::factory()->create(); // filler so the owner is NOT id 1
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->assertNotSame(1, $owner->id);
        $this->actingAs($owner)->get('/admin/artists')->assertOk();
        $this->assertTrue($owner->can('music.manage'));
    }
}
