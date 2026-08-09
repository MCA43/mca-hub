<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class UpdateChecker
{
    public function __construct(
        private readonly InstalledPackageResolver $installed,
    ) {}

    /**
     * @param  array{name: string, slug?: string, github?: ?string}  $package
     * @return array{
     *     enabled: bool,
     *     installed_version: ?string,
     *     latest_version: ?string,
     *     install_source: string,
     *     update_status: string,
     *     can_update: bool,
     *     compared_at: ?string,
     *     error: ?string
     * }
     */
    public function check(array $package): array
    {
        $name = (string) ($package['name'] ?? '');
        $empty = [
            'enabled' => false,
            'installed_version' => null,
            'latest_version' => null,
            'install_source' => 'unknown',
            'update_status' => 'unknown',
            'can_update' => false,
            'compared_at' => null,
            'error' => null,
        ];

        if (! config('hub.updates.enabled', true) || $name === '' || ! $this->installed->isInstalled($name)) {
            return $empty;
        }

        $ttl = (int) config('hub.updates.cache_ttl', 3600);
        $cacheKey = 'mca.hub.updates.'.md5($name);

        return Cache::remember($cacheKey, $ttl, fn () => $this->resolve($package));
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     * @return list<array<string, mixed>>
     */
    public function enrich(array $packages): array
    {
        return array_map(function (array $package) {
            $update = $this->check($package);

            return array_merge($package, [
                'install_source' => $update['install_source'],
                'latest_version' => $update['latest_version'],
                'update_status' => $update['update_status'],
                'can_update' => $update['can_update'],
                'update_error' => $update['error'],
            ]);
        }, $packages);
    }

    public function forget(string $composerName): void
    {
        Cache::forget('mca.hub.updates.'.md5($composerName));
    }

    public function forgetAll(): void
    {
        // Best-effort: individual keys are forgotten on update; full flush of tagged cache not used.
        foreach ($this->installed->installedMcaPackages() as $name) {
            $this->forget($name);
        }
    }

    /**
     * @param  array{name: string, slug?: string, github?: ?string}  $package
     * @return array{
     *     enabled: bool,
     *     installed_version: ?string,
     *     latest_version: ?string,
     *     install_source: string,
     *     update_status: string,
     *     can_update: bool,
     *     compared_at: ?string,
     *     error: ?string
     * }
     */
    private function resolve(array $package): array
    {
        $name = (string) $package['name'];
        $installedVersion = $this->installed->version($name);
        $source = $this->installed->installSource($name);
        $now = now()->toIso8601String();

        $repo = $this->parseGithubRepo((string) ($package['github'] ?? ''));
        if ($repo === null) {
            $slug = (string) ($package['slug'] ?? str_replace('mca/', '', $name));
            $org = (string) config('hub.github.org', 'MCA43');
            $prefix = (string) config('hub.github.repo_prefix', 'mca-');
            $repo = ['owner' => $org, 'repo' => $prefix.$slug];
        }

        try {
            $latest = $this->fetchLatestVersion($repo['owner'], $repo['repo']);
        } catch (\Throwable $e) {
            return [
                'enabled' => true,
                'installed_version' => $installedVersion,
                'latest_version' => null,
                'install_source' => $source,
                'update_status' => 'unknown',
                'can_update' => false,
                'compared_at' => $now,
                'error' => $e->getMessage(),
            ];
        }

        if ($latest === null || $latest === '') {
            return [
                'enabled' => true,
                'installed_version' => $installedVersion,
                'latest_version' => null,
                'install_source' => $source,
                'update_status' => 'unknown',
                'can_update' => false,
                'compared_at' => $now,
                'error' => null,
            ];
        }

        $status = $this->compareVersions((string) $installedVersion, $latest);
        $isPath = $source === 'path';
        $allowPath = (bool) config('hub.updates.allow_path_update', false);
        $canUpdate = $status === 'update_available' && (! $isPath || $allowPath);

        if ($status === 'update_available' && $isPath && ! $allowPath) {
            $status = 'path_linked';
            // Still signal that a newer GitHub tag exists
            $canUpdate = false;
        }

        return [
            'enabled' => true,
            'installed_version' => $installedVersion,
            'latest_version' => $latest,
            'install_source' => $source,
            'update_status' => $status,
            'can_update' => $canUpdate,
            'compared_at' => $now,
            'error' => null,
        ];
    }

    /**
     * @return array{owner: string, repo: string}|null
     */
    private function parseGithubRepo(string $url): ?array
    {
        if ($url === '') {
            return null;
        }

        if (preg_match('~github\.com[/:]([^/]+)/([^/\#\?]+)~i', $url, $m) !== 1) {
            return null;
        }

        return [
            'owner' => $m[1],
            'repo' => preg_replace('/\.git$/', '', $m[2]) ?? $m[2],
        ];
    }

    private function fetchLatestVersion(string $owner, string $repo): ?string
    {
        $request = Http::timeout(12)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'mca-hub',
                'Accept' => 'application/vnd.github+json',
            ]);

        $token = config('hub.github.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        $release = $request->get("https://api.github.com/repos/{$owner}/{$repo}/releases/latest");
        if ($release->successful()) {
            $tag = (string) ($release->json('tag_name') ?? '');
            if ($tag !== '') {
                return $this->normalizeVersion($tag);
            }
        }

        $tags = $request->get("https://api.github.com/repos/{$owner}/{$repo}/tags", [
            'per_page' => 10,
        ]);

        if (! $tags->successful()) {
            return null;
        }

        $list = $tags->json();
        if (! is_array($list) || $list === []) {
            return null;
        }

        $versions = [];
        foreach ($list as $row) {
            $name = is_array($row) ? (string) ($row['name'] ?? '') : '';
            if ($name === '') {
                continue;
            }
            $versions[] = $this->normalizeVersion($name);
        }

        if ($versions === []) {
            return null;
        }

        usort($versions, fn (string $a, string $b) => version_compare($b, $a));

        return $versions[0];
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if (str_starts_with(strtolower($version), 'v') && preg_match('/^v\d/i', $version) === 1) {
            return substr($version, 1);
        }

        return $version;
    }

    private function compareVersions(string $installed, string $latest): string
    {
        $installedNorm = $this->normalizeVersion($installed);
        $latestNorm = $this->normalizeVersion($latest);

        // Branch aliases (dev-main): treat any GitHub release as "newer available"
        if (str_starts_with($installedNorm, 'dev-') || $installedNorm === 'dev-main') {
            return config('hub.updates.dev_shows_update', true)
                ? 'update_available'
                : 'unknown';
        }

        if (! preg_match('/^\d+(\.\d+)*/', $installedNorm) || ! preg_match('/^\d+(\.\d+)*/', $latestNorm)) {
            return $installedNorm === $latestNorm ? 'uptodate' : 'unknown';
        }

        $cmp = version_compare($installedNorm, $latestNorm);

        return match (true) {
            $cmp < 0 => 'update_available',
            $cmp === 0 => 'uptodate',
            default => 'uptodate',
        };
    }
}
