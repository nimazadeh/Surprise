<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesSlugs;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\TrackResource;
use App\Http\Responses\ApiResponse;
use App\Models\Album;
use App\Models\SlugRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    use ResolvesSlugs;

    public function index(Request $request): JsonResponse
    {
        $albums = Album::query()
            ->published()
            ->ordered()
            ->with('artist')
            ->withCount('tracks')
            ->paginate($this->perPage($request));

        return ApiResponse::ok(AlbumResource::collection($albums));
    }

    public function show(string $slug): JsonResponse
    {
        $album = Album::query()
            ->published()
            ->where('slug', $slug)
            ->with('artist')
            ->withCount('tracks')
            ->firstOrFail();

        return ApiResponse::ok(new AlbumResource($album));
    }

    /**
     * Nested: the album's published tracks in track-number order
     * (Phase 2.5). Plain array, capped.
     */
    public function tracks(string $slug): JsonResponse|RedirectResponse
    {
        $album = Album::query()->published()->where('slug', $slug)->first();

        if ($album === null) {
            return $this->redirectOr404($slug, 'albums.tracks', SlugRedirect::SUBJECT_ALBUM);
        }

        $tracks = $album->tracks()
            ->published()
            ->ordered()
            ->with(['artist', 'album', 'genre'])
            ->limit($this->nestedMax())
            ->get();

        return ApiResponse::ok(TrackResource::collection($tracks));
    }

    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query(
            'per_page', config('shirin.music.api_per_page', 15)
        )));
    }
}
