<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Rhapsodic\Pbump\Release\ProcessRunner;

final class ProcessRunnerTest extends TestCase
{
    public function testRunReturnsExitCodeStdoutAndStderr(): void
    {
        $runner = new ProcessRunner();

        $result = $runner->run(
            [PHP_BINARY, '-r', 'fwrite(STDERR, "warn"); fwrite(STDOUT, "ok"); exit(3);'],
            getcwd() ?: __DIR__
        );

        self::assertSame(3, $result->exitCode);
        self::assertSame('ok', $result->stdout);
        self::assertSame('warn', $result->stderr);
    }
}
