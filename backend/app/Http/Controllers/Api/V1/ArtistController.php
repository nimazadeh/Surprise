<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use App\Http\Responses\ApiResponse;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
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

    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query(
            'per_page', config('shirin.music.api_per_page', 15)
        )));
    }
}
