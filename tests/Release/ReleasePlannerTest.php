<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Support/ReleaseTestCase.php';

use Rhapsodic\Pbump\Release\GitClient;
use Rhapsodic\Pbump\Release\ReleasePlanner;
use Rhapsodic\Pbump\Release\VersionResolver;

final class ReleasePlannerTest extends ReleaseTestCase
{
    public function testBuildReleasePlanIncludesCommitTagAndPush(): void
    {
        $git = new GitClient();
        $planner = new ReleasePlanner(new VersionResolver($git), $git);
        $repo = $this->createRepoFixture([], 'v1.2.3', false, [
            'version' => '1.2.3',
        ]);

        $plan = $planner->buildReleasePlan('patch', '1.2.3', 'composer', true, true, $repo);

        self::assertSame('1.2.4', $plan->version);
        self::assertSame('chore: release v1.2.4', $plan->commitMessage);
        self::assertTrue($plan->updatesComposer);
        self::assertSame('v1.2.4', $plan->tagName);
        self::assertTrue($plan->pushEnabled);
    }

    public function testBuildReleasePlanCanDisableTagAndPush(): void
    {
        $git = new GitClient();
        $planner = new ReleasePlanner(new VersionResolver($git), $git);
        $repo = $this->createRepoFixture([], 'v1.2.3', false);

        $plan = $planner->buildReleasePlan('patch', '1.2.3', 'tag', false, false, $repo);

        self::assertSame('1.2.4', $plan->version);
        self::assertFalse($plan->updatesComposer);
        self::assertNull($plan->tagName);
        self::assertFalse($plan->pushEnabled);
    }

    public function testBuildReleasePlanUsesPlainPushWhenTagIsDisabled(): void
    {
        $git = new GitClient();
        $planner = new ReleasePlanner(new VersionResolver($git), $git);
        $repo = $this->createRepoFixture([], 'v1.2.3', false);

        $plan = $planner->buildReleasePlan('patch', '1.2.3', 'tag', false, true, $repo);

        self::assertNull($plan->tagName);
        self::assertTrue($plan->pushEnabled);
    }

    public function testBuildReleasePlanUsesCustomTagName(): void
    {
        $git = new GitClient();
        $planner = new ReleasePlanner(new VersionResolver($git), $git);
        $repo = $this->createRepoFixture([], 'v1.2.3', false);

        $plan = $planner->buildReleasePlan('patch', '1.2.3', 'tag', 'release-1.2.4', false, $repo);

        self::assertSame('release-1.2.4', $plan->tagName);
    }

    public function testBuildReleasePlanRejectsBlankCustomTagName(): void
    {
        $git = new GitClient();
        $planner = new ReleasePlanner(new VersionResolver($git), $git);
        $repo = $this->createRepoFixture([], 'v1.2.3', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tag name cannot be empty.');

        $planner->buildReleasePlan('patch', '1.2.3', 'tag', '   ', false, $repo);
    }
}
