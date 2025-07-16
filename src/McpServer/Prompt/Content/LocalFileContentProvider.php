<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Content;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\McpServer\Prompt\Exception\FileMessageContentException;
use Spiral\Files\FilesInterface;

/**
 * Loads content from local files using FSPath for robust path handling.
 */
final readonly class LocalFileContentProvider implements FileContentProvider
{
    public function __construct(
        private FilesInterface $files,
        private FSPath $rootPath,
    ) {}

    public function canHandle(string $source): bool
    {
        // Handle local files (not URLs)
        return !\filter_var($source, \FILTER_VALIDATE_URL);
    }

    public function load(string $source): string
    {
        $filePath = $this->resolveFilePath($source);

        if (!$filePath->exists()) {
            throw FileMessageContentException::fileNotFound($filePath->toString());
        }

        if (!$filePath->isFile()) {
            throw FileMessageContentException::fileNotReadable($filePath->toString());
        }

        if (!\is_readable($filePath->toString())) {
            throw FileMessageContentException::fileNotReadable($filePath->toString());
        }

        $content = $this->files->read($filePath->toString());

        if (empty(\trim($content))) {
            throw FileMessageContentException::emptyContent($source);
        }

        return $content;
    }

    /**
     * Resolves the file path using FSPath for robust path handling.
     */
    private function resolveFilePath(string $source): FSPath
    {
        $sourcePath = FSPath::create($source);

        // If absolute path, use as-is
        if ($sourcePath->isAbsolute()) {
            return $sourcePath;
        }

        // Resolve relative to root path
        return $this->rootPath->join($source);
    }
}
