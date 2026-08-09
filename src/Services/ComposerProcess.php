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
        $process = new Process($command, $cwd, null, null, $timeout);

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
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: [])));
        if ($lines === []) {
            return 'composer failed';
        }

        $last = $lines[array_key_last($lines)] ?? 'composer failed';

        return mb_substr($last, 0, 240);
    }
}
