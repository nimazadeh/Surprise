<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Baseline hardening headers. CSP allows inline styles because the player
     * UI sets CSS variables inline; scripts stay strictly same-origin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data: https:; media-src 'self' https:; ".
            "script-src 'self'; style-src 'self' 'unsafe-inline'; connect-src 'self'; ".
            "frame-ancestors 'self'; base-uri 'self'; form-action 'self'"
        );

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
