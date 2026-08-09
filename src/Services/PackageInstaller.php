<?php

namespace Mca\Hub\Services;

final class PackageInstaller
{
    public function __construct(
        private readonly InstalledPackageResolver $installed,
        private readonly PackageCatalog $catalog,
        private readonly PackageLifecycle $lifecycle,
        private readonly ComposerRepositoryManager $repos,
        private readonly ComposerProcess $composer,
        private readonly UpdateChecker $checker,
    ) {}

    /**
     * @return array{ok: bool, message: string, output: string}
     */
    public function install(string $composerName): array
    {
        if (! $this->lifecycle->enabled()) {
            return $this->fail('lifecycle.disabled');
        }

        if (! $this->lifecycle->isValidPackageName($composerName)) {
            return $this->fail('updates.invalid_package');
        }

        if ($this->installed->isInstalled($composerName)) {
            return $this->fail('lifecycle.already_installed');
        }

        $entry = $this->catalog->findByName($composerName);
        if ($entry === null) {
            return $this->fail('lifecycle.not_in_catalog');
        }

        if (($entry['status'] ?? '') === 'planned') {
            return $this->fail('lifecycle.planned');
        }

        $gitUrl = $this->repos->githubUrlForPackage(
            $composerName,
            is_string($entry['github'] ?? null) ? (string) $entry['github'] : null
        );

        if ($gitUrl === null || ! $this->repos->isAllowedGithubUrl($gitUrl)) {
            return $this->fail('lifecycle.invalid_repo');
        }

        $ensure = $this->repos->ensureVcsRepository($gitUrl);
        if (! ($ensure['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($ensure['message'] ?? mca_hub('lifecycle.failed', [
                    'package' => $composerName,
                    'error' => 'repo',
                ])),
                'output' => '',
            ];
        }

        $args = [
            'require',
            $composerName,
            '--no-interaction',
            '--with-all-dependencies',
        ];

        if (config('hub.lifecycle.prefer_stable', config('hub.updates.prefer_stable', true))) {
            $args[] = '--prefer-stable';
        }

        $result = $this->composer->run($args);
        $this->checker->forget($composerName);

        if (! $result['ok']) {
            return [
                'ok' => false,
                'message' => mca_hub('lifecycle.install_failed', [
                    'package' => $composerName,
                    'error' => $this->composer->shortError($result['output']),
                ]),
                'output' => $result['output'],
            ];
        }

        $this->repos->remember($composerName, $gitUrl);

        return [
            'ok' => true,
            'message' => mca_hub('lifecycle.install_success', ['package' => $composerName]),
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
