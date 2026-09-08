<?php

namespace Tests\Fixtures;

use App\Contracts\MusicProvider;

/**
 * Test double for the provider contract: configurable payloads, a failing
 * mode that simulates an outage (null/[] like the real adapter), and call
 * counters. Never touches the network.
 */
class FakeMusicProvider implements MusicProvider
{
    public bool $failing = false;

    public int $searchCalls = 0;

    public int $getCalls = 0;

    /** @var array<int, array<string, mixed>> */
    public array $searchArtistResults = [];

    /** @var array<int, array<string, mixed>> */
    public array $searchAlbumResults = [];

    /** @var array<int, array<string, mixed>> */
    public array $searchTrackResults = [];

    /** @var array<string, array<string, mixed>> */
    public array $artists = [];

    /** @var array<string, array<string, mixed>> */
    public array $albums = [];

    /** @var array<string, array<string, mixed>> */
    public array $tracks = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $artistAlbums = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $artistTopTracks = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $albumTracks = [];

    public function searchArtists(string $query, int $limit): array
    {
        $this->searchCalls++;

        return $this->failing ? [] : array_slice($this->searchArtistResults, 0, $limit);
    }

    public function searchAlbums(string $query, int $limit): array
    {
        $this->searchCalls++;

        return $this->failing ? [] : array_slice($this->searchAlbumResults, 0, $limit);
    }

    public function searchTracks(string $query, int $limit): array
    {
        $this->searchCalls++;

        return $this->failing ? [] : array_slice($this->searchTrackResults, 0, $limit);
    }

    public function getArtist(string $providerId): ?array
    {
        $this->getCalls++;

        return $this->failing ? null : ($this->artists[$providerId] ?? null);
    }

    public function getArtistAlbums(string $providerId, int $limit): array
    {
        $this->getCalls++;

        return $this->failing ? [] : array_slice($this->artistAlbums[$providerId] ?? [], 0, $limit);
    }

    public function getArtistTopTracks(string $providerId, int $limit): array
    {
        $this->getCalls++;

        return $this->failing ? [] : array_slice($this->artistTopTracks[$providerId] ?? [], 0, $limit);
    }

    public function getAlbum(string $providerId): ?array
    {
        $this->getCalls++;

        return $this->failing ? null : ($this->albums[$providerId] ?? null);
    }

    public function getAlbumTracks(string $providerId, int $limit): array
    {
        $this->getCalls++;

        return $this->failing ? [] : array_slice($this->albumTracks[$providerId] ?? [], 0, $limit);
    }

    public function getTrack(string $providerId): ?array
    {
        $this->getCalls++;

        return $this->failing ? null : ($this->tracks[$providerId] ?? null);
    }
}
