<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document\Compiler;

use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Butschster\ContextGenerator\Document\Compiler\Error\SourceError;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\SourceParserInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles compiling documents
 */
final readonly class DocumentCompiler
{
    /**
     * @param string $basePath Base path for relative file references
     * @param LoggerInterface|null $logger PSR Logger instance
     */
    public function __construct(
        private FilesInterface $files,
        private SourceParserInterface $parser,
        private string $basePath,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Compile a document with all its sources
     */
    public function compile(Document $document): CompiledDocument
    {
        $this->logger?->info('Starting document compilation', [
            'document' => $document->description,
            'outputPath' => $document->outputPath,
        ]);

        $errors = new ErrorCollection();
        $resultPath = \rtrim($this->basePath, '/') . '/' . \ltrim($document->outputPath, '/');

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

        $this->logger?->debug('Writing compiled document to file', ['path' => $resultPath]);
        $this->files->write($resultPath, (string) $compiledDocument->content);

        $errorCount = \count($errors);
        if ($errorCount > 0) {
            $this->logger?->warning('Document compiled with errors', ['errorCount' => $errorCount]);
        } else {
            $this->logger?->info('Document compiled successfully', [
                'path' => $resultPath,
                'contentLength' => \strlen((string) $compiledDocument->content),
            ]);
        }

        return $compiledDocument;
    }

    /**
     * Build document content from all sources
     */
    private function buildContent(ErrorCollection $errors, Document $document): CompiledDocument
    {
        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory->create();

        $this->logger?->debug('Adding document title', ['title' => $document->description]);
        $builder->addTitle($document->description);

        $sources = $document->getSources();
        $sourceCount = \count($sources);
        $this->logger?->info('Processing document sources', ['sourceCount' => $sourceCount]);

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
                $content = $source->parseContent($this->parser);
                $this->logger?->debug('Adding parsed content to builder', [
                    'contentLength' => \strlen($content),
                ]);

                $builder
                    ->addText($content)
                    ->addSeparator();

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
}
