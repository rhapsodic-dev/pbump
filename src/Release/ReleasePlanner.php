<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;

use RuntimeException;
use Rhapsodic\Pbump\Release\Dto\ReleasePlan;

final class ReleasePlanner
{
    private readonly VersionResolver $versionResolver;

    public function __construct(?VersionResolver $versionResolver = null, ?GitClient $git = null)
    {
        $git ??= new GitClient();
        $this->versionResolver = $versionResolver ?? new VersionResolver($git);
    }

    public function buildReleasePlan(
        string $selectedType,
        string $currentVersion,
        string $versionSource,
        bool|string $tagOption,
        bool $pushEnabled,
        string $projectRoot
    ): ReleasePlan {
        $selectedVersion = $this->versionResolver->resolveReleaseVersion($selectedType, $currentVersion, $projectRoot);
        $tagName = $this->resolveTagName($tagOption, "v{$selectedVersion}");

        return new ReleasePlan(
            version: $selectedVersion,
            commitMessage: "chore: release v{$selectedVersion}",
            updatesComposer: $versionSource === 'composer',
            tagName: $tagName,
            pushEnabled: $pushEnabled,
        );
    }

    private function resolveTagName(bool|string $tagOption, string $defaultTagName): ?string
    {
        if ($tagOption === false) {
            return null;
        }

        if ($tagOption === true) {
            return $defaultTagName;
        }

        $tagName = trim($tagOption);
        if ($tagName === '') {
            throw new RuntimeException('Tag name cannot be empty.');
        }

        return $tagName;
    }
}
