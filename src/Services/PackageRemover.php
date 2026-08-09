<?php

namespace Mca\Hub\Services;

final class PackageRemover
{
    public function __construct(
        private readonly InstalledPackageResolver $installed,
        private readonly PackageLifecycle $lifecycle,
        private readonly ComposerRepositoryManager $repos,
        private readonly ComposerProcess $composer,
        private readonly UpdateChecker $checker,
    ) {}

    /**
     * @return array{ok: bool, message: string, output: string}
     */
    public function remove(string $composerName): array
    {
        if (! $this->lifecycle->enabled()) {
            return $this->fail('lifecycle.disabled');
        }

        if (! $this->lifecycle->isValidPackageName($composerName)) {
            return $this->fail('updates.invalid_package');
        }

        if ($this->lifecycle->isProtected($composerName)) {
            return $this->fail('lifecycle.protected');
        }

        if (! $this->installed->isInstalled($composerName)) {
            return $this->fail('updates.not_installed');
        }

        if ($this->installed->isPathInstall($composerName)) {
            return $this->fail('lifecycle.path_blocked_remove');
        }

        $result = $this->composer->run([
            'remove',
            $composerName,
            '--no-interaction',
            '--with-all-dependencies',
        ]);

        $this->checker->forget($composerName);

        if (! $result['ok']) {
            return [
                'ok' => false,
                'message' => mca_hub('lifecycle.remove_failed', [
                    'package' => $composerName,
                    'error' => $this->composer->shortError($result['output']),
                ]),
                'output' => $result['output'],
            ];
        }

        $managed = $this->repos->managed();
        if (isset($managed[$composerName])) {
            $this->repos->removeVcsRepository($managed[$composerName]);
            $this->repos->forget($composerName);
        }

        return [
            'ok' => true,
            'message' => mca_hub('lifecycle.remove_success', ['package' => $composerName]),
            'output' => $result['output'],
        ];
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
