<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\CatalogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/resolve — hash-link resolution (Phase 2.5): maps a legacy
 * provider id (#/album/{providerId}) to its canonical home. Owned matches
 * resolve to the SEO page URL; provider matches carry the item (+ children
 * for artist/album) so a deep link renders in-app from one response.
 * A miss is a successful resolution (matched:false), never a 404.
 */
class ResolveController extends Controller
{
    public function __construct(protected CatalogueService $catalogue) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:artist,album,track'],
            'provider_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        return ApiResponse::ok(
            $this->catalogue->resolve((string) $validated['type'], (string) $validated['provider_id'])
        );
    }
}
