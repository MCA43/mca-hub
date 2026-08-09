<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

final class GitHubPackageProbe
{
    /**
     * @return array{ok: bool, message?: string, package_name?: string}
     */
    public function assertInstallable(string $gitUrl): array
    {
        $repo = $this->parseGithubRepo($gitUrl);
        if ($repo === null) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.invalid_repo')];
        }

        $owner = $repo['owner'];
        $name = $repo['repo'];
        $client = $this->http();

        $defaultBranch = 'main';
        $size = null;

        try {
            $meta = $client->get("https://api.github.com/repos/{$owner}/{$name}");
            if ($meta->status() === 404) {
                return ['ok' => false, 'message' => mca_hub('lifecycle.package_not_on_github')];
            }
            if ($meta->successful()) {
                $branch = (string) ($meta->json('default_branch') ?? '');
                if ($branch !== '') {
                    $defaultBranch = $branch;
                }
                $size = (int) ($meta->json('size') ?? 0);
            }
        } catch (\Throwable) {
            // Continue with branch fallbacks
        }

        $composer = $this->fetchComposerJson($owner, $name, $defaultBranch);
        if ($composer !== null) {
            return [
                'ok' => true,
                'package_name' => (string) ($composer['name'] ?? ''),
            ];
        }

        if ($size !== null && $size <= 0) {
            return ['ok' => false, 'message' => mca_hub('lifecycle.github_repo_empty')];
        }

        return ['ok' => false, 'message' => mca_hub('lifecycle.github_no_composer')];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchComposerJson(string $owner, string $repo, string $defaultBranch = 'main'): ?array
    {
        $branches = array_values(array_unique(array_filter([
            $defaultBranch,
            'main',
            'master',
        ])));

        foreach ($branches as $branch) {
            $fromApi = $this->fetchComposerViaContentsApi($owner, $repo, $branch);
            if ($fromApi !== null) {
                return $fromApi;
            }

            $fromRaw = $this->fetchComposerViaRaw($owner, $repo, $branch);
            if ($fromRaw !== null) {
                return $fromRaw;
            }
        }

        // Default branch tip (Contents API without ref)
        $fromApi = $this->fetchComposerViaContentsApi($owner, $repo, null);
        if ($fromApi !== null) {
            return $fromApi;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchComposerViaContentsApi(string $owner, string $repo, ?string $ref): ?array
    {
        try {
            $query = $ref ? ['ref' => $ref] : [];
            $response = $this->http()->get(
                "https://api.github.com/repos/{$owner}/{$repo}/contents/composer.json",
                $query
            );

            if (! $response->successful()) {
                return null;
            }

            $encoding = (string) ($response->json('encoding') ?? '');
            $content = (string) ($response->json('content') ?? '');
            if ($encoding === 'base64' && $content !== '') {
                $decoded = base64_decode(preg_replace('/\s+/', '', $content) ?? '', true);
                if (! is_string($decoded) || $decoded === '') {
                    return null;
                }
                $data = json_decode($decoded, true);

                return $this->validComposer($data);
            }

            // Some gateways may return raw JSON body
            return $this->validComposer($response->json());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchComposerViaRaw(string $owner, string $repo, string $branch): ?array
    {
        try {
            $url = "https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/composer.json";
            $response = $this->http()->accept('application/json')->get($url);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (! is_array($data)) {
                $data = json_decode($response->body(), true);
            }

            return $this->validComposer($data);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function validComposer(mixed $data): ?array
    {
        if (! is_array($data) || ! isset($data['name']) || ! is_string($data['name']) || $data['name'] === '') {
            return null;
        }

        return $data;
    }

    private function http(): PendingRequest
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

        return $request;
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
            'repo' => preg_replace('/\.git$/i', '', $m[2]) ?? $m[2],
        ];
    }
}
