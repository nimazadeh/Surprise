<?php

use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Controllers\Api\V1\ArtistController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\TrackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (enveloped JSON, see App\Http\Responses\ApiResponse)
|--------------------------------------------------------------------------
|
| Foundation contract (Phase 1) + music catalogue reads (Phase 2) +
| catalogue delivery (Phase 2.5): nested endpoints and merged search.
| Stream signing stays deferred until R-01 (licensing) resolves.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/ping', [MetaController::class, 'ping'])->name('ping');
    Route::get('/meta/flags', [MetaController::class, 'publicFlags'])->name('meta.flags');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [MeController::class, 'show'])->name('auth.me');
    });

    // Search + resolve carry their own tighter limiter (30/min).
    Route::middleware('throttle:30,1')->group(function (): void {
        Route::get('/search', SearchController::class)->name('search');
    });

    Route::middleware('throttle:120,1')->group(function (): void {
        Route::get('/artists', [ArtistController::class, 'index'])->name('artists.index');
        Route::get('/artists/{slug}', [ArtistController::class, 'show'])
            ->name('artists.show')->where('slug', '[A-Za-z0-9-_]+');
        Route::get('/artists/{slug}/albums', [ArtistController::class, 'albums'])
            ->name('artists.albums')->where('slug', '[A-Za-z0-9-_]+');
        Route::get('/artists/{slug}/tracks', [ArtistController::class, 'tracks'])
            ->name('artists.tracks')->where('slug', '[A-Za-z0-9-_]+');

        Route::get('/albums', [AlbumController::class, 'index'])->name('albums.index');
        Route::get('/albums/{slug}', [AlbumController::class, 'show'])
            ->name('albums.show')->where('slug', '[A-Za-z0-9-_]+');
        Route::get('/albums/{slug}/tracks', [AlbumController::class, 'tracks'])
            ->name('albums.tracks')->where('slug', '[A-Za-z0-9-_]+');

        Route::get('/tracks', [TrackController::class, 'index'])->name('tracks.index');
        Route::get('/tracks/{slug}', [TrackController::class, 'show'])
            ->name('tracks.show')->where('slug', '[A-Za-z0-9-_]+');
    });
});
