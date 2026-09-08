<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MetaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 foundation
|--------------------------------------------------------------------------
|
| Versioned, enveloped JSON (see App\Http\Responses\ApiResponse).
| Domain endpoints arrive in Phase 2+; only the contract exists here.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/ping', [MetaController::class, 'ping'])->name('ping');
    Route::get('/meta/flags', [MetaController::class, 'publicFlags'])->name('meta.flags');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [MeController::class, 'show'])->name('auth.me');
    });
});
