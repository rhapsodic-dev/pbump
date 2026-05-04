<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Rhapsodic\Pbump\Release\TerminalInput;

final class TerminalInputTest extends TestCase
{
    public function testConfigureInteractiveInputCanBeDisabledByEnvironment(): void
    {
        $terminal = new TerminalInput();
        $input = new ArgvInput(['pbump']);
        $original = getenv('RELEASE_DISABLE_INTERACTION');

        try {
            putenv('RELEASE_DISABLE_INTERACTION=1');

            self::assertFalse($terminal->configureInteractiveInput($input));
        } finally {
            if ($original === false) {
                putenv('RELEASE_DISABLE_INTERACTION');
            } else {
                putenv('RELEASE_DISABLE_INTERACTION=' . $original);
            }
        }
    }

    public function testGetInputStreamReturnsConfiguredStream(): void
    {
        $terminal = new TerminalInput();
        $input = new ArgvInput(['pbump']);
        $stream = $this->openTempStream();

        $input->setStream($stream);

        self::assertSame($stream, $terminal->getInputStream($input));

        fclose($stream);
    }

    public function testCanUseArrowMenuReturnsFalseForUndecoratedOutput(): void
    {
        $terminal = new TerminalInput();
        $input = new ArgvInput(['pbump']);
        $stream = $this->openTempStream();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

        $input->setStream($stream);
        $input->setInteractive(true);

        self::assertFalse($terminal->canUseArrowMenu($input, $output));

        fclose($stream);
    }

    /**
     * @return resource
     */
    private function openTempStream()
    {
        $stream = fopen('php://temp', 'r+');
        if (!is_resource($stream)) {
            self::fail('Failed to open temporary stream.');
        }

        return $stream;
    }
}
