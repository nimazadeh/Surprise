<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrackResource;
use App\Http\Responses\ApiResponse;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tracks = Track::query()
            ->published()
            ->ordered()
            ->with(['artist', 'album', 'genre'])
            ->paginate($this->perPage($request));

        return ApiResponse::ok(TrackResource::collection($tracks));
    }

    public function show(string $slug): JsonResponse
    {
        $track = Track::query()
            ->published()
            ->where('slug', $slug)
            ->with(['artist', 'album', 'genre'])
            ->firstOrFail();

        return ApiResponse::ok(new TrackResource($track));
    }

    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query(
            'per_page', config('shirin.music.api_per_page', 15)
        )));
    }
}
