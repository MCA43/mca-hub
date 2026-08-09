<?php

namespace Mca\Hub\Services;

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
        if (str_contains($output, 'git@github.com') || str_contains($output, 'Failed to clone')) {
            return mca_hub('lifecycle.git_clone_failed');
        }

        if (str_contains($output, 'Could not find package')) {
            return mca_hub('lifecycle.package_not_on_github');
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: [])));
        if ($lines === []) {
            return mca_hub('lifecycle.composer_failed');
        }

        foreach (array_reverse($lines) as $line) {
            if ($line === '' || str_starts_with($line, 'require [')) {
                continue;
            }

            return mb_substr($line, 0, 240);
        }

        $last = $lines[array_key_last($lines)] ?? mca_hub('lifecycle.composer_failed');

        return mb_substr($last, 0, 240);
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

        // Force HTTPS for GitHub when Composer falls back to git clone (Windows/SSH issues).
        $base['GIT_CONFIG_COUNT'] = '2';
        $base['GIT_CONFIG_KEY_0'] = 'url.https://github.com/.insteadOf';
        $base['GIT_CONFIG_VALUE_0'] = 'git@github.com:';
        $base['GIT_CONFIG_KEY_1'] = 'url.https://github.com/.insteadOf';
        $base['GIT_CONFIG_VALUE_1'] = 'ssh://git@github.com/';

        return $base;
    }
}
