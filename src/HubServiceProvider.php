<?php

namespace Mca\Hub;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Mca\Hub\Http\Middleware\EnsureHubAccess;
use Mca\Hub\Services\GitHubOrgCatalog;
use Mca\Hub\Services\HubRegistry;
use Mca\Hub\Services\InstalledPackageResolver;
use Mca\Hub\Services\PackageCatalog;

class HubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hub.php', 'hub');

        $this->app->singleton(HubRegistry::class);
        $this->app->singleton(InstalledPackageResolver::class);
        $this->app->singleton(GitHubOrgCatalog::class);
        $this->app->singleton(PackageCatalog::class);
    }

    public function boot(): void
    {
        if (! config('hub.enabled', true)) {
            return;
        }

        $this->registerPublishing();
        $this->registerMiddleware();
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mca-hub');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mca-hub');
        $this->registerRoutes();
        $this->discoverComposerPackages();
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/hub.php' => config_path('hub.php'),
        ], 'mca-hub-config');

        $this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/mca-hub'),
        ], 'mca-hub-assets');

        $this->publishes([
            __DIR__.'/../catalog/packages.json' => base_path('mca-packages.json'),
        ], 'mca-hub-catalog');
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('mca.hub.access', EnsureHubAccess::class);
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    protected function discoverComposerPackages(): void
    {
        $packages = InstalledVersionsDiscovery::discover();

        foreach ($packages as $slug => $meta) {
            McaHub::register($slug, $meta);
        }
    }
}
