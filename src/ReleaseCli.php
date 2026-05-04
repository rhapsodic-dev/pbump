<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump;

use RuntimeException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Rhapsodic\Pbump\Release\ConsoleUi;
use Rhapsodic\Pbump\Release\GitClient;
use Rhapsodic\Pbump\Release\OptionsResolver;
use Rhapsodic\Pbump\Release\ProcessRunner;
use Rhapsodic\Pbump\Release\ProjectFiles;
use Rhapsodic\Pbump\Release\ReleaseMetadata;
use Rhapsodic\Pbump\Release\ReleasePlanner;
use Rhapsodic\Pbump\Release\TerminalInput;
use Rhapsodic\Pbump\Release\VersionResolver;
use Rhapsodic\Pbump\Release\Dto\ReleasePlan;
use Rhapsodic\Pbump\Release\Dto\ReleaseTypeCollection;

final class ReleaseCli
{
    private const EXIT_OK = 0;
    private const EXIT_ERROR = 1;

    private readonly QuestionHelper $questionHelper;
    private readonly OptionsResolver $optionsResolver;
    private readonly ProjectFiles $projectFiles;
    private readonly GitClient $git;
    private readonly VersionResolver $versionResolver;
    private readonly ReleasePlanner $releasePlanner;
    private readonly ConsoleUi $ui;
    private readonly TerminalInput $terminalInput;

    public function __construct(
        ?QuestionHelper $questionHelper = null,
        ?OptionsResolver $optionsResolver = null,
        ?ProjectFiles $projectFiles = null,
        ?ProcessRunner $processRunner = null,
        ?GitClient $git = null,
        ?VersionResolver $versionResolver = null,
        ?ReleasePlanner $releasePlanner = null,
        ?ConsoleUi $ui = null,
        ?TerminalInput $terminalInput = null,
    ) {
        $this->questionHelper = $questionHelper ?? new QuestionHelper();
        $runner = $processRunner ?? new ProcessRunner();

        $this->optionsResolver = $optionsResolver ?? new OptionsResolver();
        $this->projectFiles = $projectFiles ?? new ProjectFiles();
        $this->git = $git ?? new GitClient($runner);
        $this->versionResolver = $versionResolver ?? new VersionResolver($this->git);
        $this->releasePlanner = $releasePlanner ?? new ReleasePlanner($this->versionResolver, $this->git);
        $this->ui = $ui ?? new ConsoleUi();
        $this->terminalInput = $terminalInput ?? new TerminalInput($runner);
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv, string $projectRoot): int
    {
        $output = new ConsoleOutput();

        try {
            $input = new ArgvInput($argv, $this->optionsResolver->buildInputDefinition());
            $input->setInteractive($this->terminalInput->configureInteractiveInput($input));

            return $this->execute($input, $output, $projectRoot);
        } catch (ExceptionInterface|RuntimeException $exception) {
            $output->getErrorOutput()->writeln('[ERROR] ' . $exception->getMessage());

            return self::EXIT_ERROR;
        }
    }

