<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release\Dto;

final readonly class ReleaseConfig
{
    public function __construct(
        public ?string $type = null,
        public ?bool $dryRun = null,
        public bool|string|null $tag = null,
        public ?bool $push = null,
        public ?bool $yes = null,
        public ?bool $quiet = null,
        public ?string $versionSource = null,
        public ?bool $allowDirty = null,
    ) {
    }
}
