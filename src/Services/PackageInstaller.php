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
        private readonly GitHubPackageProbe $githubProbe,
        private readonly PackageSetupRunner $setup,
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

        $probe = $this->githubProbe->assertInstallable($gitUrl);
        if (! ($probe['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($probe['message'] ?? mca_hub('lifecycle.github_no_composer')),
                'output' => '',
            ];
        }

        $remoteName = (string) ($probe['package_name'] ?? '');
        if ($remoteName !== '' && $remoteName !== $composerName) {
            return [
                'ok' => false,
                'message' => mca_hub('lifecycle.composer_name_mismatch', [
                    'expected' => $composerName,
                    'actual' => $remoteName,
                ]),
                'output' => '',
            ];
        }

        $ensure = $this->repos->ensureGithubDistRepository($composerName, $gitUrl);
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

        // MCA GitHub packages typically publish branch `main` only (no Packagist tags yet).
        $constraint = (string) config('hub.lifecycle.default_constraint', 'dev-main');
        $requireSpec = $constraint !== '' ? $composerName.':'.$constraint : $composerName;

        $args = [
            'require',
            $requireSpec,
            '--no-interaction',
            '--with-all-dependencies',
            '--prefer-dist',
        ];

        // Explicit branch constraints must not be blocked by --prefer-stable.

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

        $setup = $this->setup->afterInstall($composerName);
        $output = trim($result['output']."\n".$setup['output']);

        if (! ($setup['ok'] ?? false)) {
            return [
                'ok' => true,
                'message' => mca_hub('lifecycle.install_success_setup_warn', [
                    'package' => $composerName,
                    'error' => (string) ($setup['message'] ?? mca_hub('lifecycle.setup_failed', [
                        'package' => $composerName,
                    ])),
                ]),
                'output' => $output,
            ];
        }

        return [
            'ok' => true,
            'message' => mca_hub('lifecycle.install_success', ['package' => $composerName]),
            'output' => $output,
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
