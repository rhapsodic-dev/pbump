<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release;

use RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Rhapsodic\Pbump\Release\Dto\ReleaseConfig;
use Rhapsodic\Pbump\Release\Dto\ResolvedOptions;

final class OptionsResolver
{
    public function buildInputDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('help', 'h', InputOption::VALUE_NONE),
            new InputOption('version', 'v', InputOption::VALUE_NONE),
            new InputOption('version-source', null, InputOption::VALUE_REQUIRED),
            new InputOption('dry-run', null, InputOption::VALUE_NONE),
            new InputOption('type', null, InputOption::VALUE_REQUIRED),
            new InputOption('tag', 't', InputOption::VALUE_OPTIONAL, '', false),
            new InputOption('no-tag', null, InputOption::VALUE_NONE),
            new InputOption('push', 'p', InputOption::VALUE_NEGATABLE),
            new InputOption('yes', 'y', InputOption::VALUE_NONE),
            new InputOption('quiet', 'q', InputOption::VALUE_NONE),
            new InputOption('allow-dirty', null, InputOption::VALUE_NONE),
        ]);
    }

    public function resolveOptions(InputInterface $input, ReleaseConfig $config): ResolvedOptions
    {
        $tagOption = $input->getOption('no-tag')
            ? false
            : ($input->getOption('tag') === false ? null : $input->getOption('tag'));

        return new ResolvedOptions(
            showHelp: (bool) $input->getOption('help'),
            showVersion: (bool) $input->getOption('version'),
            versionSource: $this->resolveVersionSource($this->resolveOptionValue(
                $input->getOption('version-source'),
                $config->versionSource,
                ReleaseMetadata::DEFAULT_VERSION_SOURCE
            )),
            dryRun: $this->resolveOptionValue($input->getOption('dry-run') ? true : null, $config->dryRun, false),
            forcedType: $this->resolveOptionValue($input->getOption('type'), $config->type, null),
            tag: $this->resolveTagOption($tagOption, $config->tag),
            push: $this->resolveOptionValue($input->getOption('push'), $config->push, true),
            yes: $this->resolveOptionValue($input->getOption('yes') ? true : null, $config->yes, false),
            quiet: $this->resolveOptionValue($input->getOption('quiet') ? true : null, $config->quiet, false),
            allowDirty: $this->resolveOptionValue(
                $input->getOption('allow-dirty') ? true : null,
                $config->allowDirty,
                false
            ),
        );
    }

    private function resolveTagOption(mixed $cliValue, mixed $configValue): bool|string
    {
        if ($cliValue !== null) {
            if ($cliValue === '') {
                return true;
            }

            return $cliValue === false ? false : $cliValue;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return true;
    }

    private function resolveOptionValue(mixed $cliValue, mixed $configValue, mixed $defaultValue): mixed
    {
        if ($cliValue !== null) {
            return $cliValue;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return $defaultValue;
    }

    private function resolveVersionSource(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Version source must be a non-empty string.');
        }

        $versionSource = trim($value);
        if (!in_array($versionSource, ReleaseMetadata::VERSION_SOURCES, true)) {
            throw new RuntimeException(
                'Invalid --version-source: ' . $versionSource . '. Available: '
                . implode(', ', ReleaseMetadata::VERSION_SOURCES)
            );
        }

        return $versionSource;
    }
}
