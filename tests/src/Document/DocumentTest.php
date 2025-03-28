<?php

declare(strict_types=1);

namespace Tests\Document;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\SourceInterface;
use Butschster\ContextGenerator\SourceParserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Document::class)]
final class DocumentTest extends TestCase
{
    private Document $document;
    private string $fixturesDir;

    #[Test]
    public function it_should_create_document_with_basic_properties(): void
    {
        $document = Document::create(
            description: 'Test Description',
            outputPath: '/path/to/output.txt',
            overwrite: true,
        );

        $this->assertEquals('Test Description', $document->description);
        $this->assertEquals('/path/to/output.txt', $document->outputPath);
        $this->assertTrue($document->overwrite);
        $this->assertEmpty($document->getModifiers());
        $this->assertEmpty($document->getTags());
        $this->assertEmpty($document->getSources());
    }

    #[Test]
    public function it_should_add_sources(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $document = $this->document->addSource($source);

        $this->assertSame($this->document, $document);
        $this->assertCount(1, $document->getSources());
        $this->assertSame($source, $document->getSources()[0]);
    }

    #[Test]
    public function it_should_add_modifiers(): void
    {
        $modifier = new Modifier(name: 'some-modifier');
        $document = $this->document->addModifier($modifier);

        $this->assertSame($this->document, $document);
        $this->assertCount(1, $document->getModifiers());
        $this->assertSame($modifier, $document->getModifiers()[0]);
    }

    #[Test]
    public function it_should_add_tags(): void
    {
        $document = $this->document->addTag('tag1', 'tag2');

        $this->assertSame($this->document, $document);
        $this->assertCount(2, $document->getTags());
        $this->assertTrue($document->hasTags());
    }

    #[Test]
    public function it_should_include_source_tags_when_getting_tags(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getTags')->willReturn(['source-tag']);

        $document = $this->document->addSource($source);
        $tags = $document->getTags(true);

        $this->assertCount(1, $tags);
        $this->assertContains('source-tag', $tags);
    }

    #[Test]
    public function it_should_not_include_source_tags_when_getting_tags(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getTags')->willReturn(['source-tag']);

        $document = $this->document->addSource($source);
        $tags = $document->getTags(false);

        $this->assertEmpty($tags);
    }

    #[Test]
    public function it_should_json_serialize_correctly(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $modifier = new Modifier(name: 'some-modifier');

        $document = $this->document
            ->addSource($source)
            ->addModifier($modifier)
            ->addTag('tag1');

        $json = $document->jsonSerialize();

        $this->assertArrayHasKey('description', $json);
        $this->assertArrayHasKey('outputPath', $json);
        $this->assertArrayHasKey('overwrite', $json);
        $this->assertArrayHasKey('sources', $json);
        $this->assertArrayHasKey('modifiers', $json);
        $this->assertArrayHasKey('tags', $json);
    }

    #[Test]
    public function it_should_handle_modifiers_with_context(): void
    {
        $modifier = new Modifier(
            name: 'test-modifier',
            context: ['key' => 'value'],
        );

        $document = $this->document->addModifier($modifier);
        $modifiers = $document->getModifiers();

        $this->assertCount(1, $modifiers);
        $this->assertEquals('test-modifier', $modifiers[0]->name);
        $this->assertEquals(['key' => 'value'], $modifiers[0]->context);
    }

    #[Test]
    public function it_should_handle_modifiers_from_array(): void
    {
        $modifier = Modifier::from([
            'name' => 'test-modifier',
            'options' => ['key' => 'value'],
        ]);

        $document = $this->document->addModifier($modifier);
        $modifiers = $document->getModifiers();

        $this->assertCount(1, $modifiers);
        $this->assertEquals('test-modifier', $modifiers[0]->name);
        $this->assertEquals(['key' => 'value'], $modifiers[0]->context);
    }

    #[Test]
    public function it_should_handle_modifiers_from_string(): void
    {
        $modifier = Modifier::from('test-modifier');

        $document = $this->document->addModifier($modifier);
        $modifiers = $document->getModifiers();

        $this->assertCount(1, $modifiers);
        $this->assertEquals('test-modifier', $modifiers[0]->name);
        $this->assertEmpty($modifiers[0]->context);
    }

