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
    | GitHub catalog (mca-* repos under a user or organization)
    |--------------------------------------------------------------------------
    | Lists public repos with repo_prefix (default mca-). Reads composer.json
    | extra.mca from each repo when fetch_composer_extra is true.
    | account_type: org | user | auto (try org, then user)
    */
    'github' => [
        'enabled' => env('MCA_HUB_GITHUB_CATALOG', true),
        'org' => env('MCA_HUB_GITHUB_ORG', 'MCA43'),
        'account_type' => env('MCA_HUB_GITHUB_ACCOUNT_TYPE', 'auto'),
        'repo_prefix' => env('MCA_HUB_GITHUB_REPO_PREFIX', 'mca-'),
        'token' => env('MCA_HUB_GITHUB_TOKEN'),
        'cache_ttl' => (int) env('MCA_HUB_GITHUB_CACHE_TTL', 3600),
        'fetch_composer_extra' => env('MCA_HUB_GITHUB_FETCH_COMPOSER', true),
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
            'ui_css' => 'vendor/mca-permission/mca-ui.css',
            'ui_js' => 'vendor/mca-permission/mca-ui.js',
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

    /*
    |--------------------------------------------------------------------------
    | Package updates (GitHub release/tag check + allowlisted composer update)
    |--------------------------------------------------------------------------
    */
    'updates' => [
        'enabled' => env('MCA_HUB_UPDATES', true),
        'cache_ttl' => (int) env('MCA_HUB_UPDATES_CACHE_TTL', 3600),
        'composer_bin' => env('MCA_HUB_COMPOSER_BIN', 'composer'),
        // Absolute php.exe for Hub composer runs (auto-detected when null)
        'php_bin' => env('MCA_HUB_PHP_BIN'),
        'timeout' => (int) env('MCA_HUB_UPDATE_TIMEOUT', 300),
        'prefer_stable' => env('MCA_HUB_UPDATE_PREFER_STABLE', true),
        // Path/symlink monorepo packages: show newer GitHub tags but block composer update by default
        'allow_path_update' => env('MCA_HUB_ALLOW_PATH_UPDATE', false),
        // Installed as dev-main / branch alias → treat any GitHub tag as "update available"
        'dev_shows_update' => env('MCA_HUB_DEV_SHOWS_UPDATE', true),
        // Always list mca/hub on the dashboard (for self-update)
        'show_hub' => env('MCA_HUB_SHOW_HUB_CARD', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle — install / remove via Hub (root only, confirm modal)
    |--------------------------------------------------------------------------
    | Path/symlink packages cannot be installed or removed from the UI.
    | Protected packages cannot be removed.
    */
    'lifecycle' => [
        'enabled' => env('MCA_HUB_LIFECYCLE', true),
        'prefer_stable' => env('MCA_HUB_LIFECYCLE_PREFER_STABLE', true),
        // Branch constraint for Hub installs (packages are not on Packagist yet).
        'default_constraint' => env('MCA_HUB_LIFECYCLE_CONSTRAINT', 'dev-main'),
        'protected' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MCA_HUB_PROTECTED_PACKAGES', 'mca/hub,mca/permission'))
        ))),
    ],

];
