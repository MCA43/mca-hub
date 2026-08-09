<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\Http;

final class GitHubPackageProbe
{
    /**
     * @return array{ok: bool, message?: string}
     */
    public function assertInstallable(string $gitUrl): array
    {
        $repo = $this->parseGithubRepo($gitUrl);
        if ($repo === null) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.invalid_repo')];
        }

        $request = Http::timeout(10)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'mca-hub']);

        $token = config('hub.github.token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $meta = $request->get("https://api.github.com/repos/{$repo['owner']}/{$repo['repo']}");
            if ($meta->successful()) {
                $size = (int) ($meta->json('size') ?? 0);
                if ($size <= 0) {
                    return ['ok' => false, 'message' => mca_hub('lifecycle.github_repo_empty')];
                }
            }
        } catch (\Throwable) {
            // Continue to composer.json probe
        }

        foreach (['main', 'master'] as $branch) {
            try {
                $url = "https://raw.githubusercontent.com/{$repo['owner']}/{$repo['repo']}/{$branch}/composer.json";
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'mca-hub'])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data) && isset($data['name'])) {
                        return ['ok' => true];
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return ['ok' => false, 'message' => mca_hub('lifecycle.github_no_composer')];
    }

    /** @return array{owner: string, repo: string}|null */
    private function parseGithubRepo(string $url): ?array
    {
        $url = trim($url);
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
}
