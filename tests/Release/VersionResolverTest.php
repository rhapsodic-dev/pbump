<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Support/ReleaseTestCase.php';

use Rhapsodic\Pbump\Release\GitClient;
use Rhapsodic\Pbump\Release\VersionResolver;

final class VersionResolverTest extends ReleaseTestCase
{
    public function testResolveVersionContextPrefersComposerVersionInAutoMode(): void
    {
        $resolver = new VersionResolver(new GitClient());
        $repo = $this->createRepoFixture([], 'v0.9.0', false, [
            'version' => '1.2.3',
        ]);

        $context = $resolver->resolveVersionContext([
            'name' => 'demo/package',
            'version' => '1.2.3',
        ], $repo, 'auto');

        self::assertSame('composer', $context->source);
        self::assertSame('1.2.3', $context->currentVersion);
    }

    public function testResolveVersionContextFallsBackToTagInAutoMode(): void
    {
        $resolver = new VersionResolver(new GitClient());
        $repo = $this->createRepoFixture([], 'v1.2.3', false);

        $context = $resolver->resolveVersionContext([
            'name' => 'demo/package',
        ], $repo, 'auto');

        self::assertSame('tag', $context->source);
        self::assertSame('1.2.3', $context->currentVersion);
    }

    public function testResolveTagVersionStartsFromZeroWithoutTags(): void
    {
        $resolver = new VersionResolver(new GitClient());
        $repo = $this->createRepoFixture([], null, false);

        $context = $resolver->resolveVersionContext([
            'name' => 'demo/package',
        ], $repo, 'tag');

        self::assertSame('tag', $context->source);
        self::assertSame('0.0.0', $context->currentVersion);
    }

    public function testResolveReleaseVersionUsesFeatConventionalCommits(): void
    {
        $resolver = new VersionResolver(new GitClient());
        $repo = $this->createRepoFixture(['feat: add dashboard'], 'v1.2.3', false);

        self::assertSame('1.3.0', $resolver->resolveReleaseVersion('next', '1.2.3', $repo));
    }

    public function testResolveReleaseVersionUsesBreakingConventionalCommits(): void
    {
        $resolver = new VersionResolver(new GitClient());
        $repo = $this->createRepoFixture(['feat!: break api'], 'v1.2.3', false);

        self::assertSame('2.0.0', $resolver->resolveReleaseVersion('conventional', '1.2.3', $repo));
    }

    public function testValidateReleaseTypeSupportsAliasAndRejectsUnknownType(): void
    {
        $resolver = new VersionResolver(new GitClient());

        self::assertSame('conventional', $resolver->validateReleaseType('conventional'));
        self::assertNull($resolver->validateReleaseType(''));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid --type: broken.');

        $resolver->validateReleaseType('broken');
    }
}
