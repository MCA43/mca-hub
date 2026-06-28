<?php

namespace Mca\Hub\Services;

use Composer\InstalledVersions;

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

    /** @return list<string> */
    public function installedMcaPackages(): array
    {
        $packages = InstalledVersions::getInstalledPackages();

        return array_values(array_filter($packages, fn (string $name) => str_starts_with($name, 'mca/')));
    }
}
