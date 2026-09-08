<?php

namespace App\Services\Providers;

use App\Contracts\MusicProvider;
use App\Http\Resources\TrackResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Deezer public-catalogue adapter behind the MusicProvider contract.
 *
 * Metadata only: public catalogue endpoints, no API key. Preview URLs the
 * provider itself authorizes are passed through untouched — audio is never
 * downloaded, cached, or proxied by this backend (R-01).
 *
 * Every failure mode (disabled, network down, HTTP error, malformed or
 * error payload, timeout) degrades to null / [] so /api/v1 never turns a
 * provider outage into a 5xx.
 */
class DeezerMusicProvider implements MusicProvider
{
    protected string $baseUrl;

    protected int $timeout;

    protected int $connectTimeout;

    protected int $cacheTtl;

    public function __construct(
        ?string $baseUrl = null,
        ?int $timeout = null,
        ?int $connectTimeout = null,
        ?int $cacheTtl = null,
    ) {
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('shirin.providers.deezer.base_url', 'https://api.deezer.com')), '/');
        $this->timeout = (int) ($timeout ?? config('shirin.providers.deezer.timeout', 4));
        $this->connectTimeout = (int) ($connectTimeout ?? config('shirin.providers.deezer.connect_timeout', 3));
        $this->cacheTtl = (int) ($cacheTtl ?? config('shirin.providers.deezer.cache_ttl', 86400));
    }

    public function searchArtists(string $query, int $limit): array
    {
        return $this->normalizeList(
            $this->get('/search/artist', ['q' => $this->query($query), 'limit' => $this->limit($limit)]),
            fn (array $raw): array => $this->normalizeArtist($raw)
        );
    }

    public function searchAlbums(string $query, int $limit): array
    {
        return $this->normalizeList(
            $this->get('/search/album', ['q' => $this->query($query), 'limit' => $this->limit($limit)]),
            fn (array $raw): array => $this->normalizeAlbum($raw)
        );
    }

    public function searchTracks(string $query, int $limit): array
    {
        return $this->normalizeList(
            $this->get('/search/track', ['q' => $this->query($query), 'limit' => $this->limit($limit)]),
            fn (array $raw): array => $this->normalizeTrack($raw)
        );
    }

    public function getArtist(string $providerId): ?array
    {
        $id = $this->id($providerId);

        if ($id === null) {
            return null;
        }

        $payload = $this->get("/artist/{$id}");

        return $payload === null ? null : $this->normalizeArtist($payload);
    }

    public function getArtistAlbums(string $providerId, int $limit): array
    {
        $id = $this->id($providerId);

        if ($id === null) {
            return [];
        }

        return $this->normalizeList(
            $this->get("/artist/{$id}/albums", ['limit' => $this->limit($limit)]),
            fn (array $raw): array => $this->normalizeAlbum($raw)
        );
    }

    public function getArtistTopTracks(string $providerId, int $limit): array
    {
        $id = $this->id($providerId);

        if ($id === null) {
            return [];
        }

        return $this->normalizeList(
            $this->get("/artist/{$id}/top", ['limit' => $this->limit($limit)]),
            fn (array $raw): array => $this->normalizeTrack($raw)
        );
    }

    public function getAlbum(string $providerId): ?array
    {
        $id = $this->id($providerId);

        if ($id === null) {
            return null;
        }

        $payload = $this->get("/album/{$id}");

        return $payload === null ? null : $this->normalizeAlbum($payload);
    }

    public function getAlbumTracks(string $providerId, int $limit): array
    {
        $id = $this->id($providerId);

        if ($id === null) {
            return [];
        }

        $album = $this->getAlbum($providerId);

        if ($album === null) {
            return [];
        }

        return $this->normalizeList(
            $this->get("/album/{$id}/tracks", ['limit' => $this->limit($limit)]),
            fn (array $raw): array => $this->normalizeTrack($raw, $album)
        );
    }

    public function getTrack(string $providerId): ?array
    {
        $id = $this->id($providerId);

        if ($id === null) {
            return null;
        }

        $payload = $this->get("/track/{$id}");

        return $payload === null ? null : $this->normalizeTrack($payload);
    }

    /**
     * Enabled by config (server-side kill switch separate from the runtime
     * `features.catalogue_provider` flag).
     */
    protected function enabled(): bool
    {
        return (bool) config('shirin.providers.deezer.enabled', true);
    }

    /**
     * Cached GET with a single retry. TTL comes from config (default 24 h);
     * a metadata cache, never a copy — nothing is persisted to the catalogue
     * tables.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    protected function get(string $path, array $params = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $key = 'shirin.provider.deezer.'.md5($path.'?'.http_build_query($params));

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $payload = null;

        // One retry with a short pause; the tight timeouts below keep the
        // worst case (~8s) under the frontend's 9s request budget.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $payload = $this->fetch($path, $params);

            if ($payload !== null) {
                break;
            }

            if ($attempt === 1) {
                usleep(100000);
            }
        }

        if ($payload === null) {
            return null;
        }

        Cache::put($key, $payload, $this->cacheTtl);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    protected function fetch(string $path, array $params): ?array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->acceptJson()
                ->get($path, $params);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload) || array_is_list($payload) || isset($payload['error'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Provider ids are user-influenced (resolve endpoint) and embedded in
     * request paths: strictly validated, and invalid ids never reach the
     * network at all.
     */
    protected function id(string $providerId): ?string
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $providerId) ? $providerId : null;
    }

    protected function query(string $query): string
    {
        return mb_substr(trim($query), 0, 100);
    }

    protected function limit(int $limit): int
    {
        return max(1, min(100, $limit));
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $normalizer
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeList(?array $payload, callable $normalizer): array
    {
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return array_values(array_filter(array_map(
            fn ($row): ?array => is_array($row) ? $normalizer($row) : null,
            $rows
        )));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function normalizeArtist(array $raw): array
    {
        return [
            'id' => (string) ($raw['id'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'slug' => null,
            'bio' => null,
            'artwork' => $this->firstArtwork($raw),
            'country' => null,
            'language' => null,
            'featured' => false,
            'source' => 'deezer',
            'provider_id' => isset($raw['id']) ? (string) $raw['id'] : null,
            'albums_count' => isset($raw['nb_album']) ? (int) $raw['nb_album'] : null,
            'tracks_count' => null,
            'url' => $this->safeLink($raw),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function normalizeAlbum(array $raw): array
    {
        $releaseDate = isset($raw['release_date']) ? (string) $raw['release_date'] : null;

        return [
            'id' => (string) ($raw['id'] ?? ''),
            'title' => (string) ($raw['title'] ?? ''),
            'slug' => null,
            'description' => null,
            'artwork' => $this->firstArtwork($raw),
            'release_date' => $releaseDate,
            'release_year' => $releaseDate ? (int) substr($releaseDate, 0, 4) : null,
            'type' => (string) ($raw['record_type'] ?? 'album'),
            'source' => 'deezer',
            'provider_id' => isset($raw['id']) ? (string) $raw['id'] : null,
            'artist_name' => $raw['artist']['name'] ?? null,
            'artist_slug' => null,
            'tracks_count' => isset($raw['nb_tracks']) ? (int) $raw['nb_tracks'] : null,
            'url' => $this->safeLink($raw),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>|null  $contextAlbum
     * @return array<string, mixed>
     */
    protected function normalizeTrack(array $raw, ?array $contextAlbum = null): array
    {
        $album = is_array($raw['album'] ?? null) ? $raw['album'] : ($contextAlbum ?? []);
        $duration = isset($raw['duration']) ? (int) $raw['duration'] : null;

        return [
            'id' => (string) ($raw['id'] ?? ''),
            'title' => (string) ($raw['title'] ?? $raw['title_short'] ?? ''),
            'slug' => null,
            'description' => null,
            'track_number' => isset($raw['track_position']) ? (int) $raw['track_position'] : null,
            'duration' => $duration,
            'duration_human' => TrackResource::format($duration),
            'genre' => null,
            'language' => null,
            'lyrics_available' => false,
            'source' => 'deezer',
            'provider_id' => isset($raw['id']) ? (string) $raw['id'] : null,
            'artist_name' => $raw['artist']['name'] ?? ($contextAlbum['artist_name'] ?? null),
            'artist_slug' => null,
            'album_title' => $album['title'] ?? null,
            'album_slug' => null,
            'artwork' => $this->firstArtwork($album),
            'preview' => $this->safeLink($raw, 'preview'),
            'url' => $this->safeLink($raw),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function firstArtwork(array $raw): ?string
    {
        foreach (['cover_xl', 'cover_big', 'cover_medium', 'cover', 'picture_xl', 'picture_big', 'picture_medium', 'picture'] as $key) {
            $value = $raw[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function safeLink(array $raw, string $key = 'link'): ?string
    {
        $value = $raw[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        // Only http(s) provider links are ever surfaced to clients.
        return str_starts_with($value, 'https://') || str_starts_with($value, 'http://') ? $value : null;
    }
}