    private function execute(InputInterface $input, ConsoleOutput $output, string $projectRoot): int
    {
        $composerPath = $projectRoot . '/composer.json';
        $composer = $this->projectFiles->loadComposerConfig($composerPath);
        $config = $this->projectFiles->loadReleaseConfig($projectRoot . '/' . ReleaseMetadata::CONFIG_FILE);
        $options = $this->optionsResolver->resolveOptions($input, $config);

        if ($options->showHelp) {
            $this->ui->renderHelp($output);

            return self::EXIT_OK;
        }

        $this->git->ensureRepository($projectRoot);
        $versionContext = $this->versionResolver->resolveVersionContext($composer, $projectRoot, $options->versionSource);
        $currentVersion = $versionContext->currentVersion;

        if ($options->showVersion) {
            $output->writeln($currentVersion);

            return self::EXIT_OK;
        }

        $workingTreeClean = $this->git->isWorkingTreeClean($projectRoot);
        if (!$options->dryRun && !$options->allowDirty && !$workingTreeClean) {
            throw new RuntimeException('Working tree is not clean. Commit or stash changes before release.');
        }

        if (!$options->quiet) {
            $this->ui->renderCommitsSincePreviousRelease($output, $this->git->readCommitSubjectsSinceLastTag($projectRoot));
            $this->ui->renderVersionHeader($output, $currentVersion);
        }

        $selectedType = $this->resolveReleaseType(
            $input,
            $output,
            $currentVersion,
            $projectRoot,
            $options->forcedType,
            $options->dryRun,
            !$options->quiet
        );
        $releasePlan = $this->releasePlanner->buildReleasePlan(
            $selectedType,
            $currentVersion,
            $versionContext->source,
            $options->tag,
            $options->push,
            $projectRoot
        );

        if ($releasePlan->tagName !== null && $this->git->tagExists($releasePlan->tagName, $projectRoot)) {
            throw new RuntimeException("Tag {$releasePlan->tagName} already exists.");
        }

        if (!$options->dryRun && $options->allowDirty && !$workingTreeClean && !$releasePlan->updatesComposer) {
            throw new RuntimeException(
                '--allow-dirty can only release with dirty files when composer.json is updated. '
                . 'Use --version-source=composer or add a string "version" field to composer.json.'
            );
        }

        if (!$options->dryRun && $options->allowDirty && !$workingTreeClean && $selectedType === 'as-is') {
            throw new RuntimeException(
                '--allow-dirty cannot create an as-is release with dirty files because composer.json would not change.'
            );
        }

        if ($options->dryRun) {
            if (!$options->quiet) {
                $this->ui->renderReleaseSummary($output, $currentVersion, $selectedType, $releasePlan, true);
            }

            return self::EXIT_OK;
        }

        if (!$options->yes && !$this->confirmRelease($input, $output, $selectedType, $releasePlan)) {
            if (!$options->quiet) {
                $this->ui->renderCancellation($output);
            }

            return self::EXIT_OK;
        }

        if ($releasePlan->updatesComposer) {
            $this->projectFiles->writeComposerVersion($composerPath, $releasePlan->version);
            $this->git->runGit(['add', 'composer.json'], $projectRoot);
        }

        $commitArgs = ['commit', '--allow-empty', '-m', $releasePlan->commitMessage];
        if ($options->allowDirty && $releasePlan->updatesComposer) {
            $commitArgs = ['commit', '--allow-empty', '--only', '-m', $releasePlan->commitMessage, '--', 'composer.json'];
        }

        $this->git->runGit($commitArgs, $projectRoot);

        if ($releasePlan->tagName !== null) {
            $this->git->runGit(['tag', $releasePlan->tagName], $projectRoot);
        }

        if ($releasePlan->pushEnabled) {
            $this->git->runGit(['push'], $projectRoot);

            if ($releasePlan->tagName !== null) {
                $this->git->runGit(['push', '--tags'], $projectRoot);
            }
        }

        if (!$options->quiet) {
            $this->ui->renderReleaseSummary($output, $currentVersion, $selectedType, $releasePlan, false);
        }

        return self::EXIT_OK;
    }

