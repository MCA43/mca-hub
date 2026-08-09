<?php

namespace Mca\Hub\Services;

use Symfony\Component\Process\Process;

final class PackageUpdater
{
    public function __construct(
        private readonly InstalledPackageResolver $installed,
        private readonly UpdateChecker $checker,
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

        $bin = (string) config('hub.updates.composer_bin', 'composer');
        $timeout = (int) config('hub.updates.timeout', 300);
        $cwd = base_path();

        $command = [
            $bin,
            'update',
            $composerName,
            '--no-interaction',
            '--with-all-dependencies',
        ];

        if (config('hub.updates.prefer_stable', true)) {
            $command[] = '--prefer-stable';
        }

        $process = new Process($command, $cwd, null, null, $timeout);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => mca_hub('updates.failed', ['package' => $composerName, 'error' => $e->getMessage()]),
                'output' => $e->getMessage(),
            ];
        }

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        $this->checker->forget($composerName);

        if (! $process->isSuccessful()) {
            return [
                'ok' => false,
                'message' => mca_hub('updates.failed', [
                    'package' => $composerName,
                    'error' => $this->shortError($output),
                ]),
                'output' => $output,
            ];
        }

        return [
            'ok' => true,
            'message' => mca_hub('updates.success', ['package' => $composerName]),
            'output' => $output,
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

    private function shortError(string $output): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: [])));
        if ($lines === []) {
            return 'composer failed';
        }

        $last = $lines[array_key_last($lines)] ?? 'composer failed';

        return mb_substr($last, 0, 240);
    }
}
