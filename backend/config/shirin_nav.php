<?php

/*
|--------------------------------------------------------------------------
| Admin navigation (config-driven; future modules plug in here)
|--------------------------------------------------------------------------
|
| Items with a `route` of null are rendered disabled with a "Phase N"
| badge. Nothing links to a route that does not exist yet.
|
*/

return [
    [
        'group' => 'admin.nav.overview',
        'items' => [
            [
                'label' => 'admin.nav.dashboard',
                'route' => 'admin.dashboard',
                'permission' => 'admin.access',
                'phase' => 1,
            ],
        ],
    ],
    [
        'group' => 'admin.nav.music',
        'items' => [
            ['label' => 'admin.nav.artists', 'route' => null, 'permission' => 'music.manage', 'phase' => 2],
            ['label' => 'admin.nav.albums', 'route' => null, 'permission' => 'music.manage', 'phase' => 2],
            ['label' => 'admin.nav.tracks', 'route' => null, 'permission' => 'music.manage', 'phase' => 2],
            ['label' => 'admin.nav.media', 'route' => null, 'permission' => 'music.manage', 'phase' => 2],
            ['label' => 'admin.nav.uploads', 'route' => null, 'permission' => 'uploads.review', 'phase' => 4],
        ],
    ],
    [
        'group' => 'admin.nav.audience',
        'items' => [
            ['label' => 'admin.nav.users', 'route' => null, 'permission' => 'users.manage', 'phase' => 4],
            ['label' => 'admin.nav.advertisements', 'route' => null, 'permission' => 'ads.manage', 'phase' => 5],
        ],
    ],
    [
        'group' => 'admin.nav.system',
        'items' => [
            ['label' => 'admin.nav.premium', 'route' => null, 'permission' => 'settings.manage', 'phase' => 5],
            ['label' => 'admin.nav.analytics', 'route' => null, 'permission' => 'analytics.view', 'phase' => 4],
            ['label' => 'admin.nav.audit', 'route' => null, 'permission' => 'audit.view', 'phase' => 4],
            ['label' => 'admin.nav.settings', 'route' => null, 'permission' => 'settings.manage', 'phase' => 4],
        ],
    ],
];
