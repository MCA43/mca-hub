<?php

namespace Mca\Hub\Services;

final class PackageUpdater
{
    public function __construct(
        private readonly InstalledPackageResolver $installed,
        private readonly UpdateChecker $checker,
        private readonly ComposerProcess $composer,
        private readonly PackageSetupRunner $setup,
    ) {}

    /**
     * @return array{ok: bool, message: string, output: string}
     */
    public function update(string $composerName): array
    {
        if (! config('hub.updates.enabled', true)) {
            return $this->fail('updates.disabled');
        }

        if (! $this->isAllowedPackage($composerName)) {
            return $this->fail('updates.invalid_package');
        }

        if (! $this->installed->isInstalled($composerName)) {
            return $this->fail('updates.not_installed');
        }

        if ($this->installed->isPathInstall($composerName) && ! config('hub.updates.allow_path_update', false)) {
            return $this->fail('updates.path_blocked');
        }

        $args = [
            'update',
            $composerName,
            '--no-interaction',
            '--with-all-dependencies',
        ];

        if (config('hub.updates.prefer_stable', true)) {
            $args[] = '--prefer-stable';
        }

        $result = $this->composer->run($args);
        $this->checker->forget($composerName);

        if (! $result['ok']) {
            return [
                'ok' => false,
                'message' => mca_hub('updates.failed', [
                    'package' => $composerName,
                    'error' => $this->composer->shortError($result['output']),
                ]),
                'output' => $result['output'],
            ];
        }

        $assets = $this->setup->afterUpdate($composerName);

        return [
            'ok' => true,
            'message' => mca_hub('updates.success', ['package' => $composerName]),
            'output' => trim($result['output']."\n".$assets['output']),
        ];
    }

    private function isAllowedPackage(string $name): bool
    {
        if (preg_match('#^mca/[a-z0-9-]+$#', $name) !== 1) {
            return false;
        }

        return in_array($name, $this->installed->installedMcaPackages(), true);
    }

    /** @return array{ok: bool, message: string, output: string} */
    private function fail(string $langKey): array
    {
        return [
            'ok' => false,
            'message' => mca_hub($langKey),
            'output' => '',
        ];
    }
}
