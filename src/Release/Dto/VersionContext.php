<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release\Dto;

final readonly class VersionContext
{
    public function __construct(
        public string $source,
        public string $currentVersion,
    ) {
    }
}
