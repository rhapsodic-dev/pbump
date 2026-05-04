<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;
final class ReleaseMetadata
{
    public const CONFIG_FILE = '.pbump.config.json';
    public const DEFAULT_RELEASE_TYPE = 'next';
    public const DEFAULT_VERSION_SOURCE = 'auto';
    public const MENU_RELEASE_TYPES = ['next', 'patch', 'minor', 'major', 'as-is'];
    public const HIDDEN_RELEASE_TYPES = ['conventional'];
    public const VERSION_SOURCES = ['auto', 'composer', 'tag'];
    public const RELEASE_TYPE_DESCRIPTIONS = [
        'next' => 'Auto by conventional commits',
        'patch' => 'Bug fixes without new API',
        'minor' => 'New functionality without breaking changes',
        'major' => 'Breaking changes',
        'as-is' => 'Keep current version',
        'conventional' => 'CLI alias for next',
    ];
    public const RELEASE_CONFIG_KEYS = ['type', 'dryRun', 'tag', 'push', 'yes', 'quiet', 'versionSource', 'allowDirty'];

    /**
     * @return list<string>
     */
    public static function availableReleaseTypes(): array
    {
        return array_values(array_unique(array_merge(self::MENU_RELEASE_TYPES, self::HIDDEN_RELEASE_TYPES)));
    }
}
