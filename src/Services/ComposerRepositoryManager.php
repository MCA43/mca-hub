<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\File;

/**
 * Manages VCS repositories that Hub adds for GitHub installs.
 * Path repositories are never modified.
 */
final class ComposerRepositoryManager
{
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
     * Ensure a VCS repository exists in composer.json for the given GitHub URL.
     *
     * @return array{ok: bool, message?: string}
     */
    public function ensureVcsRepository(string $gitUrl): array
    {
        if (! $this->isAllowedGithubUrl($gitUrl)) {
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

        $repos = $data['repositories'] ?? [];
        if (! is_array($repos)) {
            $repos = [];
        }

        $normalized = $this->normalizeGitUrl($gitUrl);
        foreach ($repos as $index => $repo) {
            if (! is_array($repo)) {
                continue;
            }
            $existing = $this->normalizeGitUrl((string) ($repo['url'] ?? ''));
            if ($existing !== '' && $existing === $normalized) {
                if (($repo['type'] ?? '') === 'vcs' && ($repo['no-api'] ?? false) !== true) {
                    $repos[$index]['no-api'] = true;
                    $data['repositories'] = array_values($repos);

                    return $this->writeComposerJson($composerPath, $data);
                }

                return ['ok' => true];
            }
        }

        array_unshift($repos, [
            'type' => 'vcs',
            'url' => $normalized,
            // Avoid GitHub API rate-limit (403) which makes Composer fall back to SSH.
            'no-api' => true,
        ]);

        $data['repositories'] = array_values($repos);

        return $this->writeComposerJson($composerPath, $data);
    }

    /**
     * Remove a Hub-managed VCS repository URL from composer.json.
     *
     * @return array{ok: bool, message?: string}
     */
    public function removeVcsRepository(string $gitUrl): array
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

        $normalized = $this->normalizeGitUrl($gitUrl);
        $filtered = [];
        foreach ($repos as $repo) {
            if (! is_array($repo)) {
                $filtered[] = $repo;
                continue;
            }
            $type = (string) ($repo['type'] ?? '');
            $existing = $this->normalizeGitUrl((string) ($repo['url'] ?? ''));
            if ($type === 'vcs' && $existing === $normalized) {
                continue;
            }
            $filtered[] = $repo;
        }

        $data['repositories'] = array_values($filtered);

        return $this->writeComposerJson($composerPath, $data);
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
