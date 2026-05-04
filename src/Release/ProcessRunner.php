<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;

use Rhapsodic\Pbump\Release\Dto\ProcessResult;

final class ProcessRunner
{
    /**
     * @param list<string> $command
     * @param array<string, string>|null $env
     *
     */
    public function run(array $command, string $cwd, ?array $env = null): ProcessResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $options = [];
        if (PHP_OS_FAMILY === 'Windows') {
            $options['bypass_shell'] = true;
        }

        $process = proc_open($command, $descriptors, $pipes, $cwd, $env, $options);
        if (!is_resource($process)) {
            return new ProcessResult(1, '', 'Failed to start process');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ProcessResult($exitCode, $stdout, $stderr);
    }
}
