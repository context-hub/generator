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
        $outputPath = $this->variables->resolve(strings: $document->outputPath);

        $this->logger?->info('Starting document compilation', [
            'outputPath' => $outputPath,
        ]);

        $errors = new ErrorCollection(errors: $document->getErrors());
        $resultPath = (string) FSPath::create(path: $this->basePath)->join($outputPath);

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
        $compiledDocument = $this->buildContent(errors: $errors, document: $document);

        $directory = \dirname(path: $resultPath);
        $this->logger?->debug('Ensuring directory exists', ['directory' => $directory]);
        $this->files->ensureDirectory($directory);

        // Add file statistics to the generated content before writing
        $finalContent = $this->addFileStatistics(content: $compiledDocument->content, resultPath: $resultPath, outputPath: $outputPath);

        $this->logger?->debug('Writing compiled document to file', ['path' => $resultPath]);
        $this->files->write($resultPath, $finalContent);

        $errorCount = \count(value: $errors);
        if ($errorCount > 0) {
            $this->logger?->warning('Document compiled with errors', ['errorCount' => $errorCount]);
        } else {
            $this->logger?->info('Document compiled successfully', [
                'path' => $resultPath,
                'contentLength' => \strlen(string: $finalContent),
                'fileSize' => $this->files->size($resultPath),
            ]);
        }

        return $compiledDocument->withOutputPath(outputPath: $this->basePath, contextPath: $outputPath);
    }

    /**
     * Build document content from all sources
     */
    public function buildContent(ErrorCollection $errors, Document $document): CompiledDocument
    {
        $description = $this->variables->resolve(strings: $document->description);

        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory->create();

        $this->logger?->debug('Adding document title', ['title' => $description]);
        $builder->addTitle(title: $description);

        // Add document tags if present
        if ($document->hasTags()) {
            $tags = \implode(separator: ', ', array: $document->getTags());
            $this->logger?->debug('Adding document tags', ['tags' => $tags]);
            $builder->addBlock(block: new TextBlock(content: $tags, tag: 'DOCUMENT_TAGS'));
        }

        $sources = $document->getSources();
        $sourceCount = \count(value: $sources);
        $this->logger?->info('Processing document sources', [
            'sourceCount' => $sourceCount,
            'hasDocumentModifiers' => $document->hasModifiers(),
        ]);

        // Create a modifiers applier with document-level modifiers if present
        $modifiersApplier = ModifiersApplier::empty(registry: $this->modifierRegistry, logger: $this->logger)
            ->withModifiers(modifiers: $document->getModifiers());

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
                    $builder->addDescription(description: "SOURCE: {$description}");
                }

                $this->logger?->debug('Parsing source content');
                $content = $source->parseContent($this->parser, $modifiersApplier);
                $this->logger?->debug('Adding parsed content to builder', [
                    'contentLength' => \strlen(string: $content),
                ]);

                $builder->addText(text: $content);

                $this->logger?->debug('Source processed successfully');
            } catch (\Throwable $e) {
                $this->logger?->error('Error processing source', [
                    'sourceType' => $sourceType,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                $errors->add(
                    error: new SourceError(
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
     * Add file statistics to the generated content
     *
     * @param string|\Stringable $content The original content
     * @param string $resultPath The actual file path where content was written
     * @param string $outputPath The configured output path
     * @return string The content with file statistics added
     */
    private function addFileStatistics(string|\Stringable $content, string $resultPath, string $outputPath): string
    {
        try {
            // Check if file exists before attempting to read it
            if (!$this->files->exists($resultPath)) {
                $this->logger?->debug('File does not exist, skipping statistics', ['path' => $resultPath]);
                return (string) $content;
            }

            $fileSize = $this->files->size($resultPath);
            $fileContent = $this->files->read($resultPath);
            $lineCount = \substr_count(haystack: $fileContent, needle: "\n") + 1; // Count lines including the last line

            // Create a new content builder with the original content
            $builder = $this->builderFactory->create();
            $builder->addText(text: (string) $content);

            // Add file statistics
            $this->logger?->debug('Adding file statistics', [
                'fileSize' => $fileSize,
                'lineCount' => $lineCount,
                'filePath' => $outputPath,
            ]);

            $builder->addFileStats(fileSize: $fileSize, lineCount: $lineCount, filePath: $outputPath);

            return $builder->build();
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to add file statistics', [
                'path' => $resultPath,
                'error' => $e->getMessage(),
            ]);
            // Return original content if statistics calculation fails
            return (string) $content;
        }
    }
}
