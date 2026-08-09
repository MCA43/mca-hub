<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\File;

/**
 * Runs package install/setup after composer require (assets, config, migrate).
 * Prefers a fresh `php artisan` subprocess so newly required providers are discovered.
 */
final class PackageSetupRunner
{
    public function __construct(
        private readonly ComposerProcess $composer,
    ) {}

    /**
     * @return array{ok: bool, message?: string, output: string}
     */
    public function afterInstall(string $composerName): array
    {
        $chunks = [];

        $ui = $this->ensureSharedUiAssets();
        $chunks[] = $ui['output'];

        $command = $this->installCommandFor($composerName);
        if ($command !== null) {
            $result = $this->composer->runArtisan(array_values(array_filter([
                $command,
                '--no-interaction',
            ])));
            $chunks[] = $result['output'];

            if ($result['ok']) {
                return [
                    'ok' => true,
                    'output' => trim(implode("\n", array_filter($chunks))),
                ];
            }

            // Install command missing/failed — fall back to asset publish + migrate.
            $fallback = $this->fallbackSetup($composerName);
            $chunks[] = $fallback['output'];

            return [
                'ok' => $fallback['ok'],
                'message' => $fallback['ok']
                    ? null
                    : ($fallback['message'] ?? mca_hub('lifecycle.setup_failed', ['package' => $composerName])),
                'output' => trim(implode("\n", array_filter($chunks))),
            ];
        }

        $fallback = $this->fallbackSetup($composerName);
        $chunks[] = $fallback['output'];

        return [
            'ok' => $fallback['ok'],
            'message' => $fallback['message'] ?? null,
            'output' => trim(implode("\n", array_filter($chunks))),
        ];
    }

    /**
     * Re-publish CSS/JS after composer update.
     *
     * @return array{ok: bool, output: string}
     */
    public function afterUpdate(string $composerName): array
    {
        $slug = $this->slugFor($composerName);
        $tags = array_values(array_filter([
            'mca-permission-assets',
            $slug !== null ? 'mca-'.$slug.'-assets' : null,
            $composerName === 'mca/hub' ? 'mca-hub-assets' : null,
            $composerName === 'mca/uploads' ? 'mca-upload-assets' : null,
        ]));

        return $this->publishTags($tags);
    }

    /**
     * @return array{ok: bool, output: string}
     */
    public function ensureSharedUiAssets(): array
    {
        return $this->publishTags(['mca-permission-assets']);
    }

    private function installCommandFor(string $composerName): ?string
    {
        $map = config('hub.lifecycle.setup_commands', []);
        if (is_array($map) && isset($map[$composerName]) && is_string($map[$composerName]) && $map[$composerName] !== '') {
            return $map[$composerName];
        }

        $slug = $this->slugFor($composerName);
        if ($slug === null) {
            return null;
        }

        // Historical exception: mca/uploads → mca:upload:install
        if ($composerName === 'mca/uploads') {
            return 'mca:upload:install';
        }

        return 'mca:'.$slug.':install';
    }

    private function slugFor(string $composerName): ?string
    {
        if (preg_match('#^mca/([a-z0-9-]+)$#', $composerName, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /**
     * @return array{ok: bool, message?: string, output: string}
     */
    private function fallbackSetup(string $composerName): array
    {
        $slug = $this->slugFor($composerName);
        $tags = [];

        if ($composerName === 'mca/uploads') {
            $tags[] = 'mca-upload-config';
            $tags[] = 'mca-upload-assets';
        } elseif ($slug !== null) {
            $tags[] = 'mca-'.$slug.'-config';
            $tags[] = 'mca-'.$slug.'-assets';
        }

        $publish = $this->publishTags($tags);
        $copied = $this->copyAssetsFromVendor($composerName);
        $migrate = $this->composer->runArtisan(['migrate', '--force', '--no-interaction']);

        $ok = $publish['ok'] || $copied['ok'];

        return [
            'ok' => $ok,
            'message' => $ok ? null : mca_hub('lifecycle.setup_failed', ['package' => $composerName]),
            'output' => trim(implode("\n", array_filter([
                $publish['output'],
                $copied['output'],
                $migrate['output'],
            ]))),
        ];
    }

    /**
     * @param  list<string>  $tags
     * @return array{ok: bool, output: string}
     */
    private function publishTags(array $tags): array
    {
        $outputs = [];
        $anyOk = $tags === [];

        foreach ($tags as $tag) {
            $result = $this->composer->runArtisan([
                'vendor:publish',
                '--tag='.$tag,
                '--force',
                '--no-interaction',
            ]);
            $outputs[] = '['.$tag.'] '.$result['output'];
            // Missing tags still exit 0/1 depending on Laravel version; never hard-fail the whole install.
            $anyOk = true;
        }

        return [
            'ok' => $anyOk,
            'output' => trim(implode("\n", array_filter($outputs))),
        ];
    }

    /**
     * Direct copy when vendor:publish tags are not yet discoverable in a broken state.
     *
     * @return array{ok: bool, output: string}
     */
    private function copyAssetsFromVendor(string $composerName): array
    {
        $slug = $this->slugFor($composerName);
        if ($slug === null) {
            return ['ok' => false, 'output' => ''];
        }

        $vendorAssets = base_path('vendor/mca/'.$slug.'/resources/assets');
        if (! is_dir($vendorAssets)) {
            return ['ok' => false, 'output' => 'assets missing: '.$vendorAssets];
        }

        $publicDir = $composerName === 'mca/uploads'
            ? public_path('vendor/mca-upload')
            : public_path('vendor/mca-'.$slug);

        try {
            File::ensureDirectoryExists($publicDir);
            File::copyDirectory($vendorAssets, $publicDir);
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'output' => 'Copied assets to '.$publicDir,
        ];
    }
}
