<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;

use RuntimeException;
final class GitClient
{
    private readonly ProcessRunner $processRunner;

    public function __construct(?ProcessRunner $processRunner = null)
    {
        $this->processRunner = $processRunner ?? new ProcessRunner();
    }

    public function readConventionalLog(string $cwd): string
    {
        $lastTag = $this->getLastTag($cwd);
        $range = $lastTag ? "{$lastTag}..HEAD" : 'HEAD';

        $result = $this->processRunner->run(['git', 'log', '--pretty=%s%n%b', $range], $cwd);
        if ($result->exitCode !== 0) {
            throw new RuntimeException('git log error: ' . trim($result->stderr !== '' ? $result->stderr : $result->stdout));
        }

        return $result->stdout;
    }

    /**
     * @return list<string>
     */
    public function readCommitSubjectsSinceLastTag(string $cwd): array
    {
        $lastTag = $this->getLastTag($cwd);
        $range = $lastTag ? "{$lastTag}..HEAD" : 'HEAD';

        $result = $this->processRunner->run(['git', 'log', '--reverse', '--pretty=%s', $range], $cwd);
        if ($result->exitCode !== 0) {
            throw new RuntimeException('git log error: ' . trim($result->stderr !== '' ? $result->stderr : $result->stdout));
        }

        $lines = preg_split('/\r?\n/', trim($result->stdout)) ?: [];

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    public function getLastTag(string $cwd): ?string
    {
        $result = $this->processRunner->run(['git', 'describe', '--tags', '--abbrev=0'], $cwd);
        if ($result->exitCode !== 0) {
            return null;
        }

        $tag = trim($result->stdout);

        return $tag !== '' ? $tag : null;
    }

    public function getCurrentBranch(string $cwd): string
    {
        $result = $this->processRunner->run(['git', 'branch', '--show-current'], $cwd);
        if ($result->exitCode !== 0) {
            throw new RuntimeException('git branch error: ' . trim($result->stderr !== '' ? $result->stderr : $result->stdout));
        }

        $branchName = trim($result->stdout);
        if ($branchName === '' || $branchName === 'HEAD') {
            throw new RuntimeException('Push requires an active branch. Detached HEAD is not supported.');
        }

        return $branchName;
    }

    public function tagExists(string $tag, string $cwd): bool
    {
        $result = $this->processRunner->run(['git', 'tag', '--list', $tag], $cwd);
        if ($result->exitCode !== 0) {
            throw new RuntimeException('git tag error: ' . trim($result->stderr !== '' ? $result->stderr : $result->stdout));
        }

        return trim($result->stdout) === $tag;
    }

    public function ensureRepository(string $cwd): void
    {
        $result = $this->processRunner->run(['git', 'rev-parse', '--is-inside-work-tree'], $cwd);
        if ($result->exitCode !== 0 || trim($result->stdout) !== 'true') {
            throw new RuntimeException('Current directory is not a git repository. ' . trim($result->stderr));
        }
    }

    public function isWorkingTreeClean(string $cwd): bool
    {
        $result = $this->processRunner->run(['git', 'status', '--porcelain'], $cwd);
        if ($result->exitCode !== 0) {
            throw new RuntimeException('git status error: ' . trim($result->stderr !== '' ? $result->stderr : $result->stdout));
        }

        $lines = preg_split('/\r?\n/', trim($result->stdout)) ?: [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if ($this->extractStatusPath($line) === ReleaseMetadata::CONFIG_FILE) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param list<string> $args
     */
    public function runGit(array $args, string $cwd): void
    {
        array_unshift($args, 'git');
        $result = $this->processRunner->run($args, $cwd);

        if ($result->exitCode !== 0) {
            throw new RuntimeException(trim($result->stderr !== '' ? $result->stderr : $result->stdout));
        }
    }

    private function extractStatusPath(string $statusLine): string
    {
        $path = trim(substr($statusLine, 3));

        if (str_contains($path, ' -> ')) {
            // Renames are reported as "old -> new"; clean-tree checks care about the final path.
            $parts = explode(' -> ', $path);

            return (string) end($parts);
        }

        return $path;
    }
}