    private function resolveReleaseType(
        InputInterface $input,
        ConsoleOutput $output,
        string $currentVersion,
        string $projectRoot,
        ?string $forcedType,
        bool $dryRun,
        bool $renderMenu
    ): string {
        $forcedType = $this->versionResolver->validateReleaseType($forcedType);
        if ($forcedType !== null) {
            return $forcedType;
        }

        if (!$input->isInteractive()) {
            if ($dryRun) {
                return ReleaseMetadata::DEFAULT_RELEASE_TYPE;
            }

            throw new RuntimeException(
                'No interactive input available (STDIN/TTY). Use --type or set "type" in ' . ReleaseMetadata::CONFIG_FILE . '.'
            );
        }

        if ($renderMenu && $this->terminalInput->canUseArrowMenu($input, $output)) {
            $releaseTypes = $this->versionResolver->buildReleaseTypes(
                ReleaseMetadata::MENU_RELEASE_TYPES,
                $currentVersion,
                $projectRoot
            );

            return $this->askReleaseTypeFromArrowMenu(
                $input,
                $output,
                $releaseTypes,
                ReleaseMetadata::DEFAULT_RELEASE_TYPE
            );
        }

        if ($renderMenu) {
            $releaseTypes = $this->versionResolver->buildReleaseTypes(
                ReleaseMetadata::MENU_RELEASE_TYPES,
                $currentVersion,
                $projectRoot
            );
            $this->ui->renderStaticReleaseTypeMenu($output, $releaseTypes, ReleaseMetadata::DEFAULT_RELEASE_TYPE);
        }

        $question = new ChoiceQuestion(
            "\n" . $this->ui->buildChoicePrompt($output),
            ReleaseMetadata::MENU_RELEASE_TYPES,
            ReleaseMetadata::DEFAULT_RELEASE_TYPE
        );
        $question->setErrorMessage('Invalid selection: %s');

        $answer = $this->questionHelper->ask($input, $output, $question);
        if (!is_string($answer) || !in_array($answer, ReleaseMetadata::MENU_RELEASE_TYPES, true)) {
            throw new RuntimeException('Invalid selection.');
        }

        return $answer;
    }

    private function askReleaseTypeFromArrowMenu(
        InputInterface $input,
        ConsoleOutput $output,
        ReleaseTypeCollection $releaseTypes,
        string $default
    ): string {
        $stream = $this->terminalInput->getInputStream($input);
        if (!is_resource($stream)) {
            throw new RuntimeException('Failed to open input stream for the interactive menu.');
        }

        $selectedIndex = array_search($default, ReleaseMetadata::MENU_RELEASE_TYPES, true);
        $selectedIndex = $selectedIndex === false ? 0 : $selectedIndex;
        $section = $output->section();
        $sttyMode = $this->terminalInput->enterRawInputMode();

        try {
            $this->ui->renderArrowReleaseTypeMenu($section, $releaseTypes, $selectedIndex, $default);

            while (true) {
                $key = $this->terminalInput->readMenuKey($stream);

                if ($key === 'up') {
                    $selectedIndex = ($selectedIndex + count(ReleaseMetadata::MENU_RELEASE_TYPES) - 1)
                        % count(ReleaseMetadata::MENU_RELEASE_TYPES);
                    $this->ui->renderArrowReleaseTypeMenu($section, $releaseTypes, $selectedIndex, $default);
                    continue;
                }

                if ($key === 'down') {
                    $selectedIndex = ($selectedIndex + 1) % count(ReleaseMetadata::MENU_RELEASE_TYPES);
                    $this->ui->renderArrowReleaseTypeMenu($section, $releaseTypes, $selectedIndex, $default);
                    continue;
                }

                if ($key === 'enter') {
                    $section->clear();

                    return ReleaseMetadata::MENU_RELEASE_TYPES[$selectedIndex];
                }

                if ($key !== null && ctype_digit($key)) {
                    $choiceIndex = (int) $key - 1;
                    if (isset(ReleaseMetadata::MENU_RELEASE_TYPES[$choiceIndex])) {
                        $section->clear();

                        return ReleaseMetadata::MENU_RELEASE_TYPES[$choiceIndex];
                    }
                }
            }
        } finally {
            $this->terminalInput->leaveRawInputMode($sttyMode);
        }
    }

    private function confirmRelease(
        InputInterface $input,
        ConsoleOutput $output,
        string $selectedType,
        ReleasePlan $releasePlan
    ): bool {
        $prompt = "\n" . $this->ui->buildConfirmPrompt($output, $selectedType, $releasePlan) . ': ';

        if (!$input->isInteractive()) {
            $output->write($prompt);
            throw new RuntimeException(
                'No interactive input available (STDIN/TTY). Use --yes or set "yes": true in ' . ReleaseMetadata::CONFIG_FILE . '.'
            );
        }

        $question = new ConfirmationQuestion($prompt, true, '/^(y|yes)$/i');

        return (bool) $this->questionHelper->ask($input, $output, $question);
    }
}
