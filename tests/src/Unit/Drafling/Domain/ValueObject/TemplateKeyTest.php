<?php

declare(strict_types=1);

namespace Tests\Unit\Drafling\Domain\ValueObject;

use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TemplateKey value object
 */
final class TemplateKeyTest extends TestCase
{
    public function testFromString(): void
    {
        $templateKey = TemplateKey::fromString('blog-template');

        $this->assertSame('blog-template', $templateKey->value);
    }

    public function testFromStringWithEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template key cannot be empty');

        TemplateKey::fromString('');
    }

    public function testFromStringWithWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template key cannot be empty');

        TemplateKey::fromString("  \n\t  ");
    }

    public function testEquality(): void
    {
        $key1 = TemplateKey::fromString('blog');
        $key2 = TemplateKey::fromString('blog');
        $key3 = TemplateKey::fromString('portfolio');

        $this->assertTrue($key1->equals($key2));
        $this->assertFalse($key1->equals($key3));
    }

    public function testToString(): void
    {
        $templateKey = TemplateKey::fromString('api-docs');

        $this->assertSame('api-docs', (string) $templateKey);
    }

    public function testValueObjectIsImmutable(): void
    {
        $templateKey = TemplateKey::fromString('immutable-template');

        // Value should be read-only
        $this->assertSame('immutable-template', $templateKey->value);

        // Creating from same string should be equal but different instances
        $anotherTemplateKey = TemplateKey::fromString('immutable-template');
        $this->assertTrue($templateKey->equals($anotherTemplateKey));
        $this->assertNotSame($templateKey, $anotherTemplateKey);
    }

    public function testKeyWithSpecialCharacters(): void
    {
        $templateKey = TemplateKey::fromString('my_template-v2.0');

        $this->assertSame('my_template-v2.0', $templateKey->value);
        $this->assertSame('my_template-v2.0', (string) $templateKey);
    }
}
