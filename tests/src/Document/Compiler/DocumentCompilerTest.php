<?php

declare(strict_types=1);

namespace Tests\Document\Compiler;

use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\Compiler\Error\SourceError;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceParserInterface;
use Butschster\ContextGenerator\SourceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(DocumentCompiler::class)]
final class DocumentCompilerTest extends TestCase
{
    private DocumentCompiler $compiler;
    private FilesInterface $files;
    private SourceParserInterface $parser;
    private SourceModifierRegistry $modifierRegistry;
    private ContentBuilderFactory $builderFactory;
    private LoggerInterface $logger;

    #[Test]
    public function it_should_compile_document_with_basic_content(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: true,
        );

        $this->files
            ->expects($this->never())
            ->method('exists');

        $this->files
            ->expects($this->once())
            ->method('ensureDirectory')
            ->with('/base/path');

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with(
                '/base/path/output.txt',
                $content = "# Test Document\n\n",
            );

        $compiled = $this->compiler->compile($document);

        $this->assertEquals($content, (string) $compiled->content);
        $this->assertEquals(0, \count($compiled->errors));
    }

    #[Test]
    public function it_should_skip_compilation_if_file_exists_and_overwrite_is_false(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: false,
        );

        $this->files
            ->expects($this->once())
            ->method('exists')
            ->with('/base/path/output.txt')
            ->willReturn(true);

        $this->files
            ->expects($this->never())
            ->method('write');

        $compiled = $this->compiler->compile($document);

        $this->assertEquals("", (string) $compiled->content);
        $this->assertEquals(0, \count($compiled->errors));
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

        $this->files
            ->expects($this->never())
            ->method('exists');

        $compiled = $this->compiler->compile($document);

        $this->assertEquals("# Test Document\n\n", (string) $compiled->content);
        $this->assertEquals(1, \count($compiled->errors));
        $this->assertInstanceOf(SourceError::class, $compiled->errors->getIterator()[0]);
    }

    #[Test]
    public function it_should_include_document_tags(): void
    {
        $document = Document::create(
            description: 'Test Document',
            outputPath: 'output.txt',
            overwrite: true,
        )->addTag(...['tag1', 'tag2']);

        $this->files
            ->expects($this->never())
            ->method('exists')
            ->with('/base/path/output.txt')
            ->willReturn(false);

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with(
                '/base/path/output.txt',
                $content = "# Test Document\n\n<DOCUMENT_TAGS>\ntag1, tag2\n</DOCUMENT_TAGS>\n\n\n",
            );

        $compiled = $this->compiler->compile($document);

        $this->assertEquals($content, (string) $compiled->content);
        $this->assertEquals(0, \count($compiled->errors));
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

        $this->files
            ->expects($this->never())
            ->method('exists');

        $this->files
            ->expects($this->once())
            ->method('write')
            ->with(
                '/base/path/output.txt',
                $content = "# Test Document\n\nContent from source 1\n\nContent from source 2\n\n",
            );

        $compiled = $this->compiler->compile($document);

        $this->assertEquals($content, (string) $compiled->content);
        $this->assertEquals(0, \count($compiled->errors));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = $this->createMock(FilesInterface::class);
        $this->parser = $this->createMock(SourceParserInterface::class);
        $this->modifierRegistry = new SourceModifierRegistry();
        $this->builderFactory = new ContentBuilderFactory();
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->compiler = new DocumentCompiler(
            files: $this->files,
            parser: $this->parser,
            basePath: '/base/path',
            modifierRegistry: $this->modifierRegistry,
            builderFactory: $this->builderFactory,
            logger: $this->logger,
        );
    }
}
