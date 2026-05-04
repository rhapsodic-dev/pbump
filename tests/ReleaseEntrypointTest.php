<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/ReleaseTestCase.php';

use Rhapsodic\Pbump\Release\Dto\ReleaseConfig;

final class ReleaseEntrypointTest extends ReleaseTestCase
{
    public function testScriptEntrypointSupportsHelpAlias(): void
    {
        $result = $this->runReleaseEntrypoint(['help'], $this->toolRoot);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stderr));
        self::assertStringContainsString('Usage:', $result->stdout);
        self::assertStringContainsString('Available --type values:', $result->stdout);
    }

    public function testScriptEntrypointResolvesProjectRootFromWorkingDirectory(): void
    {
        $repo = $this->createRepoFixture([], 'v3.4.5', false);

        $result = $this->runReleaseEntrypoint(['-v'], $repo);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stderr));
        self::assertSame('3.4.5', trim($result->stdout));
    }

    public function testPackageBinaryDelegatesToReleaseScript(): void
    {
        $repo = $this->createRepoFixture([], 'v5.6.7', false);

        $result = $this->runPackageBinary($repo, ['-v']);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stderr));
        self::assertSame('5.6.7', trim($result->stdout));
    }

    public function testInstalledBinaryUsesConsumerProjectRootAndAutoload(): void
    {
        $consumer = $this->createRepoFixture([], 'v9.8.7', false, [
            'name' => 'consumer/project',
        ]);
        $runner = $this->createTempDirectory('release-runner-');

        $this->installPackageAsDependency($consumer);

        $result = $this->runInstalledRelease($consumer, $runner, ['-v']);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stderr));
        self::assertSame('9.8.7', trim($result->stdout));
    }

    public function testScriptEntrypointAppliesReleaseConfigFileDefaults(): void
    {
        $repo = $this->createRepoFixture([
            'feat: add configurable release',
        ], 'v1.2.3');

        $this->writeReleaseConfig($repo, new ReleaseConfig(
            type: 'minor',
            tag: false,
            push: false,
            yes: true,
        ));

        $result = $this->runReleaseEntrypoint([], $repo);

        self::assertSame(0, $result->exitCode);
        self::assertSame('', trim($result->stderr));
        self::assertStringContainsString('target   v1.3.0', $result->stdout);
        self::assertStringContainsString('tag      disabled', $result->stdout);
        self::assertStringContainsString('push     disabled', $result->stdout);
        self::assertFalse($this->gitTagExists($repo, 'v1.3.0'));
        self::assertFalse($this->remoteTagExists($repo, 'v1.3.0'));
        self::assertSame('feat: add configurable release', $this->remoteHeadSubject($repo));
    }
}
