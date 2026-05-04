<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release\Dto;

final readonly class SemverParts
{
    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
    ) {
    }
}
