<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\File;

/**
 * Manages Hub-added Composer repositories for GitHub installs.
 * Prefers HTTPS zip "package" repos (no git). Path repositories are never modified.
 */
final class ComposerRepositoryManager
{
    public function __construct(
        private readonly GitHubPackageProbe $githubProbe,
    ) {}

    public function managedStorePath(): string
    {
        return storage_path('app/mca-hub-managed-repos.json');
    }

    /** @return array<string, string> composerName => gitUrl */
    public function managed(): array
    {
        $path = $this->managedStorePath();
        if (! is_file($path)) {
            return [];
        }

        try {
            $data = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $name => $url) {
            if (is_string($name) && is_string($url) && preg_match('#^mca/[a-z0-9-]+$#', $name) === 1) {
                $out[$name] = $url;
            }
        }

        return $out;
    }

    public function remember(string $composerName, string $gitUrl): void
    {
        $all = $this->managed();
        $all[$composerName] = $gitUrl;
        $this->writeManaged($all);
    }

    public function forget(string $composerName): void
    {
        $all = $this->managed();
        unset($all[$composerName]);
        $this->writeManaged($all);
    }

    /**
     * Register a git-free Composer package repository using GitHub branch zip.
     *
     * @return array{ok: bool, message?: string}
     */
    public function ensureGithubDistRepository(string $composerName, string $gitUrl): array
    {
        if (! $this->isAllowedGithubUrl($gitUrl)) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.invalid_repo')];
        }

