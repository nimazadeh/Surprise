<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlbumResource;
use App\Http\Responses\ApiResponse;
use App\Models\Album;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
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

    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->query(
            'per_page', config('shirin.music.api_per_page', 15)
        )));
    }
}
