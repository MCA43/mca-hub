<?php

namespace Mca\Hub\Http\Controllers;

use Illuminate\Contracts\View\View;
use Mca\Hub\Services\PackageCatalog;
use Mca\Hub\Support\FrameworkDetector;
use Mca\Hub\Support\McaHubLocale;

class HubController
{
    public function __construct(
        private readonly PackageCatalog $catalog,
    ) {}

    public function index(): View
    {
        McaHubLocale::apply();

        $catalogMeta = $this->catalog->loadCatalog();

        return view('mca-hub::index', [
            'title' => config('hub.ui.title') ?: mca_hub('app.title'),
            'framework' => FrameworkDetector::current(),
            'frameworkLabel' => FrameworkDetector::label(),
            'packages' => $this->catalog->packagesForCurrentFramework(),
            'catalogUpdatedAt' => $catalogMeta['updated_at'] ?? null,
            'catalogUrl' => config('hub.catalog.url'),
            'catalogSources' => $catalogMeta['sources'] ?? [],
        ]);
    }
}
