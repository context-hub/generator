<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

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
    public function compile(Document $document): void
    {
        $resultPath = \rtrim($this->basePath, '/') . '/' . \ltrim($document->outputPath, '/');
        if (!$document->overwrite && $this->files->exists($resultPath)) {
            return;
        }

        $content = $this->buildContent($document);

        $this->files->ensureDirectory(\dirname($resultPath));

        $this->files->write($resultPath, $content);
    }

    /**
     * Build document content from all sources
     */
    private function buildContent(Document $document): string
    {
        // Add document description
        $content = "## DOCUMENT: {$document->description}" . PHP_EOL . PHP_EOL;

        // Process all sources
        foreach ($document->getSources() as $source) {
            if ($source->hasDescription()) {
                $content .= "SOURCE: {$source->getDescription()}" . PHP_EOL;
            }

            $content .= $source->parseContent($this->parser);
            $content .= PHP_EOL;
            $content .= '---' . PHP_EOL;
        }

        $content .= PHP_EOL;

        // Remove empty lines at the beginning of each line
        return \preg_replace('/^\s*[\r\n]+/m', '', $content);
    }
}
