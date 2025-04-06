<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Butschster\ContextGenerator\Source\Text\TextSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TextSourceConstructorTest extends TestCase
{
    #[Test]
    public function it_should_store_constructor_parameters(): void
    {
        $content = 'This is some test content';
        $description = 'Test description';

        $source = new TextSource(
            content: $content,
            description: $description,
        );

        $this->assertEquals($content, $source->content);
        $this->assertEquals($description, $source->getDescription());
    }

    #[Test]
    public function it_should_have_default_empty_description(): void
    {
        $content = 'This is some test content';
        $source = new TextSource(content: $content);

        $this->assertEquals($content, $source->content);
        $this->assertEquals('', $source->getDescription());
        $this->assertFalse($source->hasDescription());
    }

    #[Test]
    public function it_should_trim_description(): void
    {
        $content = 'This is some test content';
        $description = '  Test description  ';

        $source = new TextSource(content: $content, description: $description);

        $this->assertEquals('Test description', $source->getDescription());
    }
}
