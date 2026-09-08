<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSlugs;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\TrackResource;
use App\Http\Responses\ApiResponse;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    use ResolvesSlugs;

    public function index(Request $request): JsonResponse
    {
        $artists = Artist::query()
            ->published()
            ->ordered()
            ->withCount(['albums', 'tracks'])
            ->paginate($this->perPage($request));

        return ApiResponse::ok(ArtistResource::collection($artists));
    }

    public function show(string $slug): JsonResponse
    {
        $artist = Artist::query()
            ->published()
            ->where('slug', $slug)
            ->withCount(['albums', 'tracks'])
            ->firstOrFail();

        return ApiResponse::ok(new ArtistResource($artist));
    }

    /**
     * Nested: the artist's published albums (Phase 2.5). Plain array,
     * capped — catalogues are small in v1 and the player wants one shape.
     */
    public function albums(string $slug): JsonResponse|RedirectResponse
    {
        if (($artist = $this->findArtist($slug)) === null) {
            return $this->redirectOr404($slug, 'artists.albums', SlugRedirect::SUBJECT_ARTIST);
        }

        $albums = $artist->albums()
            ->published()
            ->ordered()
            ->with('artist')
            ->withCount('tracks')
            ->limit($this->nestedMax())
            ->get();

        return ApiResponse::ok(AlbumResource::collection($albums));
    }

    /**
     * Nested: the artist's published tracks (Phase 2.5).
     */
    public function tracks(string $slug): JsonResponse|RedirectResponse
    {
        if (($artist = $this->findArtist($slug)) === null) {
            return $this->redirectOr404($slug, 'artists.tracks', SlugRedirect::SUBJECT_ARTIST);
        }

        $tracks = $artist->tracks()
            ->published()
            ->ordered()
            ->with(['artist', 'album', 'genre'])
            ->limit($this->nestedMax())
            ->get();

        return ApiResponse::ok(TrackResource::collection($tracks));
    }

    protected function findArtist(string $slug): ?Artist
    {
        return Artist::query()->published()->where('slug', $slug)->first();
    }

    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query(
            'per_page', config('shirin.music.api_per_page', 15)
        )));
    }
}
