<?php

declare(strict_types=1);

namespace Tests\Source;

use Butschster\ContextGenerator\Source\BaseSource;
use Butschster\ContextGenerator\SourceParserInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseSourceConstructorTest extends TestCase
{
    #[Test]
    public function it_should_store_description(): void
    {
        $source = $this->createBaseSource(description: 'Test description');
        $this->assertEquals('Test description', $source->getDescription());
    }

    #[Test]
    public function it_should_trim_description(): void
    {
        $source = $this->createBaseSource(description: '  Test description  ');
        $this->assertEquals('Test description', $source->getDescription());
    }

    #[Test]
    public function it_should_check_if_description_exists(): void
    {
        $sourceWithDescription = $this->createBaseSource(description: 'Test description');
        $sourceWithoutDescription = $this->createBaseSource(description: '');

        $this->assertTrue($sourceWithDescription->hasDescription());
        $this->assertFalse($sourceWithoutDescription->hasDescription());
    }

    #[Test]
    public function it_should_parse_content_using_parser(): void
    {
        $source = $this->createBaseSource(description: 'Test description');

        $parser = $this->createMock(SourceParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with($source)
            ->willReturn('Parsed content');

        $this->assertEquals('Parsed content', $source->parseContent($parser));
    }

    /**
     * Create a concrete implementation of the abstract BaseSource class for testing
     */
    private function createBaseSource(string $description): BaseSource
    {
        return new class($description) extends BaseSource {
            public function jsonSerialize(): array
            {
                return ['description' => $this->description];
            }
        };
    }
}