        $parsed = $this->parseGithubRepo($gitUrl);
        if ($parsed === null) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.invalid_repo')];
        }

        $composerPath = base_path('composer.json');
        if (! is_file($composerPath) || ! is_writable($composerPath)) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.composer_unwritable')];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.composer_invalid')];
        }

        $branch = (string) config('hub.lifecycle.default_branch', 'main');
        $meta = $this->githubProbe->fetchComposerJson($parsed['owner'], $parsed['repo'], $branch);
        if ($meta === null) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.github_no_composer')];
        }

        $remoteName = (string) ($meta['name'] ?? '');
        if ($remoteName !== '' && $remoteName !== $composerName) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.composer_name_mismatch', [
                'expected' => $composerName,
                'actual' => $remoteName,
            ])];
        }

        $constraint = (string) config('hub.lifecycle.default_constraint', 'dev-main');
        $version = $constraint !== '' ? $constraint : 'dev-main';
        $zipUrl = sprintf(
            'https://codeload.github.com/%s/%s/zip/refs/heads/%s',
            rawurlencode($parsed['owner']),
            rawurlencode($parsed['repo']),
            rawurlencode($branch)
        );

        $package = $meta;
        $package['name'] = $composerName;
        $package['version'] = $version;
        $package['dist'] = [
            'type' => 'zip',
            'url' => $zipUrl,
            'reference' => $branch,
        ];
        unset($package['source']);

        $repos = $data['repositories'] ?? [];
        if (! is_array($repos)) {
            $repos = [];
        }

        $normalized = $this->normalizeGitUrl($gitUrl);
        $filtered = [];
        foreach ($repos as $repo) {
            if (! is_array($repo)) {
                $filtered[] = $repo;
                continue;
            }

            $type = (string) ($repo['type'] ?? '');
            if ($type === 'vcs' && $this->normalizeGitUrl((string) ($repo['url'] ?? '')) === $normalized) {
                continue;
            }
            if ($type === 'package' && (string) (($repo['package']['name'] ?? '')) === $composerName) {
                continue;
            }

            $filtered[] = $repo;
        }

        array_unshift($filtered, [
            'type' => 'package',
            'package' => $package,
        ]);

        $data['repositories'] = array_values($filtered);

        return $this->writeComposerJson($composerPath, $data);
    }

    /**
     * @deprecated Prefer ensureGithubDistRepository()
     *
     * @return array{ok: bool, message?: string}
     */
    public function ensureVcsRepository(string $gitUrl): array
    {
        if (preg_match('#github\.com/([^/]+)/(mca-[a-z0-9-]+)#i', $gitUrl, $m) === 1) {
            $name = 'mca/'.substr($m[2], strlen((string) config('hub.github.repo_prefix', 'mca-')));

            return $this->ensureGithubDistRepository($name, $gitUrl);
        }

        return ['ok' => false, 'message' => mca_hub('lifecycle.invalid_repo')];
    }

    /**
     * Remove Hub-managed repository entries for a package.
     *
     * @return array{ok: bool, message?: string}
     */
    public function removeManagedRepository(string $composerName, ?string $gitUrl = null): array
    {
        $composerPath = base_path('composer.json');
        if (! is_file($composerPath) || ! is_writable($composerPath)) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.composer_unwritable')];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.composer_invalid')];
        }

        $repos = $data['repositories'] ?? [];
        if (! is_array($repos)) {
            return ['ok' => true];
        }

        $normalized = is_string($gitUrl) ? $this->normalizeGitUrl($gitUrl) : null;
        $filtered = [];
        foreach ($repos as $repo) {
            if (! is_array($repo)) {
                $filtered[] = $repo;
                continue;
            }

            $type = (string) ($repo['type'] ?? '');
            if ($type === 'package' && (string) (($repo['package']['name'] ?? '')) === $composerName) {
                continue;
            }
            if ($normalized !== null && $type === 'vcs' && $this->normalizeGitUrl((string) ($repo['url'] ?? '')) === $normalized) {
                continue;
            }

            $filtered[] = $repo;
        }

        $data['repositories'] = array_values($filtered);

        return $this->writeComposerJson($composerPath, $data);
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function removeVcsRepository(string $gitUrl): array
    {
        $name = null;
        if (preg_match('#/(mca-[a-z0-9-]+)(?:\.git)?$#i', $this->normalizeGitUrl($gitUrl), $m) === 1) {
            $prefix = (string) config('hub.github.repo_prefix', 'mca-');
            $slug = str_starts_with($m[1], $prefix) ? substr($m[1], strlen($prefix)) : $m[1];
            $name = 'mca/'.$slug;
        }

        return $this->removeManagedRepository((string) $name, $gitUrl);
    }

    public function isAllowedGithubUrl(string $url): bool
    {
        $org = preg_quote((string) config('hub.github.org', 'MCA43'), '#');
        $prefix = preg_quote((string) config('hub.github.repo_prefix', 'mca-'), '#');

        $normalized = $this->normalizeGitUrl($url);

        return (bool) preg_match(
            '#^https://github\.com/'.$org.'/'.$prefix.'[a-z0-9-]+(\.git)?$#i',
            $normalized
        );
    }

    public function normalizeGitUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('#\.git$#i', '', $url) ?? $url;
        $url = rtrim($url, '/');

        return $url;
    }

    public function githubUrlForPackage(string $composerName, ?string $explicitGithub = null): ?string
    {
        if (is_string($explicitGithub) && $explicitGithub !== '') {
            $normalized = $this->normalizeGitUrl($explicitGithub);
            if ($this->isAllowedGithubUrl($normalized)) {
                return $normalized;
            }
        }

        if (preg_match('#^mca/([a-z0-9-]+)$#', $composerName, $m) !== 1) {
            return null;
        }

        $org = (string) config('hub.github.org', 'MCA43');
        $prefix = (string) config('hub.github.repo_prefix', 'mca-');

        return 'https://github.com/'.$org.'/'.$prefix.$m[1];
    }

    /** @return array{owner: string, repo: string}|null */
    private function parseGithubRepo(string $url): ?array
    {
        if (preg_match('~github\.com[/:]([^/]+)/([^/\#\?]+)~i', $url, $m) !== 1) {
            return null;
        }

        return [
            'owner' => $m[1],
            'repo' => preg_replace('/\.git$/i', '', $m[2]) ?? $m[2],
        ];
    }

    /** @param  array<string, string>  $all */
    private function writeManaged(array $all): void
    {
        $dir = dirname($this->managedStorePath());
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put(
            $this->managedStorePath(),
            json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message?: string}
     */
    private function writeComposerJson(string $path, array $data): array
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.composer_invalid')];
        }

        try {
            File::put($path, $json."\n");
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.composer_unwritable')];
        }

        return ['ok' => true];
    }
}
