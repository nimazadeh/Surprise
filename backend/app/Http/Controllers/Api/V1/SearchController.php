<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\CatalogueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/search — merged owned + provider search (Phase 2.5).
 * Grouped result shape: {query, type, artists[], albums[], tracks[]}.
 */
class SearchController extends Controller
{
    public function __construct(protected CatalogueService $catalogue) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', 'string', 'in:all,artist,album,track'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('shirin.catalogue.search_limit_max', 25)],
        ]);

        $type = (string) ($validated['type'] ?? 'all');
        $limit = (int) ($validated['limit'] ?? config('shirin.catalogue.search_limit_default', 12));

        $results = $this->catalogue->search((string) $validated['q'], $type, $limit);

        return ApiResponse::ok([
            'query' => (string) $validated['q'],
            'type' => $type,
            'artists' => $results['artists'],
            'albums' => $results['albums'],
            'tracks' => $results['tracks'],
        ]);
    }
}
