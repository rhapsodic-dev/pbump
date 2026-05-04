<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class TerminalInput
{
    private readonly ProcessRunner $processRunner;
    private ?bool $supportsWindowsRawMenuInputCache = null;

    public function __construct(?ProcessRunner $processRunner = null)
    {
        $this->processRunner = $processRunner ?? new ProcessRunner();
    }

    public function configureInteractiveInput(StreamableInputInterface $input): bool
    {
        if (getenv('RELEASE_DISABLE_INTERACTION') === '1') {
            return false;
        }

        if ($this->isStreamInteractive(STDIN)) {
            return true;
        }

        // Some environments expose a real terminal even when STDIN itself is not interactive.
        $tty = $this->openTerminalInput();
        if (is_resource($tty)) {
            $input->setStream($tty);

            return true;
        }

        return false;
    }

    public function canUseArrowMenu(InputInterface $input, OutputInterface $output): bool
    {
        if (DIRECTORY_SEPARATOR === '\\' && !$this->supportsWindowsRawMenuInput()) {
            return false;
        }

        return $input->isInteractive()
            && $output->isDecorated()
            && is_resource($this->getInputStream($input));
    }

    /**
     * @return resource|null
     */
    public function getInputStream(InputInterface $input)
    {
        if ($input instanceof StreamableInputInterface && is_resource($input->getStream())) {
            return $input->getStream();
        }

        return defined('STDIN') && is_resource(STDIN) ? STDIN : null;
    }

    public function enterRawInputMode(): ?string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return null;
        }

        // Arrow-key handling needs raw mode on POSIX; the previous stty state
        // is returned so the caller can restore it in a finally block.
        $state = shell_exec('stty -g');
        if (!is_string($state) || trim($state) === '') {
            return null;
        }

        shell_exec('stty -icanon -echo');

        return trim($state);
    }

    public function leaveRawInputMode(?string $sttyMode): void
    {
        if ($sttyMode !== null && DIRECTORY_SEPARATOR !== '\\') {
            shell_exec('stty ' . $sttyMode);
        }
    }

    /**
     * @param resource $stream
     */
    public function readMenuKey(mixed $stream): ?string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return $this->readWindowsMenuKey();
        }

        $char = fread($stream, 1);
        if ($char === false || $char === '') {
            return null;
        }

        // POSIX terminals send arrow keys as escape sequences rather than single characters.
        if ($char === "\r" || $char === "\n") {
            return 'enter';
        }

        if ($char === "\033") {
            $sequence = $char . (fread($stream, 2) ?: '');

            return match ($sequence) {
                "\033[A" => 'up',
                "\033[B" => 'down',
                default => null,
            };
        }

        if ($char === "\000" || $char === "\xe0") {
            $code = fread($stream, 1);

            return match ($code) {
                'H' => 'up',
                'P' => 'down',
                default => null,
            };
        }

        return $char;
    }

    private function readWindowsMenuKey(): ?string
    {
        $command = [
            'powershell',
            '-NoProfile',
            '-Command',
            '$key = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown"); [Console]::Out.WriteLine($key.VirtualKeyCode)',
        ];

        // PowerShell gives us virtual key codes without needing a separate binary helper.
        $result = $this->processRunner->run($command, getcwd() ?: '.');
        if ($result->exitCode !== 0) {
            return null;
        }

        $virtualKeyCode = (int) trim($result->stdout);

        return match ($virtualKeyCode) {
            13 => 'enter',
            38 => 'up',
            40 => 'down',
            49, 97 => '1',
            50, 98 => '2',
            51, 99 => '3',
            52, 100 => '4',
            53, 101 => '5',
            default => null,
        };
    }

    /**
     * @return resource|null
     */
    private function openTerminalInput()
    {
        $paths = PHP_OS_FAMILY === 'Windows'
            ? ['CONIN$', 'CON']
            : ['/dev/tty'];

        foreach ($paths as $path) {
            $handle = @fopen($path, 'r');
            if (is_resource($handle)) {
                return $handle;
            }
        }

        return null;
    }

    private function supportsWindowsRawMenuInput(): bool
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return false;
        }

        if ($this->supportsWindowsRawMenuInputCache !== null) {
            return $this->supportsWindowsRawMenuInputCache;
        }

        $result = $this->processRunner->run(
            ['powershell', '-NoProfile', '-Command', '$PSVersionTable.PSVersion.ToString()'],
            getcwd() ?: '.'
        );

        return $this->supportsWindowsRawMenuInputCache = $result->exitCode === 0;
    }

    private function isStreamInteractive(mixed $stream): bool
    {
        if (!is_resource($stream)) {
            return false;
        }

        if (function_exists('stream_isatty')) {
            return @stream_isatty($stream);
        }

        if (function_exists('posix_isatty')) {
            return @posix_isatty($stream);
        }

        return false;
    }
}
