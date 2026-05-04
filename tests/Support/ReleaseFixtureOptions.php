<?php

declare(strict_types=1);

final readonly class ReleaseFixtureOptions
{
    /**
     * @param array<string, mixed> $composer
     */
    public function __construct(
        public array $composer = [],
    ) {
    }
}
