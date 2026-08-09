<?php

namespace Mca\Hub\Services;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\File;

final class InstalledPackageResolver
{
    public function isInstalled(string $composerName): bool
    {
        return InstalledVersions::isInstalled($composerName);
    }

    public function version(string $composerName): ?string
    {
        if (! $this->isInstalled($composerName)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($composerName);
    }

    public function reference(string $composerName): ?string
    {
        if (! $this->isInstalled($composerName)) {
            return null;
        }

        try {
            $ref = InstalledVersions::getReference($composerName);

            return is_string($ref) && $ref !== '' ? $ref : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function installPath(string $composerName): ?string
    {
        if (! $this->isInstalled($composerName)) {
            return null;
        }

        try {
            $path = InstalledVersions::getInstallPath($composerName);

            return is_string($path) && $path !== '' ? $path : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * path | dist | vcs | unknown
     */
    public function installSource(string $composerName): string
    {
        if (! $this->isInstalled($composerName)) {
            return 'unknown';
        }

        $path = $this->installPath($composerName);
        if ($path === null) {
            return 'unknown';
        }

        $real = realpath($path) ?: $path;
        $packagesDir = realpath(base_path('packages/mca'));
        if ($packagesDir && str_starts_with($real, $packagesDir)) {
            return 'path';
        }

        // Symlink into packages/mca or vendor junction to path repo
        if (is_link($path)) {
            $target = realpath($path);
            if ($packagesDir && $target && str_starts_with($target, $packagesDir)) {
                return 'path';
            }
        }

        $installedJson = base_path('vendor/composer/installed.json');
        if (is_file($installedJson)) {
            try {
                $data = json_decode((string) File::get($installedJson), true, 512, JSON_THROW_ON_ERROR);
                $packages = $data['packages'] ?? $data;
                if (is_array($packages)) {
                    foreach ($packages as $pkg) {
                        if (! is_array($pkg) || ($pkg['name'] ?? '') !== $composerName) {
                            continue;
                        }
                        $distType = (string) ($pkg['dist']['type'] ?? '');
                        if ($distType === 'path') {
                            return 'path';
                        }
                        if (isset($pkg['source']['type']) && (string) $pkg['source']['type'] === 'git') {
                            return 'vcs';
                        }
                        if ($distType !== '') {
                            return 'dist';
                        }
                    }
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        return 'unknown';
    }

    public function isPathInstall(string $composerName): bool
    {
        return $this->installSource($composerName) === 'path';
    }

    /** @return list<string> */
    public function installedMcaPackages(): array
    {
        $packages = InstalledVersions::getInstalledPackages();

        return array_values(array_filter($packages, fn (string $name) => str_starts_with($name, 'mca/')));
    }
}
