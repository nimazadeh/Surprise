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
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dual-source catalogue merge (Phase 2.5).
 *
 * Rules (owner-approved):
 *  1. Owned published rows always come first; provider items are appended.
 *  2. Dedupe on provider_id: an owned row (any status) with a matching
 *     provider_id suppresses its provider twin (owned wins; managed items
 *     never double-list, published or not).
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

        foreach ($types as $one) {
            $results[$one.'s'] = $this->searchOne($one, $query, $limit);
        }

        return $results;
    }

    /**
     * Featured bootstrap: one round trip for the player home screen.
     * Owned featured artist wins; else the configured provider artist.
     * Owned albums/tracks first, provider items appended (twin-filtered).
     *
     * @return array{artist: array<string, mixed>|null, albums: array<int, array<string, mixed>>, tracks: array<int, array<string, mixed>>}
     */
    public function featured(): array
    {
        $limit = max(1, (int) config('shirin.catalogue.featured_limit', 12));

        $featuredArtist = Artist::query()
            ->published()
            ->featured()
            ->ordered()
            ->withCount(['albums', 'tracks'])
            ->first();

        return [
            'artist' => $featuredArtist !== null
                ? ArtistResource::make($featuredArtist)->resolve()
                : $this->providerArtist(),
            'albums' => $this->featuredAlbums($limit),
            'tracks' => $this->featuredTracks($limit),
        ];
    }

    /**
     * Resolve a hash-link address (provider id) to its canonical item:
     * an owned row with that provider_id wins (published ones resolve to
     * their SEO page; unpublished ones miss — managed items never fall
     * through to the provider). Otherwise the provider is asked live.
     * Never throws; a miss is matched:false.
     *
     * @return array{matched: bool, source: string|null, slug: string|null, url: string|null, item: array<string, mixed>|null, tracks: array<int, array<string, mixed>>|null}
     */
    public function resolve(string $type, string $providerId): array
    {
        $model = $this->ownedModelByProviderId($type, $providerId);

        if ($model !== null) {
            return $model->isPublished()
                ? $this->ownedMatch($type, $model)
                : $this->miss();
        }

        if (! $this->providerEnabled()) {
            return $this->miss();
        }

        $item = match ($type) {
            'artist' => $this->provider->getArtist($providerId),
            'album' => $this->provider->getAlbum($providerId),
            'track' => $this->provider->getTrack($providerId),
            default => null,
        };

        if ($item === null) {
            return $this->miss();
        }

        // Provider artist/album resolves carry their children so a deep
        // link renders in-app from this single response.
        $tracks = match ($type) {
            'artist' => $this->provider->getArtistTopTracks($providerId, $this->resolveTracksLimit()),
            'album' => $this->provider->getAlbumTracks($providerId, $this->resolveTracksLimit()),
            default => null,
        };

        return [
            'matched' => true,
            'source' => 'deezer',
            'slug' => null,
            'url' => $item['url'] ?? null,
            'item' => $item,
            'tracks' => $tracks,
        ];
    }

    /**
     * @return array{matched: bool, source: string|null, slug: string|null, url: string|null, item: array<string, mixed>|null, tracks: null}
     */
    protected function miss(): array
    {
        return ['matched' => false, 'source' => null, 'slug' => null, 'url' => null, 'item' => null, 'tracks' => null];
    }

    /**
     * @return array{matched: bool, source: string, slug: string, url: string|null, item: array<string, mixed>, tracks: null}
     */
    protected function ownedMatch(string $type, Model $model): array
    {
        match ($type) {
            'artist' => $model->loadCount(['albums', 'tracks']),
            'album' => $model->load(['artist'])->loadCount('tracks'),
            'track' => $model->load(['artist', 'album', 'genre']),
            default => null,
        };

        $resource = $this->resourceFor($type);
        $item = $resource === null ? [] : $resource::make($model)->resolve();

        return [
            'matched' => true,
            'source' => 'owned',
            'slug' => (string) $model->getRouteKey(),
            'url' => $item['url'] ?? null,
            'item' => $item,
            'tracks' => null,
        ];
    }

    /**
     * Owned row claiming this provider id — any status, soft-deletes
     * included (a managed item never falls through to the provider).
     */
    protected function ownedModelByProviderId(string $type, string $providerId): ?Model
    {
        /** @var Builder<Model>|null $queryBuilder */
        $queryBuilder = match ($type) {
            'artist' => Artist::query(),
            'album' => Album::query(),
            'track' => Track::query(),
            default => null,
        };

        return $queryBuilder?->withTrashed()->where('provider_id', $providerId)->first();
    }

    protected function providerArtist(): ?array
    {
        if (! $this->providerEnabled()) {
            return null;
        }

        return $this->provider->getArtist($this->featuredProviderId());
    }

    protected function featuredProviderId(): string
    {
        return (string) config('shirin.catalogue.featured_provider_id', '7312776');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function featuredAlbums(int $limit): array
    {
        $owned = Album::query()
            ->published()
            ->ordered()
            ->with('artist')
            ->withCount('tracks')
            ->limit($limit)
            ->get();

        $items = $this->resolveAll('album', $owned);

        if (! $this->providerEnabled()) {
            return $items;
        }

        return array_merge(
            $items,
            $this->filterTwins('album', $this->provider->getArtistAlbums($this->featuredProviderId(), $limit))
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function featuredTracks(int $limit): array
    {
        // No play counts exist yet (Phase 4 counters): latest additions
        // stand in for "top" until analytics do.
        $owned = Track::query()
            ->published()
            ->with(['artist', 'album', 'genre'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = $this->resolveAll('track', $owned);

        if (! $this->providerEnabled()) {
            return $items;
        }

        return array_merge(
            $items,
            $this->filterTwins('track', $this->provider->getArtistTopTracks($this->featuredProviderId(), $limit))
        );
    }

    protected function resolveTracksLimit(): int
    {
        return max(1, (int) config('shirin.catalogue.resolve_tracks_limit', 18));
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

        $items = match ($type) {
            'artist' => $this->provider->searchArtists($query, $limit),
            'album' => $this->provider->searchAlbums($query, $limit),
            'track' => $this->provider->searchTracks($query, $limit),
            default => [],
        };

        // Rule 1: owned first, provider appended.
        return array_merge($owned, $this->filterTwins($type, $items));
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
     * Rule 2: drop provider items whose provider_id an owned row already
     * claims (any status — managed items never double-list). One query
     * per type, not per item.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function filterTwins(string $type, array $items): array
    {
        if ($items === []) {
            return [];
        }

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
        $resource = $this->resourceFor($type);

        if ($resource === null) {
            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            $items[] = $resource::make($row)->resolve();
        }

        return $items;
    }

    /**
     * @return class-string<JsonResource>|null
     */
    protected function resourceFor(string $type): ?string
    {
        return match ($type) {
            'artist' => ArtistResource::class,
            'album' => AlbumResource::class,
            'track' => TrackResource::class,
            default => null,
        };
    }
}
