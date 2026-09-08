<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Admin area gate: authenticated + `admin.access` permission.
     * OWNER bypass is centralized in AppServiceProvider::boot (role based).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? ApiResponse::error('Unauthenticated.', 'UNAUTHENTICATED', 401)
                : redirect()->guest(route('login'));
        }

        if ($user->isBanned() || ! $user->can('admin.access')) {
            abort(403);
        }

        return $next($request);
    }
}
