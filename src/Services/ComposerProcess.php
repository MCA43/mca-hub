<?php

namespace Mca\Hub\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class ComposerProcess
{
    /**
     * @param  list<string>  $arguments  Args after the composer binary (e.g. ['require', 'mca/foo'])
     * @return array{ok: bool, output: string, exit_code: int}
     */
    public function run(array $arguments): array
    {
        $bin = (string) config('hub.updates.composer_bin', 'composer');
        $timeout = (int) config('hub.updates.timeout', 300);
        $cwd = base_path();

        $this->ensureComposerHome();

        $command = array_merge([$bin], $arguments);
        $process = new Process($command, $cwd, $this->processEnvironment(), null, $timeout);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'output' => $e->getMessage(),
                'exit_code' => 1,
            ];
        }

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return [
            'ok' => $process->isSuccessful(),
            'output' => $output,
            'exit_code' => $process->getExitCode() ?? 1,
        ];
    }

    public function shortError(string $output): string
    {
        $normalized = preg_replace('/\s+/u', ' ', $output) ?? $output;
        $lower = mb_strtolower($normalized);

        if (
            str_contains($lower, 'git was not found')
            || str_contains($lower, 'available in your path to run correctly')
            || (str_contains($lower, 'to run correctly') && str_contains($lower, 'git'))
        ) {
            return mca_hub('lifecycle.git_not_in_path');
        }

        if (
            str_contains($output, 'git@github.com')
            || str_contains($output, 'Failed to clone')
            || str_contains($output, 'Host key verification failed')
            || str_contains($output, 'Could not read from remote repository')
        ) {
            return mca_hub('lifecycle.git_clone_failed');
        }

        if (str_contains($output, 'Could not find package') || str_contains($output, 'no matching package found')) {
            return mca_hub('lifecycle.package_not_on_github');
        }

        if (str_contains($lower, 'minimum-stability') || str_contains($lower, 'stability flags')) {
            return mca_hub('lifecycle.stability_blocked');
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: [])));
        if ($lines === []) {
            return mca_hub('lifecycle.composer_failed');
        }

        // Prefer the most informative line (avoid tiny wrap fragments like "to run correctly.").
        $best = '';
        foreach (array_reverse($lines) as $line) {
            if ($line === '' || str_starts_with($line, 'require [') || str_starts_with($line, 'In ')) {
                continue;
            }
            if (mb_strlen($line) < 24 && $best !== '') {
                continue;
            }
            if (mb_strlen($line) >= mb_strlen($best)) {
                $best = $line;
            }
            if (mb_strlen($best) >= 60) {
                break;
            }
        }

        if ($best === '') {
            $best = $lines[array_key_last($lines)] ?? mca_hub('lifecycle.composer_failed');
        }

        return mb_substr($best, 0, 240);
    }

    public function composerHome(): string
    {
        return storage_path('app/mca-hub-composer-home');
    }

    private function ensureComposerHome(): void
    {
        $home = $this->composerHome();
        if (! is_dir($home)) {
            File::makeDirectory($home, 0755, true);
        }

        // Composer warns / may skip global config when composer.json is missing in COMPOSER_HOME.
        $globalComposer = $home.DIRECTORY_SEPARATOR.'composer.json';
        if (! is_file($globalComposer)) {
            File::put($globalComposer, "{}\n");
        }

        $configPath = $home.DIRECTORY_SEPARATOR.'config.json';
        $config = [
            'config' => [
                'github-protocols' => ['https'],
                'use-github-api' => true,
                'secure-http' => true,
                'gitlab-protocol' => 'https',
            ],
        ];
        File::put($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $gitConfig = $home.DIRECTORY_SEPARATOR.'gitconfig';
        File::put($gitConfig, implode("\n", [
            '[url "https://github.com/"]',
            "\tinsteadOf = git@github.com:",
            "\tinsteadOf = ssh://git@github.com/",
            "\tinsteadOf = git://github.com/",
            '',
        ]));

        $token = config('hub.github.token');
        $authPath = $home.DIRECTORY_SEPARATOR.'auth.json';
        if (is_string($token) && $token !== '') {
            File::put($authPath, json_encode([
                'github-oauth' => [
                    'github.com' => $token,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        } elseif (is_file($authPath)) {
            File::delete($authPath);
        }
    }

    /** @return array<string, string> */
    private function processEnvironment(): array
    {
        $base = [];
        foreach ($_SERVER as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }
            $base[$key] = $value;
        }
        foreach ($_ENV as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $base[$key] = (string) $value;
            }
        }

        $home = $this->composerHome();
        $base['COMPOSER_HOME'] = $home;
        $base['PATH'] = $this->pathWithGit($base['PATH'] ?? (getenv('PATH') ?: ''));
        $base['GIT_CONFIG_GLOBAL'] = $home.DIRECTORY_SEPARATOR.'gitconfig';
        $base['GIT_CONFIG_NOSYSTEM'] = '1';

        // Force HTTPS for GitHub when Composer falls back to git clone (Windows/SSH issues).
        $base['GIT_CONFIG_COUNT'] = '3';
        $base['GIT_CONFIG_KEY_0'] = 'url.https://github.com/.insteadOf';
        $base['GIT_CONFIG_VALUE_0'] = 'git@github.com:';
        $base['GIT_CONFIG_KEY_1'] = 'url.https://github.com/.insteadOf';
        $base['GIT_CONFIG_VALUE_1'] = 'ssh://git@github.com/';
        $base['GIT_CONFIG_KEY_2'] = 'url.https://github.com/.insteadOf';
        $base['GIT_CONFIG_VALUE_2'] = 'git://github.com/';

        return $base;
    }

    private function pathWithGit(string $path): string
    {
        $candidates = [
            'C:\\laragon\\bin\\git\\bin',
            'C:\\laragon\\bin\\git\\cmd',
            'C:\\Program Files\\Git\\cmd',
            'C:\\Program Files\\Git\\bin',
            'C:\\Program Files (x86)\\Git\\cmd',
            '/usr/bin',
            '/usr/local/bin',
        ];

        $parts = array_values(array_filter(array_map('trim', explode(PATH_SEPARATOR, $path))));
        foreach (array_reverse($candidates) as $dir) {
            $git = $dir.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'git.exe' : 'git');
            if (is_file($git) && ! in_array($dir, $parts, true)) {
                array_unshift($parts, $dir);
            }
        }

        return implode(PATH_SEPARATOR, $parts);
    }
}
