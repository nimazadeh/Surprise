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
