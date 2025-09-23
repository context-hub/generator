<?php

declare(strict_types=1);

namespace Tests\Unit\Drafling\Domain\ValueObject;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProjectId value object
 */
final class ProjectIdTest extends TestCase
{
    public function testFromString(): void
    {
        $projectId = ProjectId::fromString('proj_123abc');

        $this->assertSame('proj_123abc', $projectId->value);
    }

    public function testFromStringWithEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Project ID cannot be empty');

        ProjectId::fromString('');
    }

    public function testFromStringWithWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Project ID cannot be empty');

        ProjectId::fromString("   \t\n   ");
    }

    public function testEquality(): void
    {
        $id1 = ProjectId::fromString('proj_123');
        $id2 = ProjectId::fromString('proj_123');
        $id3 = ProjectId::fromString('proj_456');

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function testToString(): void
    {
        $projectId = ProjectId::fromString('proj_test');

        $this->assertSame('proj_test', (string) $projectId);
    }

    public function testValueObjectIsImmutable(): void
    {
        $projectId = ProjectId::fromString('proj_immutable');

        // Value should be read-only
        $this->assertSame('proj_immutable', $projectId->value);

        // Creating from same string should be equal but different instances
        $anotherProjectId = ProjectId::fromString('proj_immutable');
        $this->assertTrue($projectId->equals($anotherProjectId));
        $this->assertNotSame($projectId, $anotherProjectId);
    }
}
