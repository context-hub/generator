<?php

declare(strict_types=1);

namespace Tests\Unit\Research\Domain\ValueObject;

use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProjectId value object
 */
final class ResearchIdTest extends TestCase
{
    public function testFromString(): void
    {
        $projectId = ResearchId::fromString('proj_123abc');

        $this->assertSame('proj_123abc', $projectId->value);
    }

    public function testFromStringWithEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Research ID cannot be empty');

        ResearchId::fromString('');
    }

    public function testFromStringWithWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Research ID cannot be empty');

        ResearchId::fromString("   \t\n   ");
    }

    public function testEquality(): void
    {
        $id1 = ResearchId::fromString('proj_123');
        $id2 = ResearchId::fromString('proj_123');
        $id3 = ResearchId::fromString('proj_456');

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function testToString(): void
    {
        $projectId = ResearchId::fromString('proj_test');

        $this->assertSame('proj_test', (string) $projectId);
    }

    public function testValueObjectIsImmutable(): void
    {
        $projectId = ResearchId::fromString('proj_immutable');

        // Value should be read-only
        $this->assertSame('proj_immutable', $projectId->value);

        // Creating from same string should be equal but different instances
        $anotherProjectId = ResearchId::fromString('proj_immutable');
        $this->assertTrue($projectId->equals($anotherProjectId));
        $this->assertNotSame($projectId, $anotherProjectId);
    }
}
