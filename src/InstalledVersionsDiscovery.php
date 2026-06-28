<?php

namespace Mca\Hub;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Route;

final class InstalledVersionsDiscovery
{
    /** @return array<string, array<string, mixed>> */
    public static function discover(): array
    {
        $found = [];

        foreach (InstalledVersions::getInstalledPackages() as $packageName) {
            if (! str_starts_with($packageName, 'mca/')) {
                continue;
            }

            $extra = self::readExtra($packageName);
            $hub = $extra['mca'] ?? null;

            if (! is_array($hub) || ($hub['hidden'] ?? false) === true) {
                continue;
            }

            $slug = (string) ($hub['slug'] ?? self::slugFromName($packageName));

            $found[$slug] = array_filter([
                'name' => $packageName,
                'title' => $hub['title'] ?? self::titleFromSlug($slug),
                'description' => $hub['description'] ?? null,
                'route' => $hub['route'] ?? null,
                'github' => $hub['github'] ?? null,
                'composer' => $hub['composer'] ?? ('composer require '.$packageName),
                'icon' => $hub['icon'] ?? 'box',
                'order' => $hub['order'] ?? 100,
                'frameworks' => $hub['frameworks'] ?? null,
                'hidden' => $hub['hidden'] ?? false,
                'enabled' => fn () => self::routeEnabled($hub['route'] ?? null),
            ], fn ($value) => $value !== null);
        }

        return $found;
    }

    /** @return array<string, mixed> */
    private static function readExtra(string $packageName): array
    {
        $path = InstalledVersions::getInstallPath($packageName);
        if (! is_string($path)) {
            return [];
        }

        $composerFile = $path.'/composer.json';
        if (! is_file($composerFile)) {
            return [];
        }

        $json = file_get_contents($composerFile);
        if (! is_string($json)) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? ($data['extra'] ?? []) : [];
    }

    private static function slugFromName(string $name): string
    {
        return str_contains($name, '/') ? explode('/', $name, 2)[1] : $name;
    }

    private static function titleFromSlug(string $slug): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $slug));
    }

    private static function routeEnabled(?string $route): bool
    {
        if (! is_string($route) || $route === '') {
            return true;
        }

        return Route::has($route);
    }
}
