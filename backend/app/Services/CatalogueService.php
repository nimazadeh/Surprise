<?php

namespace App\Services;

use App\Contracts\MusicProvider;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\TrackResource;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dual-source catalogue merge (Phase 2.5).
 *
 * Rules (owner-approved):
 *  1. Owned published rows always come first; provider items are appended.
 *  2. Dedupe on provider_id: an owned row with a matching provider_id
 *     suppresses its provider twin (owned wins).
 *  3. Provider items are never persisted — cache only, inside the adapter.
 *  4. Every item carries source: owned|deezer.
 *  5. Flag off (features.catalogue_provider) → the provider is never called.
 *
 * A provider outage degrades silently to owned-only results; the adapter
 * never throws.
 */
class CatalogueService
{
    public function __construct(
        protected MusicProvider $provider,
        protected FeatureFlagService $flags,
    ) {}

    public function providerEnabled(): bool
    {
        return $this->flags->catalogueProviderEnabled();
    }

    /**
     * Merged search: owned LIKE matches first, then provider matches,
     * twin-filtered. Grouped shape (not a paginator) — the search UI shows
     * fixed groups and mixing DB pagination with a remote source would be
     * pagination theater.
     *
     * @return array{artists: array<int, array<string, mixed>>, albums: array<int, array<string, mixed>>, tracks: array<int, array<string, mixed>>}
     */
    public function search(string $query, string $type, int $limit): array
    {
        $types = $type === 'all' ? ['artist', 'album', 'track'] : [$type];

        $results = ['artists' => [], 'albums' => [], 'tracks' => []];

        foreach ($types as $type) {
            $results[$type.'s'] = $this->searchOne($type, $query, $limit);
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function searchOne(string $type, string $query, int $limit): array
    {
        $owned = $this->searchOwned($type, $query, $limit);

        if (! $this->providerEnabled()) {
            return $owned;
        }

        // Rule 1: owned first, provider appended.
        return array_merge($owned, $this->searchProvider($type, $query, $limit));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function searchOwned(string $type, string $query, int $limit): array
    {
        /** @var Builder<Model>|null $queryBuilder */
        $queryBuilder = match ($type) {
            'artist' => Artist::query()->published()->ordered()->withCount(['albums', 'tracks']),
            'album' => Album::query()->published()->ordered()->with('artist')->withCount('tracks'),
            'track' => Track::query()->published()->ordered()->with(['artist', 'album', 'genre']),
            default => null,
        };

        if ($queryBuilder === null) {
            return [];
        }

        return $this->resolveAll($type, $queryBuilder->search($query)->limit($limit)->get());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function searchProvider(string $type, string $query, int $limit): array
    {
        $items = match ($type) {
            'artist' => $this->provider->searchArtists($query, $limit),
            'album' => $this->provider->searchAlbums($query, $limit),
            'track' => $this->provider->searchTracks($query, $limit),
            default => [],
        };

        if ($items === []) {
            return [];
        }

        // Rule 2: one query per type, not per item.
        $ownedProviderIds = $this->ownedProviderIds($type, array_map(
            fn (array $item): ?string => is_string($item['provider_id'] ?? null) && $item['provider_id'] !== '' ? $item['provider_id'] : null,
            $items
        ));

        if ($ownedProviderIds === []) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn (array $item): bool => ! in_array($item['provider_id'] ?? null, $ownedProviderIds, true)
        ));
    }

    /**
     * Provider ids already claimed by owned rows (any status: a draft twin
     * must not double-list either).
     *
     * @param  array<int, string|null>  $providerIds
     * @return array<int, string>
     */
    protected function ownedProviderIds(string $type, array $providerIds): array
    {
        $ids = array_values(array_filter($providerIds));

        if ($ids === []) {
            return [];
        }

        /** @var Builder<Model>|null $queryBuilder */
        $queryBuilder = match ($type) {
            'artist' => Artist::query(),
            'album' => Album::query(),
            'track' => Track::query(),
            default => null,
        };

        if ($queryBuilder === null) {
            return [];
        }

        return $queryBuilder->whereIn('provider_id', $ids)
            ->pluck('provider_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * Resolve owned models through their resources so both sources share
     * one player-ready shape.
     *
     * @param  iterable<int, Model>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function resolveAll(string $type, iterable $rows): array
    {
        $resource = match ($type) {
            'artist' => ArtistResource::class,
            'album' => AlbumResource::class,
            'track' => TrackResource::class,
            default => null,
        };

        if ($resource === null) {
            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            $items[] = $resource::make($row)->resolve();
        }

        return $items;
    }
}
