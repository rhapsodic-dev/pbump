<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release\Dto;

final readonly class ReleasePlan
{
    public function __construct(
        public string $version,
        public string $commitMessage,
        public bool $updatesComposer,
        public ?string $tagName,
        public bool $pushEnabled,
    ) {
    }
}
