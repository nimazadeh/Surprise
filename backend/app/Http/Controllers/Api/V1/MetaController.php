<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    public function ping(): JsonResponse
    {
        return ApiResponse::ok([
            'app' => config('app.name'),
            'version' => 'v1',
            'time' => now()->toIso8601String(),
        ]);
    }

    public function publicFlags(Request $request, FeatureFlagService $flags): JsonResponse
    {
        return ApiResponse::ok($flags->publicFlags($request->user()));
    }
}
