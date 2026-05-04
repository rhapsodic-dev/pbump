<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/ReleaseTestCase.php';

final class ReleaseCliTest extends ReleaseTestCase
{
    public function testRunUsesExplicitProjectRootForVersionLookup(): void
    {
        $repo = $this->createRepoFixture([], 'v4.5.6', false);
        $cwd = $this->createTempDirectory('release-cli-cwd-');

        $result = $this->runReleaseCli($repo, ['--version'], $cwd);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stderr));
        self::assertSame('4.5.6', trim($result->stdout));
    }

    public function testRunReturnsErrorWhenProjectRootIsNotGitRepository(): void
    {
        $projectRoot = $this->createBareFixture(new ReleaseFixtureOptions(
            composer: [
                'name' => 'demo/package',
            ],
        ));

        $result = $this->runReleaseCli($projectRoot, ['--dry-run', '--type=patch']);

        self::assertSame(1, $result->exitCode);
        self::assertSame('', trim($result->stdout));
        self::assertStringContainsString('Current directory is not a git repository.', $result->stderr);
    }

    public function testRunShowsCommitsBeforeCurrentVersion(): void
    {
        $repo = $this->createRepoFixture([
            'fix: visible release note',
            'feat: visible feature',
        ], 'v1.2.3', false);

        $result = $this->runReleaseCli($repo, ['--dry-run', '--type=next']);

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString('Commits since previous release', $result->stdout);
        self::assertStringContainsString('- fix: visible release note', $result->stdout);
        self::assertStringContainsString('- feat: visible feature', $result->stdout);
        self::assertLessThan(
            strpos($result->stdout, 'Current version v1.2.3'),
            strpos($result->stdout, 'Commits since previous release')
        );
    }

    public function testRunCanPerformQuietReleaseDirectly(): void
    {
        $repo = $this->createRepoFixture([
            'fix: direct cli release',
        ], 'v1.2.3');

        $result = $this->runReleaseCli($repo, ['--type=patch', '--yes', '--quiet']);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stdout));
        self::assertSame('', trim($result->stderr));
        self::assertTrue($this->gitTagExists($repo, 'v1.2.4'));
        self::assertTrue($this->remoteTagExists($repo, 'v1.2.4'));
        self::assertSame('chore: release v1.2.4', $this->remoteHeadSubject($repo));
    }

    public function testRunPushesToTrackedRemoteWithoutAssumingOrigin(): void
    {
        $repo = $this->createRepoFixture([
            'fix: release through custom upstream',
        ], 'v1.2.3', false);

        $this->attachBareRemoteAs($repo, 'company');

        $result = $this->runReleaseCli($repo, ['--type=patch', '--yes', '--quiet']);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stdout));
        self::assertSame('', trim($result->stderr));
        self::assertTrue($this->remoteTagExistsOn($repo, 'company', 'v1.2.4'));
        self::assertSame('chore: release v1.2.4', $this->remoteHeadSubjectOn($repo, 'company'));
    }

    public function testRunCanReleaseComposerVersionWithDirtyFilesWhenAllowed(): void
    {
        $repo = $this->createRepoFixture([
            'fix: dirty release',
        ], null, false, [
            'version' => '1.2.3',
        ]);

        file_put_contents($repo . DIRECTORY_SEPARATOR . 'staged.txt', "staged\n");
        $this->git($repo, ['add', 'staged.txt']);
        file_put_contents($repo . DIRECTORY_SEPARATOR . 'unstaged.txt', "unstaged\n");

        $result = $this->runReleaseCli($repo, [
            '--type=patch',
            '--yes',
            '--quiet',
            '--no-push',
            '--allow-dirty',
        ]);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stderr));
        self::assertSame('1.2.4', $this->readComposerVersion($repo));
        self::assertSame(['composer.json'], $this->headChangedFiles($repo));
        self::assertTrue($this->gitTagExists($repo, 'v1.2.4'));

        $status = $this->gitOutput($repo, ['status', '--porcelain']);
        self::assertStringContainsString('A  staged.txt', $status);
        self::assertStringContainsString('?? unstaged.txt', $status);
    }

    public function testRunRejectsDirtyFilesByDefault(): void
    {
        $repo = $this->createRepoFixture([], null, false, [
            'version' => '1.2.3',
        ]);
        file_put_contents($repo . DIRECTORY_SEPARATOR . 'dirty.txt', "dirty\n");

        $result = $this->runReleaseCli($repo, ['--type=patch', '--yes', '--quiet', '--no-push']);

        self::assertSame(1, $result->exitCode);
        self::assertStringContainsString('Working tree is not clean.', $result->stderr);
        self::assertSame('1.2.3', $this->readComposerVersion($repo));
        self::assertFalse($this->gitTagExists($repo, 'v1.2.4'));
    }
}
