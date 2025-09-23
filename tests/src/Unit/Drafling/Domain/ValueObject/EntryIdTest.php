<?php

declare(strict_types=1);

namespace Tests\Unit\Drafling\Domain\ValueObject;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EntryId value object
 */
final class EntryIdTest extends TestCase
{
    public function testFromString(): void
    {
        $entryId = EntryId::fromString('entry_789xyz');

        $this->assertSame('entry_789xyz', $entryId->value);
    }

    public function testFromStringWithEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entry ID cannot be empty');

        EntryId::fromString('');
    }

    public function testFromStringWithWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entry ID cannot be empty');

        EntryId::fromString("  \t\n  ");
    }

    public function testEquality(): void
    {
        $id1 = EntryId::fromString('entry_abc');
        $id2 = EntryId::fromString('entry_abc');
        $id3 = EntryId::fromString('entry_def');

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function testToString(): void
    {
        $entryId = EntryId::fromString('entry_test_123');

        $this->assertSame('entry_test_123', (string) $entryId);
    }

    public function testValueObjectIsImmutable(): void
    {
        $entryId = EntryId::fromString('entry_readonly');

        // Value should be read-only
        $this->assertSame('entry_readonly', $entryId->value);

        // Creating from same string should be equal but different instances
        $anotherEntryId = EntryId::fromString('entry_readonly');
        $this->assertTrue($entryId->equals($anotherEntryId));
        $this->assertNotSame($entryId, $anotherEntryId);
    }
}
