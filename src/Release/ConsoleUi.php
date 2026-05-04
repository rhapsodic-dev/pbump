<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;

use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Rhapsodic\Pbump\Release\Dto\ReleasePlan;
use Rhapsodic\Pbump\Release\Dto\ReleaseTypeCollection;
use Rhapsodic\Pbump\Release\Dto\SummaryRow;

final class ConsoleUi
{
    public function buildChoicePrompt(OutputInterface $output): string
    {
        return $this->colorize($output, 'Input', '1;36') . ' ' . $this->dim($output, 'Select release type');
    }

    public function buildConfirmPrompt(OutputInterface $output, string $selectedType, ReleasePlan $releasePlan): string
    {
        $tagPreview = $releasePlan->tagName !== null ? ', tag ' . $releasePlan->tagName : '';

        return $this->colorize($output, 'Confirm', '1;33') . ' '
            . $this->dim($output, "Release v{$releasePlan->version} as {$selectedType}{$tagPreview}? [Y/n]");
    }

    public function renderCancellation(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln($this->colorize($output, 'Release cancelled', '1;33'));
    }

    /**
     * @param list<string> $commits
     */
    public function renderCommitsSincePreviousRelease(OutputInterface $output, array $commits): void
    {
        $output->writeln('');
        $output->writeln($this->colorize($output, 'Commits since previous release', '1'));

        if ($commits === []) {
            $output->writeln('  ' . $this->dim($output, 'none'));
            $output->writeln('');

            return;
        }

        foreach ($commits as $commit) {
            $output->writeln('  ' . $this->colorize($output, '-', '2') . ' ' . $commit);
        }

        $output->writeln('');
    }

    public function renderVersionHeader(OutputInterface $output, string $currentVersion): void
    {
        $output->writeln($this->colorize($output, 'Current version', '2') . ' ' . $this->colorize($output, "v{$currentVersion}", '1'));
        $output->writeln('');
    }

    public function renderStaticReleaseTypeMenu(
        OutputInterface $output,
        ReleaseTypeCollection $releaseTypes,
        string $default
    ): void {
        $output->writeln($this->colorize($output, '? Select release type', '1;36'));

        foreach (ReleaseMetadata::MENU_RELEASE_TYPES as $index => $type) {
            $item = $releaseTypes->get($type);
            $defaultLabel = $type === $default ? ' ' . $this->dim($output, '(default)') : '';

            $output->writeln(sprintf(
                '%d. %-8s %s  %s%s',
                $index + 1,
                $this->colorize($output, $type, $this->releaseTypeColor($type)),
                $this->colorize($output, 'v' . $item->version, '1'),
                $this->dim($output, $item->description),
                $defaultLabel
            ));
        }

        $output->writeln($this->dim($output, 'Type a number or release type name.'));
    }

    public function renderArrowReleaseTypeMenu(
        ConsoleSectionOutput $section,
        ReleaseTypeCollection $releaseTypes,
        int $selectedIndex,
        string $default
    ): void {
        $lines = [$this->colorize($section, '? Select release type', '1;36')];

        foreach (ReleaseMetadata::MENU_RELEASE_TYPES as $index => $type) {
            $item = $releaseTypes->get($type);
            $isSelected = $index === $selectedIndex;
            $defaultLabel = $type === $default ? ' ' . $this->dim($section, '(default)') : '';
            $prefix = $isSelected ? $this->colorize($section, '>', '1;32') : ' ';

            $lines[] = sprintf(
                '%s %d. %-8s %s  %s%s',
                $prefix,
                $index + 1,
                $this->colorize($section, $type, $this->releaseTypeColor($type)),
                $this->colorize($section, 'v' . $item->version, '1'),
                $this->dim($section, $item->description),
                $defaultLabel
            );
        }

        $lines[] = $this->dim($section, 'Use Up/Down and Enter, or press 1-5.');
        $section->overwrite($lines);
    }

    public function renderReleaseSummary(
        OutputInterface $output,
        string $currentVersion,
        string $selectedType,
        ReleasePlan $releasePlan,
        bool $dryRun
    ): void {
        $title = $dryRun ? 'Release preview' : 'Release complete';
        $rows = [
            new SummaryRow('current', "v{$currentVersion}"),
            new SummaryRow('target', 'v' . $releasePlan->version),
            new SummaryRow('type', $this->formatReleaseType($selectedType)),
            new SummaryRow('commit', $releasePlan->commitMessage),
            new SummaryRow('tag', $releasePlan->tagName ?? 'disabled'),
            new SummaryRow('push', $this->formatPushTarget($releasePlan)),
        ];

        $output->writeln('');
        $output->writeln($this->colorize($output, $title, $dryRun ? '1;33' : '1;32'));
        $this->renderKeyValueRows($output, $rows);

        $output->writeln('');
        $output->writeln($this->colorize($output, $dryRun ? 'Planned actions' : 'Applied actions', '1'));

        foreach ($this->buildReleaseActions($currentVersion, $releasePlan, $dryRun) as $action) {
            $output->writeln('  ' . $this->colorize($output, '-', $dryRun ? '33' : '32') . ' ' . $action);
        }
    }

