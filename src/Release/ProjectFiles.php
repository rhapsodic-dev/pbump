<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;

use RuntimeException;
use Rhapsodic\Pbump\Release\Dto\ReleaseConfig;

final class ProjectFiles
{
    /**
     * @return array<string, mixed>
     */
    public function loadComposerConfig(string $composerPath): array
    {
        if (!file_exists($composerPath)) {
            throw new RuntimeException("composer.json not found: {$composerPath}");
        }

        return $this->decodeJsonFile($composerPath, 'composer.json');
    }

    public function loadReleaseConfig(string $configPath): ReleaseConfig
    {
        if (!file_exists($configPath)) {
            return new ReleaseConfig();
        }

        $config = $this->decodeJsonFile($configPath, ReleaseMetadata::CONFIG_FILE);

        return $this->validateReleaseConfig($config);
    }

    public function writeComposerVersion(string $composerPath, string $version): void
    {
        $contents = file_get_contents($composerPath);
        if (!is_string($contents)) {
            throw new RuntimeException('Failed to read composer.json');
        }

        $updatedContents = $this->replaceTopLevelJsonStringValue($contents, 'version', $version);
        if ($updatedContents === null) {
            throw new RuntimeException('Failed to update the "version" field in composer.json');
        }

        if (file_put_contents($composerPath, $updatedContents) === false) {
            throw new RuntimeException('Failed to write composer.json');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFile(string $path, string $label): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read {$label}");
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Failed to parse ' . $label);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateReleaseConfig(array $config): ReleaseConfig
    {
        foreach ($config as $key => $value) {
            if (!in_array($key, ReleaseMetadata::RELEASE_CONFIG_KEYS, true)) {
                throw new RuntimeException('Unknown key in ' . ReleaseMetadata::CONFIG_FILE . ": {$key}");
            }

            if ($key === 'type' && (!is_string($value) || trim($value) === '')) {
                throw new RuntimeException('The "type" key in ' . ReleaseMetadata::CONFIG_FILE . ' must be a non-empty string.');
            }

            if ($key === 'versionSource') {
                if (!is_string($value) || trim($value) === '') {
                    throw new RuntimeException('The "versionSource" key in ' . ReleaseMetadata::CONFIG_FILE . ' must be a non-empty string.');
                }

                if (!in_array($value, ReleaseMetadata::VERSION_SOURCES, true)) {
                    throw new RuntimeException(
                        'The "versionSource" key in ' . ReleaseMetadata::CONFIG_FILE . ' must be one of: '
                        . implode(', ', ReleaseMetadata::VERSION_SOURCES) . '.'
                    );
                }
            }

            if ($key === 'tag') {
                if (!is_bool($value) && !is_string($value)) {
                    throw new RuntimeException('The "tag" key in ' . ReleaseMetadata::CONFIG_FILE . ' must be a boolean or string.');
                }

                if (is_string($value) && trim($value) === '') {
                    throw new RuntimeException('The "tag" key in ' . ReleaseMetadata::CONFIG_FILE . ' must not be an empty string.');
                }
            }

            if (in_array($key, ['dryRun', 'push', 'yes', 'quiet', 'allowDirty'], true) && !is_bool($value)) {
                throw new RuntimeException('The "' . $key . '" key in ' . ReleaseMetadata::CONFIG_FILE . ' must be a boolean.');
            }
        }

        return new ReleaseConfig(
            type: isset($config['type']) && is_string($config['type']) ? $config['type'] : null,
            dryRun: isset($config['dryRun']) && is_bool($config['dryRun']) ? $config['dryRun'] : null,
            tag: array_key_exists('tag', $config) && (is_bool($config['tag']) || is_string($config['tag'])) ? $config['tag'] : null,
            push: isset($config['push']) && is_bool($config['push']) ? $config['push'] : null,
            yes: isset($config['yes']) && is_bool($config['yes']) ? $config['yes'] : null,
            quiet: isset($config['quiet']) && is_bool($config['quiet']) ? $config['quiet'] : null,
            versionSource: isset($config['versionSource']) && is_string($config['versionSource']) ? $config['versionSource'] : null,
            allowDirty: isset($config['allowDirty']) && is_bool($config['allowDirty']) ? $config['allowDirty'] : null,
        );
    }

    private function replaceTopLevelJsonStringValue(string $json, string $field, string $value): ?string
    {
        $length = strlen($json);

        // Walk the original JSON text so we can update one top-level field
        // without re-encoding the file and changing the user's formatting.
        for ($index = 0, $depth = 0; $index < $length; $index++) {
            $char = $json[$index];

            if ($char === '{' || $char === '[') {
                $depth++;
                continue;
            }

            if ($char === '}' || $char === ']') {
                $depth--;
                continue;
            }

            if ($char !== '"') {
                continue;
            }

            $stringEnd = $this->findJsonStringEnd($json, $index);
            if ($stringEnd === null) {
                return null;
            }

            if ($depth !== 1) {
                $index = $stringEnd;
                continue;
            }

            $key = json_decode(substr($json, $index, $stringEnd - $index + 1), true);
            if (!is_string($key)) {
                return null;
            }

            $cursor = $this->skipJsonWhitespace($json, $stringEnd + 1);
            if ($cursor >= $length || $json[$cursor] !== ':') {
                $index = $stringEnd;
                continue;
            }

            if ($key !== $field) {
                $index = $stringEnd;
                continue;
            }

            $valueStart = $this->skipJsonWhitespace($json, $cursor + 1);
            if ($valueStart >= $length || $json[$valueStart] !== '"') {
                return null;
            }

            $valueEnd = $this->findJsonStringEnd($json, $valueStart);
            if ($valueEnd === null) {
                return null;
            }

            $replacement = json_encode($value, JSON_UNESCAPED_SLASHES);
            if (!is_string($replacement)) {
                return null;
            }

            return substr($json, 0, $valueStart) . $replacement . substr($json, $valueEnd + 1);
        }

        return null;
    }

    private function findJsonStringEnd(string $json, int $start): ?int
    {
        $length = strlen($json);

        for ($index = $start + 1, $escaped = false; $index < $length; $index++) {
            $char = $json[$index];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                return $index;
            }
        }

        return null;
    }

    private function skipJsonWhitespace(string $json, int $offset): int
    {
        $length = strlen($json);

        while ($offset < $length && preg_match('/\s/', $json[$offset]) === 1) {
            $offset++;
        }

        return $offset;
    }
}
