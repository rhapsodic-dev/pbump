<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Rhapsodic\Pbump\Release\ReleaseMetadata;

final class ReleaseMetadataTest extends TestCase
{
    public function testAvailableReleaseTypesReturnsUniqueSupportedTypes(): void
    {
        self::assertSame(
            ['next', 'patch', 'minor', 'major', 'as-is', 'conventional'],
            ReleaseMetadata::availableReleaseTypes()
        );
    }
}
