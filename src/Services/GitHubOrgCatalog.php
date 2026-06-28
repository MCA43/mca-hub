<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class GitHubOrgCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        if (! config('hub.github.enabled', false)) {
            return [];
        }

        $org = (string) config('hub.github.org', 'MCA43');
        if ($org === '') {
            return [];
        }

        $ttl = (int) config('hub.github.cache_ttl', 3600);

        return Cache::remember('mca.hub.github.'.$org, $ttl, function () use ($org) {
            return $this->fetchEntries($org);
        });
    }

    /** @return list<array<string, mixed>> */
    private function fetchEntries(string $org): array
    {
        $repos = $this->fetchRepositories($org);
        $prefix = (string) config('hub.github.repo_prefix', 'mca-');
        $entries = [];

        foreach ($repos as $repo) {
            if (! is_array($repo)) {
                continue;
            }

            $repoName = (string) ($repo['name'] ?? '');
            if ($repoName === '' || ($prefix !== '' && ! str_starts_with($repoName, $prefix))) {
                continue;
            }

            $slug = $prefix !== '' && str_starts_with($repoName, $prefix)
                ? substr($repoName, strlen($prefix))
                : $repoName;

            if ($slug === '') {
                continue;
            }

            $composerName = 'mca/'.$slug;
            $extra = config('hub.github.fetch_composer_extra', true)
                ? $this->readComposerExtra($org, $repoName, (string) ($repo['default_branch'] ?? 'main'))
                : [];

            $hubExtra = is_array($extra['mca'] ?? null) ? $extra['mca'] : [];
            $description = (string) ($repo['description'] ?? $extra['description'] ?? '');

            $title = $hubExtra['title'] ?? null;
            if (is_string($title)) {
                $titleField = ['en' => $title, 'tr' => $title];
            } elseif (is_array($title)) {
                $titleField = $title;
            } else {
                $label = ucfirst(str_replace('-', ' ', $slug));
                $titleField = ['en' => $label, 'tr' => $label];
            }

            $descField = $hubExtra['description'] ?? null;
            if (is_string($descField)) {
                $descriptionField = ['en' => $descField, 'tr' => $descField];
            } elseif (is_array($descField)) {
                $descriptionField = $descField;
            } else {
                $descriptionField = ['en' => $description, 'tr' => $description];
            }

            $entries[] = array_filter([
                'name' => $composerName,
                'slug' => (string) ($hubExtra['slug'] ?? $slug),
                'title' => $titleField,
                'description' => $descriptionField,
                'frameworks' => $hubExtra['frameworks'] ?? ['laravel11', 'laravel12', 'laravel13'],
                'github' => (string) ($repo['html_url'] ?? "https://github.com/{$org}/{$repoName}"),
                'composer' => $hubExtra['composer'] ?? ('composer require '.$composerName),
                'route' => $hubExtra['route'] ?? null,
                'icon' => $hubExtra['icon'] ?? 'box',
                'order' => $hubExtra['order'] ?? 100,
                'hidden' => $hubExtra['hidden'] ?? false,
                'status' => $hubExtra['status'] ?? 'stable',
                'source' => 'github',
            ], fn ($value) => $value !== null);
        }

        return $entries;
    }

    /** @return list<array<string, mixed>> */
    private function fetchRepositories(string $owner): array
    {
        $accountType = (string) config('hub.github.account_type', 'auto');

        if ($accountType === 'user') {
            return $this->requestRepositories('users', $owner);
        }

        if ($accountType === 'org') {
            return $this->requestRepositories('orgs', $owner);
        }

        $repos = $this->requestRepositories('orgs', $owner);
        if ($repos !== []) {
            return $repos;
        }

        return $this->requestRepositories('users', $owner);
    }

    /** @return list<array<string, mixed>> */
    private function requestRepositories(string $kind, string $owner): array
    {
        $request = Http::timeout(12)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'mca-hub']);

        $token = config('hub.github.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->get("https://api.github.com/{$kind}/{$owner}/repos", [
                'per_page' => 100,
                'type' => 'public',
                'sort' => 'updated',
            ]);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function readComposerExtra(string $org, string $repo, string $branch): array
    {
        $branches = array_values(array_unique([$branch, 'main', 'master']));

        foreach ($branches as $tryBranch) {
            try {
                $url = "https://raw.githubusercontent.com/{$org}/{$repo}/{$tryBranch}/composer.json";
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'mca-hub'])
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $data = $response->json();
                if (is_array($data)) {
                    return $data;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }
}
