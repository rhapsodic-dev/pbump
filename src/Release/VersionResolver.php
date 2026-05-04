<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;

use RuntimeException;
use Rhapsodic\Pbump\Release\Dto\ReleaseTypeCollection;
use Rhapsodic\Pbump\Release\Dto\ReleaseTypeInfo;
use Rhapsodic\Pbump\Release\Dto\SemverParts;
use Rhapsodic\Pbump\Release\Dto\VersionContext;

final class VersionResolver
{
    private readonly GitClient $git;

    /** @var array<string, string> */
    private array $conventionalVersionCache = [];

    public function __construct(?GitClient $git = null)
    {
        $this->git = $git ?? new GitClient();
    }

    /**
     * @param array<string, mixed> $composer
     */
    public function resolveVersionContext(array $composer, string $projectRoot, string $versionSource): VersionContext
    {
        return match ($versionSource) {
            'auto' => $this->resolveAutoVersionContext($composer, $projectRoot),
            'composer' => new VersionContext('composer', $this->resolveComposerCurrentVersion($composer)),
            'tag' => new VersionContext('tag', $this->resolveTagCurrentVersion($projectRoot)),
            default => throw new RuntimeException(
                'Invalid --version-source: ' . $versionSource . '. Available: '
                . implode(', ', ReleaseMetadata::VERSION_SOURCES)
            ),
        };
    }

    /**
     * @param list<string> $types
     */
    public function buildReleaseTypes(array $types, string $currentVersion, string $projectRoot): ReleaseTypeCollection
    {
        $releaseTypes = [];

        foreach ($types as $type) {
            $releaseTypes[$type] = $this->buildReleaseTypeInfo($type, $currentVersion, $projectRoot);
        }

        return new ReleaseTypeCollection($releaseTypes);
    }

    public function resolveReleaseVersion(string $type, string $currentVersion, string $projectRoot): string
    {
        return match ($type) {
            'next', 'conventional' => $this->resolveConventionalVersion($currentVersion, $projectRoot),
            'patch' => $this->bumpPatch($currentVersion),
            'minor' => $this->bumpMinor($currentVersion),
            'major' => $this->bumpMajor($currentVersion),
            'as-is' => $currentVersion,
            default => throw new RuntimeException("Invalid release type: {$type}"),
        };
    }

    public function validateReleaseType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $allowed = ReleaseMetadata::availableReleaseTypes();
        if (!in_array($type, $allowed, true)) {
            throw new RuntimeException("Invalid --type: {$type}. Available: " . implode(', ', $allowed));
        }

        return $type;
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function resolveAutoVersionContext(array $composer, string $projectRoot): VersionContext
    {
        $composerVersion = $this->readComposerVersion($composer);
        if ($composerVersion !== null) {
            return new VersionContext('composer', $this->validateCurrentVersion($composerVersion));
        }

        return new VersionContext('tag', $this->resolveTagCurrentVersion($projectRoot));
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function resolveComposerCurrentVersion(array $composer): string
    {
        $composerVersion = $this->readComposerVersion($composer);
        if ($composerVersion === null) {
            throw new RuntimeException(
                'composer.json is missing a string "version" field. Use --version-source=tag or add version.'
            );
        }

        return $this->validateCurrentVersion($composerVersion);
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function readComposerVersion(array $composer): ?string
    {
        if (!array_key_exists('version', $composer) || $composer['version'] === null) {
            return null;
        }

        if (!is_string($composer['version'])) {
            throw new RuntimeException('The "version" field in composer.json must be a string.');
        }

        $version = trim($composer['version']);

        return $version === '' ? null : $version;
    }

    private function validateCurrentVersion(string $version): string
    {
        $normalizedVersion = ltrim(trim($version), 'v');
        if (!$this->isValidSemver($normalizedVersion)) {
            throw new RuntimeException("Current version '{$normalizedVersion}' does not match X.Y.Z");
        }

        return $normalizedVersion;
    }

    private function resolveTagCurrentVersion(string $projectRoot): string
    {
        $lastTag = $this->git->getLastTag($projectRoot);
        if ($lastTag === null) {
            return '0.0.0';
        }

        $currentVersion = $this->extractVersionFromTag($lastTag);
        if ($currentVersion === null || !$this->isValidSemver($currentVersion)) {
            throw new RuntimeException("Latest tag '{$lastTag}' does not contain a semver version in X.Y.Z format.");
        }

        return $currentVersion;
    }

    private function extractVersionFromTag(string $tag): ?string
    {
        if (!preg_match('/(?:^|[^0-9])v?(\d+\.\d+\.\d+)$/', $tag, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function buildReleaseTypeInfo(string $type, string $currentVersion, string $projectRoot): ReleaseTypeInfo
    {
        if (!array_key_exists($type, ReleaseMetadata::RELEASE_TYPE_DESCRIPTIONS)) {
            throw new RuntimeException("Unknown release type: {$type}");
        }

        return new ReleaseTypeInfo(
            $this->resolveReleaseVersion($type, $currentVersion, $projectRoot),
            ReleaseMetadata::RELEASE_TYPE_DESCRIPTIONS[$type]
        );
    }

    private function resolveConventionalVersion(string $currentVersion, string $projectRoot): string
    {
        $cacheKey = $projectRoot . "\0" . $currentVersion;

        // The menu/plan flow may ask for the same computed version more than once.
        return $this->conventionalVersionCache[$cacheKey]
            ??= $this->bumpConventional($currentVersion, $projectRoot);
    }

    private function isValidSemver(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $version);
    }

    private function bumpMajor(string $version): string
    {
        $parts = $this->parseSemver($version);

        return ($parts->major + 1) . '.0.0';
    }

    private function bumpMinor(string $version): string
    {
        $parts = $this->parseSemver($version);

        return $parts->major . '.' . ($parts->minor + 1) . '.0';
    }

    private function bumpPatch(string $version): string
    {
        $parts = $this->parseSemver($version);

        return $parts->major . '.' . $parts->minor . '.' . ($parts->patch + 1);
    }

    private function parseSemver(string $version): SemverParts
    {
        $parts = array_map('intval', explode('.', $version));

        return new SemverParts($parts[0], $parts[1], $parts[2]);
    }

    private function bumpConventional(string $currentVersion, string $cwd): string
    {
        $stdout = $this->git->readConventionalLog($cwd);

        // Conventional commit precedence is breaking change, then feature, then patch.
        if (
            preg_match('/BREAKING CHANGE:/i', $stdout) ||
            preg_match('/^[a-z]+(\(.+\))?!:/mi', $stdout)
        ) {
            return $this->bumpMajor($currentVersion);
        }

        if (preg_match('/^feat(\(.+\))?:/mi', $stdout)) {
            return $this->bumpMinor($currentVersion);
        }

        return $this->bumpPatch($currentVersion);
    }
}
