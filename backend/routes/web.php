<?php

use App\Http\Controllers\Admin\AlbumController as AdminAlbumController;
use App\Http\Controllers\Admin\ArtistController as AdminArtistController;
use App\Http\Controllers\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\Admin\TrackController as AdminTrackController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\MediaController;
use App\Http\Controllers\Web\Music\AlbumController as MusicAlbumController;
use App\Http\Controllers\Web\Music\ArtistController as MusicArtistController;
use App\Http\Controllers\Web\Music\GenreController as MusicGenreController;
use App\Http\Controllers\Web\Music\TrackController as MusicTrackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/takedown', [LegalController::class, 'takedown'])->name('legal.takedown');

Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Music catalogue (Phase 2 detail pages + Phase 2.5 index pages)
|--------------------------------------------------------------------------
*/
Route::get('/artists', [MusicArtistController::class, 'index'])->name('artists.index');
Route::get('/artists/{slug}', [MusicArtistController::class, 'show'])
    ->name('artists.show')->where('slug', '[A-Za-z0-9-_]+');

Route::get('/albums', [MusicAlbumController::class, 'index'])->name('albums.index');
Route::get('/albums/{slug}', [MusicAlbumController::class, 'show'])
    ->name('albums.show')->where('slug', '[A-Za-z0-9-_]+');

Route::get('/tracks', [MusicTrackController::class, 'index'])->name('tracks.index');
Route::get('/tracks/{slug}', [MusicTrackController::class, 'show'])
    ->name('tracks.show')->where('slug', '[A-Za-z0-9-_]+');

Route::get('/genres/{slug}', [MusicGenreController::class, 'show'])
    ->name('genres.show')->where('slug', '[A-Za-z0-9-_]+');

/*
|--------------------------------------------------------------------------
| Public cover proxy (approved catalogue artwork only).
|--------------------------------------------------------------------------
*/
Route::get('/media/covers/{collection}/{file}', [MediaController::class, 'cover'])
    ->name('media.cover')
    ->where(['collection' => '[a-z]+', 'file' => '[A-Za-z0-9-]+\.(jpg|jpeg|png|webp)']);

/*
|--------------------------------------------------------------------------
| Authentication (session based)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Email verification
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Admin foundation (modules plug into config/shirin_nav.php + Admin/*)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function (): void {
        Route::get('/', [App\Http\Controllers\Admin\DashboardController::class, 'index'])
            ->name('dashboard');

        Route::middleware('can:music.manage')->group(function (): void {
            Route::post('/artists/{artist}/toggle', [AdminArtistController::class, 'toggle'])
                ->name('artists.toggle');
            Route::resource('artists', AdminArtistController::class)->except(['show']);

            Route::post('/albums/{album}/toggle', [AdminAlbumController::class, 'toggle'])
                ->name('albums.toggle');
            Route::resource('albums', AdminAlbumController::class)->except(['show']);

            Route::post('/tracks/{track}/toggle', [AdminTrackController::class, 'toggle'])
                ->name('tracks.toggle');
            Route::resource('tracks', AdminTrackController::class)->except(['show']);

            Route::resource('genres', AdminGenreController::class)->except(['show']);
        });
    });

// Backwards-compatible alias used by early API consumers (see routes/api.php).
Route::get('/meta/ping', [MetaController::class, 'ping'])->name('meta.ping');
