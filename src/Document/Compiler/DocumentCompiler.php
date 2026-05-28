<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document\Compiler;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Butschster\ContextGenerator\Document\Compiler\Error\SourceError;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Lib\Content\Block\TextBlock;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\ModifiersApplier;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceParserInterface;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Handles compiling documents
 */
final readonly class DocumentCompiler
{
    /**
     * @param string $basePath Base path for relative file references
     * @param SourceModifierRegistry $modifierRegistry Registry for source modifiers
     * @param LoggerInterface|null $logger PSR Logger instance
     */
    public function __construct(
        private FilesInterface $files,
        private SourceParserInterface $parser,
        private string $basePath,
        private SourceModifierRegistry $modifierRegistry,
        private VariableResolver $variables,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        #[LoggerPrefix(prefix: 'document-compiler')]
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Compile a document with all its sources
     */
    public function compile(Document $document): CompiledDocument
    {
        $outputPath = $this->variables->resolve($document->outputPath);

        $this->logger?->info('Starting document compilation', [
            'outputPath' => $outputPath,
        ]);

        $errors = new ErrorCollection($document->getErrors());
        $resultPath = (string) FSPath::create($this->basePath)->join($outputPath);

        if (!$document->overwrite && $this->files->exists($resultPath)) {
            $this->logger?->notice('Document already exists and overwrite is disabled', [
                'path' => $resultPath,
            ]);

            return new CompiledDocument(
                content: '',
                errors: $errors,
            );
        }

        $this->logger?->debug('Building document content');
        $compiledDocument = $this->buildContent($errors, $document);

        $directory = \dirname($resultPath);
        $this->logger?->debug('Ensuring directory exists', ['directory' => $directory]);
        $this->files->ensureDirectory($directory);

        // Add file statistics to the generated content before writing
        $finalContent = $document->showFileStats
            ? $this->addFileStatistics($compiledDocument->content, $outputPath)
            : (string) $compiledDocument->content;

        $this->logger?->debug('Writing compiled document to file', ['path' => $resultPath]);
        $this->files->write($resultPath, $finalContent);

        $errorCount = \count($errors);
        if ($errorCount > 0) {
            $this->logger?->warning('Document compiled with errors', ['errorCount' => $errorCount]);
        } else {
            $this->logger?->info('Document compiled successfully', [
                'path' => $resultPath,
                'fileSize' => \strlen($finalContent),
            ]);
        }

        return $compiledDocument->withOutputPath($this->basePath, $outputPath);
    }

    /**
     * Build document content from all sources
     */
    public function buildContent(ErrorCollection $errors, Document $document): CompiledDocument
    {
        $description = $this->variables->resolve($document->description);

        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory->create();

        $this->logger?->debug('Adding document title', ['title' => $description]);
        $builder->addTitle($description);

        // Add document tags if present
        if ($document->hasTags()) {
            $tags = \implode(', ', $document->getTags());
            $this->logger?->debug('Adding document tags', ['tags' => $tags]);
            $builder->addBlock(new TextBlock($tags, 'DOCUMENT_TAGS'));
        }

        $sources = $document->getSources();
        $sourceCount = \count($sources);
        $this->logger?->info('Processing document sources', [
            'sourceCount' => $sourceCount,
            'hasDocumentModifiers' => $document->hasModifiers(),
        ]);

        // Create a modifiers applier with document-level modifiers if present
        $modifiersApplier = ModifiersApplier::empty($this->modifierRegistry, $this->logger)
            ->withModifiers($document->getModifiers());

        // Process all sources
        foreach ($sources as $index => $source) {
            $sourceType = $source::class;
            $this->logger?->debug('Processing source', [
                'index' => $index + 1,
                'total' => $sourceCount,
                'type' => $sourceType,
            ]);

            try {
                if ($source->hasDescription()) {
                    $description = $source->getDescription();
                    $this->logger?->debug('Adding source description', ['description' => $description]);
                    $builder->addDescription("SOURCE: {$description}");
                }

                $this->logger?->debug('Parsing source content');
                $content = $source->parseContent($this->parser, $modifiersApplier);
                $this->logger?->debug('Adding parsed content to builder', [
                    'contentLength' => \strlen($content),
                ]);

                $builder->addText($content);

                $this->logger?->debug('Source processed successfully');
            } catch (\Throwable $e) {
                $this->logger?->error('Error processing source', [
                    'sourceType' => $sourceType,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $errors->add(
                    new SourceError(
                        source: $source,
                        exception: $e,
                    ),
                );
            }
        }

        $this->logger?->debug('Content building completed');
        // Remove empty lines at the beginning of each line
        return new CompiledDocument(
            content: $builder,
            errors: $errors,
        );
    }

    /**
     * Add file statistics to the generated content.
     *
     * Statistics are computed directly from the in-memory content string — no disk I/O is
     * performed. Byte count and line count therefore describe the content as written in the
     * current run, including on the very first compilation where the output file does not yet
     * exist.
     *
     * @param string|\Stringable $content The original content
     * @param string $outputPath The configured output path (used as the display path in the stats block)
     * @return string The content with file statistics added
     */
    private function addFileStatistics(string|\Stringable $content, string $outputPath): string
    {
        $contentStr = (string) $content;
        $fileSize = \strlen($contentStr);
        $lineCount = \substr_count($contentStr, "\n") + 1;

        // Create a new content builder with the original content
        $builder = $this->builderFactory->create();
        $builder->addText($contentStr);

        // Add file statistics
        $this->logger?->debug('Adding file statistics', [
            'fileSize' => $fileSize,
            'lineCount' => $lineCount,
            'filePath' => $outputPath,
        ]);

        $builder->addFileStats($fileSize, $lineCount, $outputPath);

        return $builder->build();
    }
}
