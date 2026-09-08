<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site identity
    |--------------------------------------------------------------------------
    */
    'site' => [
        'name' => env('APP_NAME', 'SHIRIN'),
        'tagline' => [
            'fa' => 'پلتفرم موسیقی شیرین',
            'en' => 'The SHIRIN Music Platform',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'prefix' => 'admin',
        'idle_timeout_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flag defaults (seeded into `settings`, managed at runtime)
    |--------------------------------------------------------------------------
    */
    'features' => [
        // off | public | registered | premium
        'download_center' => 'off',
        'music_lab' => false,
        'music_lab_premium_only' => true,
        'ads' => false,
        // off | registered | premium
        'user_uploads' => 'off',
        // Dual-source catalogue: external provider enrichment (metadata
        // only). Instantly killable without a redeploy.
        'catalogue_provider' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads (server truth; client hints must never be trusted)
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'audio' => [
            'extensions' => ['mp3', 'ogg', 'opus', 'wav', 'flac', 'm4a'],
            'max_mb' => (int) env('UPLOAD_MAX_AUDIO_MB', 100),
        ],
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_mb' => (int) env('UPLOAD_MAX_IMAGE_MB', 5),
            'min_dimension' => 500,
        ],
        'quarantine_disk' => 'quarantine',
        'purge_tmp_after_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media disks and paths (see config/filesystems.php for disk roots)
    |--------------------------------------------------------------------------
    */
    'media' => [
        'disk' => env('MEDIA_DISK', 'media'),
        'signed_url_ttl_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Music catalogue (Phase 2 foundation)
    |--------------------------------------------------------------------------
    */
    'music' => [
        'api_per_page' => 15,
        'admin_per_page' => 20,
        'artwork_collections' => ['artists', 'albums'],
    ],

    /*
    |--------------------------------------------------------------------------
    | External catalogue provider (Phase 2.5, metadata only — never audio)
    |--------------------------------------------------------------------------
    |
    | The owned database is the primary source; the provider enriches the
    | catalogue. Responses are cached (a short metadata cache, never a copy).
    | Timeouts keep a slow provider well under the frontend request budget.
    |
    */

    'providers' => [
        'deezer' => [
            'enabled' => (bool) env('DEEZER_ENABLED', true),
            'base_url' => env('DEEZER_BASE_URL', 'https://api.deezer.com'),
            'cache_ttl' => 86400,
            'timeout' => 4,
            'connect_timeout' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalogue delivery (Phase 2.5): featured bootstrap, merged search,
    | resolve (hash-link → SEO page), nested endpoint caps.
    |--------------------------------------------------------------------------
    */

    'catalogue' => [
        'featured_provider_id' => env('CATALOGUE_FEATURED_PROVIDER_ID', '7312776'),
        'featured_limit' => 12,
        'search_limit_default' => 12,
        'search_limit_max' => 25,
        'resolve_tracks_limit' => 18,
        'nested_max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO defaults (per-page values override these in later phases)
    |--------------------------------------------------------------------------
    */
    'seo' => [
        'title_suffix' => 'SHIRIN',
        'description' => [
            'fa' => 'پلتفرم دوزبانه موسیقی شیرین',
            'en' => 'SHIRIN bilingual music platform',
        ],
    ],

];