    public function renderHelp(OutputInterface $output): void
    {
        $output->writeln('Usage:');
        $output->writeln('  php vendor/bin/pbump [arguments]');
        $output->writeln('');
        $output->writeln('Arguments:');
        $output->writeln('  help, --help, -h       Show this help and exit');
        $output->writeln('  -v, --version          Show the current version from the selected source');
        $output->writeln('  --version-source=<s>   Version source: auto, composer, tag');
        $output->writeln('  --dry-run              Do not make changes, only show planned actions');
        $output->writeln('  --type=<type>          Release version type');
        $output->writeln('  -t, --tag [tag]        Create a tag, you can pass a custom name');
        $output->writeln('  --no-tag               Do not create a tag');
        $output->writeln('  -p, --push             Push to remote (default: true)');
        $output->writeln('  --no-push              Do not push to the remote');
        $output->writeln('  -y, --yes              Skip confirmation');
        $output->writeln('  -q, --quiet            Hide summary and preview');
        $output->writeln('  --allow-dirty          Allow unrelated uncommitted files and commit only composer.json');
        $output->writeln('');
        $output->writeln('Available --type values:');
        $output->writeln('  major                  X+1.0.0');
        $output->writeln('  minor                  X.Y+1.0');
        $output->writeln('  patch                  X.Y.Z+1');
        $output->writeln('  next                   Auto-select by conventional commits (alias conventional)');
        $output->writeln('  conventional           Auto-select by conventional commits');
        $output->writeln('  as-is                  Keep the current version');
        $output->writeln('');
        $output->writeln('Version sources:');
        $output->writeln('  auto                   composer.json.version, or the latest git tag if it is missing');
        $output->writeln('  composer               Always use and update composer.json.version');
        $output->writeln('  tag                    Always use git tag, composer.json is not modified');
        $output->writeln('');
        $output->writeln('Configuration:');
        $output->writeln('  You can create ' . ReleaseMetadata::CONFIG_FILE . ' in the project root.');
        $output->writeln('  Supported keys: type, dryRun, tag, push, yes, quiet, versionSource, allowDirty.');
        $output->writeln('');
        $output->writeln('Example ' . ReleaseMetadata::CONFIG_FILE . ':');
        $output->writeln('  {');
        $output->writeln('    "type": "patch",');
        $output->writeln('    "versionSource": "auto",');
        $output->writeln('    "tag": true,');
        $output->writeln('    "push": true,');
        $output->writeln('    "yes": false,');
        $output->writeln('    "quiet": false,');
        $output->writeln('    "allowDirty": false');
        $output->writeln('  }');
        $output->writeln('');
        $output->writeln('Examples:');
        $output->writeln('  composer release -- --type=minor -y');
        $output->writeln('  composer release -- --version-source=tag --type=next -y');
        $output->writeln('  composer release -- -t release-1.4.0 --no-push');
        $output->writeln('  composer release:dry -- -q --type=next');
    }

    /**
     * @return list<string>
     */
    private function buildReleaseActions(string $currentVersion, ReleasePlan $releasePlan, bool $dryRun): array
    {
        $actions = [];

        if ($releasePlan->updatesComposer) {
            $actions[] = $dryRun
                ? "update composer.json version: {$currentVersion} -> {$releasePlan->version}"
                : "updated composer.json version: {$currentVersion} -> {$releasePlan->version}";

            if ($dryRun) {
                $actions[] = 'git add composer.json';
            }
        }

        $actions[] = $dryRun
            ? 'git commit --allow-empty -m "' . $releasePlan->commitMessage . '"'
            : 'created commit: ' . $releasePlan->commitMessage;

        if ($releasePlan->tagName !== null) {
            $actions[] = $dryRun
                ? 'git tag ' . $releasePlan->tagName
                : 'created tag: ' . $releasePlan->tagName;
        }

        if ($releasePlan->pushEnabled) {
            foreach ($this->buildPushCommands($releasePlan) as $command) {
                $actions[] = $dryRun
                    ? $command
                    : 'pushed with ' . $command;
            }
        }

        return $actions;
    }

    private function formatPushTarget(ReleasePlan $releasePlan): string
    {
        if (!$releasePlan->pushEnabled) {
            return 'disabled';
        }

        return implode(', ', $this->buildPushCommands($releasePlan));
    }

    /**
     * @return list<string>
     */
    private function buildPushCommands(ReleasePlan $releasePlan): array
    {
        $commands = [
            'git push',
        ];

        if ($releasePlan->tagName !== null) {
            $commands[] = 'git push --tags';
        }

        return $commands;
    }

    /**
     * @param list<SummaryRow> $rows
     */
    private function renderKeyValueRows(OutputInterface $output, array $rows): void
    {
        $width = 0;
        foreach ($rows as $row) {
            $width = max($width, strlen($row->label));
        }

        foreach ($rows as $row) {
            $output->writeln('  ' . $this->colorize($output, str_pad($row->label, $width), '2') . '  ' . $row->value);
        }
    }

    private function formatReleaseType(string $type): string
    {
        return match ($type) {
            'next' => 'next (conventional commits)',
            'conventional' => 'conventional (alias of next)',
            default => $type,
        };
    }

    private function releaseTypeColor(string $type): string
    {
        return match ($type) {
            'next' => '36',
            'patch' => '32',
            'minor' => '33',
            'major' => '31',
            'as-is' => '2',
            default => '0',
        };
    }

    private function colorize(OutputInterface $output, string $text, string $style): string
    {
        if (!$output->isDecorated()) {
            return $text;
        }

        return "\033[{$style}m{$text}\033[0m";
    }

    private function dim(OutputInterface $output, string $text): string
    {
        return $this->colorize($output, $text, '2');
    }
}
