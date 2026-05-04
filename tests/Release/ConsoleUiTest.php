<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Rhapsodic\Pbump\Release\ConsoleUi;
use Rhapsodic\Pbump\Release\Dto\ReleasePlan;

final class ConsoleUiTest extends TestCase
{
    public function testBuildChoicePromptReturnsPlainTextForUndecoratedOutput(): void
    {
        $ui = new ConsoleUi();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

        self::assertSame('Input Select release type', $ui->buildChoicePrompt($output));
    }

    public function testBuildConfirmPromptIncludesTagPreview(): void
    {
        $ui = new ConsoleUi();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

        $prompt = $ui->buildConfirmPrompt($output, 'patch', new ReleasePlan(
            version: '1.2.4',
            commitMessage: 'chore: release v1.2.4',
            updatesComposer: true,
            tagName: 'v1.2.4',
            pushEnabled: false,
        ));

        self::assertSame('Confirm Release v1.2.4 as patch, tag v1.2.4? [Y/n]', $prompt);
    }

    public function testRenderReleaseSummaryShowsPlannedActions(): void
    {
        $ui = new ConsoleUi();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

        $ui->renderReleaseSummary($output, '1.2.3', 'next', new ReleasePlan(
            version: '1.3.0',
            commitMessage: 'chore: release v1.3.0',
            updatesComposer: true,
            tagName: 'v1.3.0',
            pushEnabled: true,
        ), true);

        $rendered = $output->fetch();

        self::assertStringContainsString('Release preview', $rendered);
        self::assertStringContainsString('type     next (conventional commits)', $rendered);
        self::assertStringContainsString('update composer.json version: 1.2.3 -> 1.3.0', $rendered);
        self::assertStringContainsString('push     git push, git push --tags', $rendered);
        self::assertStringContainsString('git push', $rendered);
        self::assertStringContainsString('git push --tags', $rendered);
    }

    public function testRenderCommitsSincePreviousReleaseShowsSubjects(): void
    {
        $ui = new ConsoleUi();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

        $ui->renderCommitsSincePreviousRelease($output, [
            'fix: first change',
            'feat: second change',
        ]);

        $rendered = $output->fetch();

        self::assertStringContainsString('Commits since previous release', $rendered);
        self::assertStringContainsString('- fix: first change', $rendered);
        self::assertStringContainsString('- feat: second change', $rendered);
    }

    public function testRenderHelpIncludesKeySections(): void
    {
        $ui = new ConsoleUi();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

        $ui->renderHelp($output);
        $rendered = $output->fetch();

        self::assertStringContainsString('Usage:', $rendered);
        self::assertStringContainsString('Available --type values:', $rendered);
        self::assertStringContainsString('Version sources:', $rendered);
        self::assertStringContainsString('Configuration:', $rendered);
    }
}
