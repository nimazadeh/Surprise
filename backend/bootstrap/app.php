<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => App\Http\Middleware\AdminMiddleware::class,
        ]);

        // Security headers on every response (cheap, global).
        $middleware->append(App\Http\Middleware\SecurityHeaders::class);

        // Locale resolution for the bilingual web layer.
        $middleware->appendToGroup('web', App\Http\Middleware\SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep the /api/v1 envelope consistent for JSON consumers.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::validation($e->errors());
            }

            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Not found.', 'NOT_FOUND', 404);
            }

            return null;
        });
    })->create();
