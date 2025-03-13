<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document;

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\Error\ErrorCollection;
use Butschster\ContextGenerator\Error\SourceError;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\SourceParserInterface;

/**
 * Handles compiling documents
 */
final readonly class DocumentCompiler
{
    /**
     * @param string $basePath Base path for relative file references
     */
    public function __construct(
        private FilesInterface $files,
        private SourceParserInterface $parser,
        private string $basePath,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
    ) {}

    /**
     * Compile a document with all its sources
     */
    public function compile(Document $document): CompiledDocument
    {
        $errors = new ErrorCollection();
        $resultPath = \rtrim($this->basePath, '/') . '/' . \ltrim($document->outputPath, '/');
        if (!$document->overwrite && $this->files->exists($resultPath)) {
            return new CompiledDocument(
                content: '',
                errors: $errors,
            );
        }

        $compiledDocument = $this->buildContent($errors, $document);

        $this->files->ensureDirectory(\dirname($resultPath));

        $this->files->write($resultPath, (string) $compiledDocument->content);

        return $compiledDocument;
    }

    /**
     * Build document content from all sources
     */
    private function buildContent(ErrorCollection $errors, Document $document): CompiledDocument
    {
        $builder = $this->builderFactory->create();

        $builder->addTitle($document->description);


        // Process all sources
        foreach ($document->getSources() as $source) {
            try {
                if ($source->hasDescription()) {
                    $builder->addDescription("SOURCE: {$source->getDescription()}");
                }

                $builder
                    ->addText($source->parseContent($this->parser))
                    ->addSeparator();
            } catch (\Throwable $e) {
                $errors->add(
                    new SourceError(
                        source: $source,
                        exception: $e,
                    ),
                );
            }
        }

        // Remove empty lines at the beginning of each line
        return new CompiledDocument(
            content: $builder,
            errors: $errors,
        );
    }
}
