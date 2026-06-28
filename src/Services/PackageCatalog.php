<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mca\Hub\Support\FrameworkDetector;

final class PackageCatalog
{
    public function __construct(
        private readonly InstalledPackageResolver $installed,
        private readonly GitHubOrgCatalog $githubCatalog,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function packagesForCurrentFramework(): array
    {
        $framework = FrameworkDetector::current();
        $catalog = $this->loadCatalog();
        $registered = app(HubRegistry::class)->all();

        $items = [];

        foreach ($catalog['packages'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $frameworks = $entry['frameworks'] ?? [];
            if (is_array($frameworks) && $frameworks !== [] && ! in_array($framework, $frameworks, true)) {
                continue;
            }

            if (($entry['hidden'] ?? false) === true) {
                continue;
            }

            $name = (string) ($entry['name'] ?? '');
            $slug = (string) ($entry['slug'] ?? '');
            $reg = $registered[$slug] ?? $registered[$name] ?? null;

            $items[] = $this->normalizeEntry($entry, $reg, $framework);
        }

        foreach ($registered as $slug => $reg) {
            if ($reg['hidden'] ?? false) {
                continue;
            }

            $already = collect($items)->contains(fn (array $item) => ($item['slug'] ?? '') === $slug || ($item['name'] ?? '') === ($reg['name'] ?? ''));

            if (! $already) {
                $items[] = $this->normalizeEntry([
                    'name' => $reg['name'] ?? ('mca/'.$slug),
                    'slug' => $slug,
                    'title' => ['en' => $reg['title'] ?? $slug, 'tr' => $reg['title'] ?? $slug],
                    'description' => ['en' => $reg['description'] ?? '', 'tr' => $reg['description'] ?? ''],
                    'frameworks' => $reg['frameworks'] ?? [FrameworkDetector::current()],
                    'route' => $reg['route'] ?? null,
                    'github' => $reg['github'] ?? null,
                    'composer' => $reg['composer'] ?? null,
                    'icon' => $reg['icon'] ?? 'box',
                    'order' => $reg['order'] ?? 100,
                ], $reg, $framework);
            }
        }

        usort($items, fn (array $a, array $b) => ($a['order'] ?? 100) <=> ($b['order'] ?? 100));

        return $items;
    }

    /** @return array{updated_at: ?string, packages: list<array<string, mixed>>} */
    public function loadCatalog(): array
    {
        $url = config('hub.catalog.url');
        $ttl = (int) config('hub.catalog.cache_ttl', 3600);
        $fallback = config('hub.catalog.fallback');

        if (is_string($url) && $url !== '') {
            return Cache::remember('mca.hub.catalog', $ttl, function () use ($url, $fallback) {
                try {
                    $response = Http::timeout(8)->acceptJson()->get($url);
                    if ($response->successful()) {
                        $data = $response->json();
                        if (is_array($data) && isset($data['packages'])) {
                            return $this->mergeCatalogSources($data);
                        }
                    }
                } catch (\Throwable) {
                    // fallback below
                }

                return $this->mergeCatalogSources($this->readFallback($fallback));
            });
        }

        return $this->mergeCatalogSources($this->readFallback($fallback));
    }

    /**
     * @param  array{updated_at: ?string, packages: list<array<string, mixed>>}  $catalog
     * @return array{updated_at: ?string, packages: list<array<string, mixed>>, sources: list<string>}
     */
    private function mergeCatalogSources(array $catalog): array
    {
        $sources = [];
        $packages = $catalog['packages'] ?? [];

        if (is_string(config('hub.catalog.url')) && config('hub.catalog.url') !== '') {
            $sources[] = 'remote';
        } elseif (is_file((string) config('hub.catalog.fallback'))) {
            $sources[] = 'local';
        }

        $githubEntries = $this->githubCatalog->entries();
        if ($githubEntries !== []) {
            $sources[] = 'github';
            $packages = $this->mergePackageLists($packages, $githubEntries);
        }

        return [
            'updated_at' => $catalog['updated_at'] ?? null,
            'packages' => array_values($packages),
            'sources' => $sources,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    private function mergePackageLists(array $base, array $incoming): array
    {
        $indexed = [];

        foreach ($base as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = (string) ($entry['slug'] ?? $entry['name'] ?? '');
            if ($key !== '') {
                $indexed[$key] = $entry;
            }
        }

        foreach ($incoming as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = (string) ($entry['slug'] ?? $entry['name'] ?? '');
            if ($key === '') {
                continue;
            }
            if (! isset($indexed[$key])) {
                $indexed[$key] = $entry;
                continue;
            }
            $indexed[$key] = array_merge($entry, $indexed[$key]);
        }

        return array_values($indexed);
    }

    /** @param  array<string, mixed>  $entry
     * @param  array<string, mixed>|null  $reg
     * @return array<string, mixed>
     */
    private function normalizeEntry(array $entry, ?array $reg, string $framework): array
    {
        $locale = app()->getLocale();
        $name = (string) ($entry['name'] ?? '');
        $slug = (string) ($entry['slug'] ?? '');
        $installed = $this->installed->isInstalled($name);
        $version = $this->installed->version($name);

        $route = $reg['route'] ?? $entry['route'] ?? null;
        $enabled = $reg['enabled'] ?? true;
        if (is_callable($enabled)) {
            $enabled = (bool) $enabled();
        }

        $status = (string) ($entry['status'] ?? 'stable');
        if ($status === 'stable' && $installed) {
            $cardStatus = 'installed';
        } elseif ($status === 'planned') {
            $cardStatus = 'planned';
        } elseif ($installed) {
            $cardStatus = 'installed';
        } else {
            $cardStatus = 'available';
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'title' => $this->translateField($entry['title'] ?? $slug, $locale),
            'description' => $this->translateField($entry['description'] ?? '', $locale),
            'frameworks' => $entry['frameworks'] ?? [$framework],
            'framework_labels' => array_map(
                fn (string $f) => FrameworkDetector::label($f),
                $entry['frameworks'] ?? [$framework],
            ),
            'github' => $reg['github'] ?? $entry['github'] ?? null,
            'composer' => $reg['composer'] ?? $entry['composer'] ?? ('composer require '.$name),
            'route' => is_string($route) && $route !== '' && $enabled && $installed ? $route : null,
            'icon' => $reg['icon'] ?? $entry['icon'] ?? 'box',
            'order' => (int) ($reg['order'] ?? $entry['order'] ?? 100),
            'installed' => $installed,
            'version' => $version,
            'status' => $cardStatus,
            'route_exists' => is_string($route) && $route !== '' ? app('router')->has($route) : false,
        ];
    }

    private function translateField(mixed $field, string $locale): string
    {
        if (is_string($field)) {
            return $field;
        }

        if (! is_array($field)) {
            return '';
        }

        return (string) ($field[$locale] ?? $field['en'] ?? $field['tr'] ?? reset($field) ?: '');
    }

    /** @return array{updated_at: ?string, packages: list<array<string, mixed>>} */
    private function readFallback(?string $path): array
    {
        if (! is_string($path) || ! is_file($path)) {
            return ['updated_at' => null, 'packages' => []];
        }

        $json = file_get_contents($path);
        if (! is_string($json)) {
            return ['updated_at' => null, 'packages' => []];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : ['updated_at' => null, 'packages' => []];
    }
}
