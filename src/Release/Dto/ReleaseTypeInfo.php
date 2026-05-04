<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release\Dto;

final readonly class ReleaseTypeInfo
{
    public function __construct(
        public string $version,
        public string $description,
    ) {
    }
}
