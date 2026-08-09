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
        $timeout = (int) config('hub.updates.timeout', 300);
        $cwd = base_path();

        $this->ensureComposerHome();

        $command = array_merge($this->composerCommandPrefix(), $arguments);
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

    /**
     * Run `php artisan …` with the same CLI/temp environment as Composer.
     *
     * @param  list<string>  $arguments  Args after `artisan` (e.g. ['mca:firewall:install'])
     * @return array{ok: bool, output: string, exit_code: int}
     */
    public function runArtisan(array $arguments): array
    {
        $timeout = (int) config('hub.updates.timeout', 300);
        $cwd = base_path();
        $this->ensureComposerHome();

        $php = $this->resolvePhpCli();
        if ($php === null) {
            return [
                'ok' => false,
                'output' => mca_hub('lifecycle.php_cli_missing'),
                'exit_code' => 1,
            ];
        }

        $artisan = base_path('artisan');
        if (! is_file($artisan)) {
            return [
                'ok' => false,
                'output' => 'artisan not found',
                'exit_code' => 1,
            ];
        }

        $tmp = $this->tempDirectory();
        $command = array_merge(
            [$php, '-d', 'sys_temp_dir='.$tmp, $artisan],
            $arguments
        );
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

    /**
     * Run Composer through PHP CLI with an explicit writable sys_temp_dir.
     * Laragon/nginx web SAPI often exposes PHP_BINARY as nginx.exe — never use that.
     *
     * @return list<string>
     */
    private function composerCommandPrefix(): array
    {
        $bin = (string) config('hub.updates.composer_bin', 'composer');
        $tmp = $this->tempDirectory();
        $php = $this->resolvePhpCli();

        $phar = $this->resolveComposerPhar($bin);
        if ($phar !== null && $php !== null) {
            return [$php, '-d', 'sys_temp_dir='.$tmp, $phar];
        }

        // Fallback: shell composer + env TMP/TEMP (set in processEnvironment).
        return [$bin];
    }

    /**
     * Absolute path to a PHP CLI binary suitable for running Composer.
     */
    private function resolvePhpCli(): ?string
    {
        $configured = config('hub.updates.php_bin');
        if (is_string($configured) && $configured !== '' && $this->isUsablePhpCli($configured)) {
            return $configured;
        }

        if (PHP_SAPI === 'cli' && PHP_BINARY !== '' && $this->isUsablePhpCli(PHP_BINARY)) {
            return PHP_BINARY;
        }

        // Prefer sibling php.exe next to php-cgi.exe (common under Laragon FastCGI).
        if (PHP_BINARY !== '') {
            $dir = dirname(PHP_BINARY);
            foreach (['php.exe', 'php'] as $name) {
                $candidate = $dir.DIRECTORY_SEPARATOR.$name;
                if ($this->isUsablePhpCli($candidate)) {
                    return $candidate;
                }
            }
        }

        foreach ($this->laragonPhpCliCandidates() as $candidate) {
            if ($this->isUsablePhpCli($candidate)) {
                return $candidate;
            }
        }

        foreach (['php', 'php.exe'] as $name) {
            if ($this->isUsablePhpCli($name)) {
                return $name;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function laragonPhpCliCandidates(): array
    {
        $roots = ['C:\\laragon\\bin\\php'];
        $out = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $dirs = glob($root.DIRECTORY_SEPARATOR.'php-*', GLOB_ONLYDIR) ?: [];
            rsort($dirs);
            foreach ($dirs as $dir) {
                $out[] = $dir.DIRECTORY_SEPARATOR.'php.exe';
            }
        }

        return $out;
    }

    private function isUsablePhpCli(string $binary): bool
    {
        $base = strtolower(basename(str_replace('\\', '/', $binary)));
        $blocked = [
            'nginx.exe', 'nginx',
            'httpd.exe', 'httpd', 'apache.exe', 'apache2',
            'php-cgi.exe', 'php-cgi',
            'php-fpm.exe', 'php-fpm',
        ];
        if (in_array($base, $blocked, true)) {
            return false;
        }

        if ($binary === 'php' || $binary === 'php.exe') {
            return true;
        }

        return is_file($binary);
    }

    private function resolveComposerPhar(string $bin): ?string
    {
        $candidates = [];

        if (str_ends_with(strtolower($bin), '.phar') && is_file($bin)) {
            $candidates[] = $bin;
        }

        $candidates[] = 'C:\\laragon\\bin\\composer\\composer.phar';
        $candidates[] = 'C:\\ProgramData\\ComposerSetup\\bin\\composer.phar';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
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

        if (
            str_contains($lower, 'sys_temp_dir')
            || str_contains($lower, 'php temp directory')
            || (str_contains($lower, 'temp directory') && str_contains($lower, 'not writable'))
        ) {
            return mca_hub('lifecycle.php_temp_unwritable');
        }

        if (
            str_contains($lower, 'only one worker, do not detach')
            || str_contains($lower, 'usage: nginx')
            || (str_contains($lower, 'nginx version') && str_contains($lower, '-x'))
        ) {
            return mca_hub('lifecycle.php_cli_missing');
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: [])));
        if ($lines === []) {
            return mca_hub('lifecycle.composer_failed');
        }

        // Prefer the most informative line (avoid tiny wrap fragments / CLI flag help).
        $best = '';
        foreach (array_reverse($lines) as $line) {
            if ($line === '' || str_starts_with($line, 'require [') || str_starts_with($line, 'In ')) {
                continue;
            }
            if (preg_match('/^-\w\b/', $line) === 1) {
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

    public function tempDirectory(): string
    {
        return storage_path('app/mca-hub-tmp');
    }

    private function ensureComposerHome(): void
    {
        $home = $this->composerHome();
        if (! is_dir($home)) {
            File::makeDirectory($home, 0755, true);
        }

        $tmp = $this->tempDirectory();
        if (! is_dir($tmp)) {
            File::makeDirectory($tmp, 0755, true);
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
        $tmp = $this->tempDirectory();
        $base['COMPOSER_HOME'] = $home;
        $base['PATH'] = $this->pathWithGit($base['PATH'] ?? (getenv('PATH') ?: ''));
        $base['GIT_CONFIG_GLOBAL'] = $home.DIRECTORY_SEPARATOR.'gitconfig';
        $base['GIT_CONFIG_NOSYSTEM'] = '1';

        // Apache/Laragon PHP often resolves sys_get_temp_dir() to C:\Windows (not writable).
        // Composer CLI inherits these and uses a project-writable temp instead.
        $base['TMP'] = $tmp;
        $base['TEMP'] = $tmp;
        $base['TMPDIR'] = $tmp;

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
