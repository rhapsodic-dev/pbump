<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release\Dto;

final readonly class ResolvedOptions
{
    public function __construct(
        public bool $showHelp,
        public bool $showVersion,
        public string $versionSource,
        public bool $dryRun,
        public ?string $forcedType,
        public bool|string $tag,
        public bool $push,
        public bool $yes,
        public bool $quiet,
        public bool $allowDirty,
    ) {
    }
}
