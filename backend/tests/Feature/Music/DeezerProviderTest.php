<?php

namespace Tests\Feature\Music;

use App\Contracts\MusicProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeezerProviderTest extends TestCase
{
    protected function provider(): MusicProvider
    {
        return app(MusicProvider::class);
    }

    protected function enableProvider(): void
    {
        config([
            'shirin.providers.deezer.enabled' => true,
            'shirin.providers.deezer.cache_ttl' => 60,
        ]);
    }

    public function test_disabled_provider_never_calls_the_network(): void
    {
        config(['shirin.providers.deezer.enabled' => false]);
        Http::fake();

        $provider = $this->provider();

        $this->assertSame([], $provider->searchArtists('shirin', 5));
        $this->assertSame([], $provider->searchAlbums('shirin', 5));
        $this->assertSame([], $provider->searchTracks('shirin', 5));
        $this->assertNull($provider->getArtist('7312776'));
        $this->assertSame([], $provider->getArtistAlbums('7312776', 5));
        $this->assertSame([], $provider->getArtistTopTracks('7312776', 5));
        $this->assertNull($provider->getAlbum('123'));
        $this->assertSame([], $provider->getAlbumTracks('123', 5));
        $this->assertNull($provider->getTrack('456'));

        Http::assertNothingSent();
    }

    public function test_artist_is_normalized_to_player_shape(): void
    {
        $this->enableProvider();
        Http::fake([
            '*/artist/7312776' => Http::response([
                'id' => 7312776,
                'name' => 'Shirin David',
                'link' => 'https://www.deezer.com/artist/7312776',
                'nb_album' => 24,
                'picture_xl' => 'https://api.deezer.com/artist/7312776/image',
            ]),
        ]);

        $artist = $this->provider()->getArtist('7312776');

        $this->assertNotNull($artist);
        $this->assertSame('7312776', $artist['id']);
        $this->assertSame('Shirin David', $artist['name']);
        $this->assertNull($artist['slug']);
        $this->assertSame('deezer', $artist['source']);
        $this->assertSame(24, $artist['albums_count']);
        $this->assertSame('https://www.deezer.com/artist/7312776', $artist['url']);
        $this->assertSame('https://api.deezer.com/artist/7312776/image', $artist['artwork']);
    }

    public function test_track_carries_provider_authorized_preview_passthrough(): void
    {
        $this->enableProvider();
        Http::fake([
            '*/track/456' => Http::response([
                'id' => 456,
                'title' => 'Preview Track',
                'duration' => 201,
                'preview' => 'https://cdns-preview.dzcdn.net/stream/abc123.mp3',
                'link' => 'https://www.deezer.com/track/456',
                'artist' => ['id' => 7312776, 'name' => 'Shirin David'],
                'album' => ['id' => 123, 'title' => 'Album X', 'cover_xl' => 'https://api.deezer.com/cover/123/xl'],
            ]),
        ]);

        $track = $this->provider()->getTrack('456');

        $this->assertNotNull($track);
        $this->assertSame('Preview Track', $track['title']);
        $this->assertSame('Shirin David', $track['artist_name']);
        $this->assertSame('Album X', $track['album_title']);
        $this->assertSame(201, $track['duration']);
        $this->assertSame('3:21', $track['duration_human']);
        $this->assertSame('https://cdns-preview.dzcdn.net/stream/abc123.mp3', $track['preview']);
        $this->assertSame('deezer', $track['source']);
    }

    public function test_search_normalizes_each_type(): void
    {
        $this->enableProvider();
        Http::fake([
            '*/search/artist*' => Http::response(['data' => [
                ['id' => 7312776, 'name' => 'Shirin David', 'picture' => 'https://api.deezer.com/artist/7312776/image'],
            ]]),
            '*/search/album*' => Http::response(['data' => [
                ['id' => 123, 'title' => 'Album X', 'record_type' => 'album', 'artist' => ['id' => 7312776, 'name' => 'Shirin David']],
            ]]),
            '*/search/track*' => Http::response(['data' => [
                ['id' => 456, 'title' => 'Track Y', 'duration' => 180, 'artist' => ['id' => 7312776, 'name' => 'Shirin David']],
            ]]),
        ]);

        $provider = $this->provider();

        $this->assertCount(1, $provider->searchArtists('shirin', 5));
        $albums = $provider->searchAlbums('shirin', 5);
        $this->assertCount(1, $albums);
        $this->assertSame('album', $albums[0]['type']);
        $this->assertCount(1, $provider->searchTracks('shirin', 5));
    }

    public function test_album_tracks_inherit_album_context(): void
    {
        $this->enableProvider();
        Http::fake([
            '*/album/123' => Http::response([
                'id' => 123,
                'title' => 'Album X',
                'cover_xl' => 'https://api.deezer.com/cover/123/xl',
                'artist' => ['id' => 7312776, 'name' => 'Shirin David'],
            ]),
            '*/album/123/tracks*' => Http::response(['data' => [
                ['id' => 456, 'title' => 'Track Y', 'duration' => 180, 'track_position' => 1],
            ]]),
        ]);

        $tracks = $this->provider()->getAlbumTracks('123', 25);

        $this->assertCount(1, $tracks);
        $this->assertSame('Album X', $tracks[0]['album_title']);
        $this->assertSame('https://api.deezer.com/cover/123/xl', $tracks[0]['artwork']);
        $this->assertSame(1, $tracks[0]['track_number']);
    }

    public function test_responses_are_cached_per_request(): void
    {
        $this->enableProvider();
        Http::fake([
            '*/artist/7312776' => Http::response(['id' => 7312776, 'name' => 'Shirin David']),
        ]);

        $provider = $this->provider();
        $provider->getArtist('7312776');
        $provider->getArtist('7312776');

        Http::assertSentCount(1);
    }

    public function test_http_errors_degrade_to_null(): void
    {
        $this->enableProvider();
        Http::fake([
            '*' => Http::response(['error' => ['type' => 'DataException']], 404),
        ]);

        $this->assertNull($this->provider()->getArtist('7312776'));
        $this->assertSame([], $this->provider()->searchArtists('shirin', 5));
    }

    public function test_a_failed_attempt_is_retried_once(): void
    {
        $this->enableProvider();
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return $attempts === 1
                ? Http::response([], 500)
                : Http::response(['id' => 7312776, 'name' => 'Shirin David']);
        });

        $artist = $this->provider()->getArtist('7312776');

        $this->assertNotNull($artist);
        $this->assertSame('Shirin David', $artist['name']);
        $this->assertSame(2, $attempts);
    }

    public function test_connection_failure_degrades_to_null(): void
    {
        $this->enableProvider();
        Http::fake(function (): void {
            throw new ConnectionException('Provider unreachable.');
        });

        $this->assertNull($this->provider()->getArtist('7312776'));
        $this->assertSame([], $this->provider()->searchArtists('shirin', 5));
    }

    public function test_malformed_provider_ids_are_rejected(): void
    {
        $this->enableProvider();
        Http::fake();

        $this->assertNull($this->provider()->getArtist('../etc/passwd'));
        $this->assertNull($this->provider()->getTrack('id?x=1'));

        Http::assertNothingSent();
    }
}
