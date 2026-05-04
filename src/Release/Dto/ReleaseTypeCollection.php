<?php

declare(strict_types=1);

namespace Rhapsodic\Pbump\Release\Dto;

use RuntimeException;

final readonly class ReleaseTypeCollection
{
    /** @var array<string, ReleaseTypeInfo> */
    private array $items;

    /**
     * @param array<string, ReleaseTypeInfo> $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function get(string $type): ReleaseTypeInfo
    {
        if (!array_key_exists($type, $this->items)) {
            throw new RuntimeException("Missing release type info for: {$type}");
        }

        return $this->items[$type];
    }
}
