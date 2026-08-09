<?php

namespace Mca\Hub\Services;

final class PackageLifecycle
{
    public function __construct(
        private readonly InstalledPackageResolver $installed,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('hub.lifecycle.enabled', true);
    }

    /** @return list<string> */
    public function protectedPackages(): array
    {
        $list = config('hub.lifecycle.protected', ['mca/hub', 'mca/permission']);

        return array_values(array_filter(array_map('strval', is_array($list) ? $list : [])));
    }

    public function isProtected(string $composerName): bool
    {
        return in_array($composerName, $this->protectedPackages(), true);
    }

    public function isValidPackageName(string $name): bool
    {
        return preg_match('#^mca/[a-z0-9-]+$#', $name) === 1;
    }

    /**
     * @param  array<string, mixed>  $package  Normalized hub card
     * @return array{
     *     can_install: bool,
     *     can_remove: bool,
     *     is_protected: bool,
     *     is_path: bool,
     *     lifecycle_enabled: bool
     * }
     */
    public function flags(array $package): array
    {
        $name = (string) ($package['name'] ?? '');
        $installed = (bool) ($package['installed'] ?? false);
        $status = (string) ($package['status'] ?? '');
        $source = (string) ($package['install_source'] ?? ($installed ? $this->installed->installSource($name) : 'unknown'));
        $isPath = $source === 'path' || ($package['update_status'] ?? '') === 'path_linked';
        $protected = $this->isProtected($name);
        $enabled = $this->enabled();
        $hasGithub = is_string($package['github'] ?? null) && $package['github'] !== '';

        $canInstall = $enabled
            && ! $installed
            && $status === 'available'
            && $this->isValidPackageName($name)
            && $hasGithub;

        $canRemove = $enabled
            && $installed
            && ! $isPath
            && ! $protected
            && $this->isValidPackageName($name);

        return [
            'can_install' => $canInstall,
            'can_remove' => $canRemove,
            'is_protected' => $protected,
            'is_path' => $isPath,
            'lifecycle_enabled' => $enabled,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     * @return list<array<string, mixed>>
     */
    public function enrich(array $packages): array
    {
        return array_map(function (array $package) {
            return array_merge($package, $this->flags($package));
        }, $packages);
    }
}