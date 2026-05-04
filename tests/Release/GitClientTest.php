<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Support/ReleaseTestCase.php';

use Rhapsodic\Pbump\Release\GitClient;

final class GitClientTest extends ReleaseTestCase
{
    public function testEnsureRepositoryRejectsNonGitDirectory(): void
    {
        $client = new GitClient();
        $dir = $this->createTempDirectory('git-client-');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Current directory is not a git repository.');

        $client->ensureRepository($dir);
    }

    public function testIsWorkingTreeCleanIgnoresReleaseConfigFile(): void
    {
        $client = new GitClient();
        $repo = $this->createRepoFixture([], null, false);
        file_put_contents($repo . DIRECTORY_SEPARATOR . '.pbump.config.json', "{\"type\":\"patch\"}\n");

        self::assertTrue($client->isWorkingTreeClean($repo));
    }

    public function testIsWorkingTreeCleanReturnsFalseForOtherFiles(): void
    {
        $client = new GitClient();
        $repo = $this->createRepoFixture([], null, false);
        file_put_contents($repo . DIRECTORY_SEPARATOR . 'dirty.txt', "dirty\n");

        self::assertFalse($client->isWorkingTreeClean($repo));
    }

    public function testTagExistsDetectsExistingTag(): void
    {
        $client = new GitClient();
        $repo = $this->createRepoFixture([], 'v1.2.3', false);

        self::assertTrue($client->tagExists('v1.2.3', $repo));
        self::assertFalse($client->tagExists('v1.2.4', $repo));
    }

    public function testReadConventionalLogUsesCommitsAfterLastTag(): void
    {
        $client = new GitClient();
        $repo = $this->createRepoFixture(['fix: patch only after tag'], 'v1.2.3', false);

        $log = $client->readConventionalLog($repo);

        self::assertStringContainsString('fix: patch only after tag', $log);
        self::assertStringNotContainsString('chore: initial state', $log);
    }

    public function testReadCommitSubjectsSinceLastTagUsesChronologicalOrder(): void
    {
        $client = new GitClient();
        $repo = $this->createRepoFixture([
            'fix: first change after tag',
            'feat: second change after tag',
        ], 'v1.2.3', false);

        self::assertSame([
            'fix: first change after tag',
            'feat: second change after tag',
        ], $client->readCommitSubjectsSinceLastTag($repo));
    }
}