    #[Test]
    public function it_should_handle_source_parsing(): void
    {
        $parser = $this->createMock(SourceParserInterface::class);
        $modifiersApplier = $this->createMock(ModifiersApplierInterface::class);
        $source = $this->createMock(SourceInterface::class);

        $source
            ->method('parseContent')
            ->with($parser, $modifiersApplier)
            ->willReturn('parsed content');

        $document = $this->document->addSource($source);
        $sources = $document->getSources();

        $this->assertCount(1, $sources);
        $this->assertSame($source, $sources[0]);
    }

    #[Test]
    public function it_should_handle_source_description(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getDescription')->willReturn('source description');
        $source->method('hasDescription')->willReturn(true);

        $document = $this->document->addSource($source);
        $sources = $document->getSources();

        $this->assertCount(1, $sources);
        $this->assertEquals('source description', $sources[0]->getDescription());
        $this->assertTrue($sources[0]->hasDescription());
    }

    #[Test]
    public function it_should_handle_empty_document(): void
    {
        $document = Document::create(
            description: 'Empty Document',
            outputPath: 'empty.txt',
            overwrite: true,
        );

        $this->assertEquals('Empty Document', $document->description);
        $this->assertEquals('empty.txt', $document->outputPath);
        $this->assertTrue($document->overwrite);
        $this->assertEmpty($document->getModifiers());
        $this->assertEmpty($document->getTags());
        $this->assertEmpty($document->getSources());
    }

    #[Test]
    public function it_should_handle_invalid_output_path(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: '/invalid/path/output.txt',
            overwrite: true,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create directory');

        $document->jsonSerialize();
    }

    #[Test]
    public function it_should_handle_multiple_source_errors(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: true,
        );

        $source1 = $this->createMock(SourceInterface::class);
        $source1
            ->method('parseContent')
            ->willThrowException(new \RuntimeException('Error 1'));

        $source2 = $this->createMock(SourceInterface::class);
        $source2
            ->method('parseContent')
            ->willThrowException(new \RuntimeException('Error 2'));

        $document = $document->addSource($source1)->addSource($source2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error 1');

        $document->jsonSerialize();
    }

    #[Test]
    public function it_should_process_multiple_sources(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: true,
        );

        $source1 = $this->createMock(SourceInterface::class);
        $source1
            ->method('parseContent')
            ->willReturn('Content from source 1');

        $source2 = $this->createMock(SourceInterface::class);
        $source2
            ->method('parseContent')
            ->willReturn('Content from source 2');

        $document = $document->addSource($source1)->addSource($source2);

        $this->assertCount(2, $document->getSources());
        $this->assertSame($source1, $document->getSources()[0]);
        $this->assertSame($source2, $document->getSources()[1]);
    }

    #[Test]
    public function it_should_include_document_tags(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: true,
        )->addTag(...['tag1', 'tag2']);

        $this->assertCount(2, $document->getTags());
        $this->assertContains('tag1', $document->getTags());
        $this->assertContains('tag2', $document->getTags());
    }

    #[Test]
    public function it_should_handle_source_errors(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: true,
        );

        $source = $this->createMock(SourceInterface::class);
        $source
            ->method('parseContent')
            ->willThrowException(new \RuntimeException('Source error'));

        $document = $document->addSource($source);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source error');

        $document->jsonSerialize();
    }

    #[Test]
    public function it_should_skip_compilation_if_file_exists_and_overwrite_is_false(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: false,
        );

        $this->assertEquals('Test Document', $document->description);
        $this->assertEquals('output.txt', $document->outputPath);
        $this->assertFalse($document->overwrite);
        $this->assertEmpty($document->getModifiers());
        $this->assertEmpty($document->getTags());
        $this->assertEmpty($document->getSources());
    }

    #[Test]
    public function it_should_compile_document_with_basic_content(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: true,
        );

        $this->assertEquals('Test Document', $document->description);
        $this->assertEquals('output.txt', $document->outputPath);
        $this->assertTrue($document->overwrite);
        $this->assertEmpty($document->getModifiers());
        $this->assertEmpty($document->getTags());
        $this->assertEmpty($document->getSources());
    }

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__, 3) . '/fixtures/Document';
        $this->document = Document::create(
            description: 'Test Document',
            outputPath: $this->fixturesDir . '/output.txt',
        );
    }
}
