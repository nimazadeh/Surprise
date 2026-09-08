<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS (Phase 2.5)
    |--------------------------------------------------------------------------
    |
    | The static SHIRIN frontend (GitHub Pages) calls /api/v1 from a
    | different origin than the Laravel backend. Only the versioned API
    | surface is exposed cross-origin: web/session routes, Sanctum and
    | the media proxy stay same-origin. Origins come from the
    | FRONTEND_ORIGINS env (comma-separated) — never a wildcard in
    | production. No credentials: public reads are token-less, so
    | cookies never cross origins.
    |
    */

    'paths' => ['api/v1/*'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
