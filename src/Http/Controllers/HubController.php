<?php

namespace Mca\Hub\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mca\Hub\Services\PackageCatalog;
use Mca\Hub\Services\PackageUpdater;
use Mca\Hub\Services\UpdateChecker;
use Mca\Hub\Support\FrameworkDetector;
use Mca\Hub\Support\McaHubLocale;

class HubController
{
    public function __construct(
        private readonly PackageCatalog $catalog,
        private readonly UpdateChecker $updateChecker,
        private readonly PackageUpdater $updater,
    ) {}

    public function index(): View
    {
        McaHubLocale::apply();

        $catalogMeta = $this->catalog->loadCatalog();
        $packages = $this->catalog->packagesForCurrentFramework();

        $hubPackage = collect($packages)->first(fn (array $p) => ($p['is_hub'] ?? false) === true);
        $updateCount = collect($packages)->filter(function (array $p) {
            return in_array($p['update_status'] ?? '', ['update_available', 'path_linked'], true);
        })->count();

        return view('mca-hub::index', [
            'title' => config('hub.ui.title') ?: mca_hub('app.title'),
            'framework' => FrameworkDetector::current(),
            'frameworkLabel' => FrameworkDetector::label(),
            'packages' => $packages,
            'hubPackage' => $hubPackage,
            'updateCount' => $updateCount,
            'catalogUpdatedAt' => $catalogMeta['updated_at'] ?? null,
            'catalogUrl' => config('hub.catalog.url'),
            'catalogSources' => $catalogMeta['sources'] ?? [],
            'updatesEnabled' => (bool) config('hub.updates.enabled', true),
        ]);
    }

    public function refreshUpdates(): RedirectResponse
    {
        McaHubLocale::apply();
        $this->updateChecker->forgetAll();

        return redirect()
            ->route(config('hub.routes.name_prefix', 'mca.hub.').'index')
            ->with('status', mca_hub('updates.refreshed'));
    }

    public function update(Request $request): RedirectResponse
    {
        McaHubLocale::apply();

        $name = (string) $request->validate([
            'package' => ['required', 'string', 'max:80', 'regex:/^mca\\/[a-z0-9-]+$/'],
        ])['package'];

        $result = $this->updater->update($name);

        if (! $result['ok']) {
            return redirect()
                ->route(config('hub.routes.name_prefix', 'mca.hub.').'index')
                ->withErrors(['update' => $result['message']]);
        }

        return redirect()
            ->route(config('hub.routes.name_prefix', 'mca.hub.').'index')
            ->with('status', $result['message']);
    }
}
