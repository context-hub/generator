<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document;

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\Error\ErrorCollection;
use Butschster\ContextGenerator\Error\SourceError;
use Butschster\ContextGenerator\FilesInterface;
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

        $this->files->write($resultPath, $compiledDocument->content);

        return $compiledDocument;
    }

    /**
     * Build document content from all sources
     */
    private function buildContent(ErrorCollection $errors, Document $document): CompiledDocument
    {
        // Add document description
        $content = "## DOCUMENT: {$document->description}" . PHP_EOL . PHP_EOL;


        // Process all sources
        foreach ($document->getSources() as $source) {
            try {
                if ($source->hasDescription()) {
                    $content .= "SOURCE: {$source->getDescription()}" . PHP_EOL;
                }

                $content .= $source->parseContent($this->parser);
                $content .= PHP_EOL;
                $content .= '---' . PHP_EOL;
            } catch (\Throwable $e) {
                $errors->add(
                    new SourceError(
                        source: $source,
                        exception: $e,
                    ),
                );
            }
        }

        $content .= PHP_EOL;

        // Remove empty lines at the beginning of each line
        return new CompiledDocument(
            content: \preg_replace('/^\s*[\r\n]+/m', '', $content),
            errors: $errors,
        );
    }
}
