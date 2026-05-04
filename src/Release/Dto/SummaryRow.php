<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release\Dto;

final readonly class SummaryRow
{
    public function __construct(
        public string $label,
        public string $value,
    ) {
    }
}
