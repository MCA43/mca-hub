<?php

return [

    'enabled' => env('MCA_HUB_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | UI locale (null = app locale)
    |--------------------------------------------------------------------------
    */
    'locale' => env('MCA_HUB_LOCALE'),

    /*
    |--------------------------------------------------------------------------
    | Remote package catalog (GitHub raw JSON, Packagist manifest, etc.)
    |--------------------------------------------------------------------------
    */
    'catalog' => [
        'url' => env('MCA_HUB_CATALOG_URL'),
        'cache_ttl' => (int) env('MCA_HUB_CATALOG_CACHE_TTL', 3600),
        'fallback' => __DIR__.'/../catalog/packages.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Framework override (auto-detected when null)
    | Examples: laravel11, laravel13, codeigniter3
    |--------------------------------------------------------------------------
    */
    'framework' => env('MCA_HUB_FRAMEWORK'),

    'routes' => [
        'prefix' => env('MCA_HUB_ROUTE_PREFIX', 'mca'),
        'middleware' => array_filter(explode(',', (string) env('MCA_HUB_MIDDLEWARE', 'web,auth,mca.hub.access'))),
        'name_prefix' => 'mca.hub.',
    ],

    'ui' => [
        'title' => env('MCA_HUB_UI_TITLE'),
        'assets' => [
            'css' => 'vendor/mca-hub/mca-hub.css',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Access — use mca/permission root check when available
    |--------------------------------------------------------------------------
    */
    'access' => [
        'use_permission_root' => env('MCA_HUB_USE_PERMISSION_ROOT', true),
        'role_column' => env('MCA_HUB_ROLE_COLUMN', 'role_id'),
        'root_role' => env('MCA_HUB_ROOT_ROLE', 'root'),
    ],

];
