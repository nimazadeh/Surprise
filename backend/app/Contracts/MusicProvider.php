<?php

namespace App\Contracts;

/**
 * Read-only music metadata provider (dual-source architecture).
 *
 * The owned catalogue in the database is the primary source; an external
 * provider supplies additional metadata-only items. Implementations must
 * NEVER throw for outages — they return null / empty arrays so callers
 * degrade to owned-only content. They must never fetch, store, or proxy
 * audio: only catalogue metadata and provider-authorized preview URLs
 * (R-01 stands until resolved).
 *
 * Item arrays use the same keys as the API resources (player-ready shape):
 * id, name|title, slug (null for provider items), artwork, source, url…
 * Provider track items additionally carry a nullable `preview` URL that the
 * provider itself authorizes and serves.
 */
interface MusicProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchArtists(string $query, int $limit): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchAlbums(string $query, int $limit): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchTracks(string $query, int $limit): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getArtist(string $providerId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getArtistAlbums(string $providerId, int $limit): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getArtistTopTracks(string $providerId, int $limit): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getAlbum(string $providerId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAlbumTracks(string $providerId, int $limit): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getTrack(string $providerId): ?array;
}
