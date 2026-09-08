<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\CatalogueService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/catalogue/featured — the player's home bootstrap (Phase 2.5):
 * featured artist (owned is_featured first, else the configured provider
 * artist) + owned albums/tracks with provider items appended,
 * twin-filtered. One round trip replaces the frontend's legacy
 * resolveArtist + albums + top-tracks sequence.
 */
class CatalogueController extends Controller
{
    public function __construct(protected CatalogueService $catalogue) {}

    public function featured(): JsonResponse
    {
        return ApiResponse::ok($this->catalogue->featured());
    }
}
