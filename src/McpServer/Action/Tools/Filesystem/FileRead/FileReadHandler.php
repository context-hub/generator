<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead;

use Butschster\ContextGenerator\DirectoriesInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

/**
 * Handler for reading a single file.
 */
final readonly class FileReadHandler
{
    private const int MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        private FilesInterface $files,
        #[Proxy] private DirectoriesInterface $dirs,
        private LoggerInterface $logger,
    ) {}

    /**
     * Read a single file and return the result.
     *
     * @param string $relativePath Path relative to project root
     * @param string $encoding File encoding (currently unused, reserved for future)
     */
    public function read(string $relativePath, string $encoding = 'utf-8'): FileReadResult
    {
        $fullPath = (string) $this->dirs->getRootPath()->join($relativePath);

        // Validate file exists
        if (!$this->files->exists($fullPath)) {
            $this->logger->warning('File not found', ['path' => $relativePath]);

            return FileReadResult::error(
                $relativePath,
                \sprintf("File '%s' does not exist", $relativePath),
            );
        }

        // Validate not a directory
        if (\is_dir($fullPath)) {
            $this->logger->warning('Path is a directory', ['path' => $relativePath]);

            return FileReadResult::error(
                $relativePath,
                \sprintf("'%s' is a directory, not a file", $relativePath),
            );
        }

        // Check file size
        $size = $this->files->size($fullPath);
        if ($size > self::MAX_FILE_SIZE) {
            $this->logger->warning('File too large', [
                'path' => $relativePath,
                'size' => $size,
                'maxSize' => self::MAX_FILE_SIZE,
            ]);

            return FileReadResult::error(
                $relativePath,
                \sprintf(
                    "File '%s' is too large (%d bytes). Maximum size is %d bytes.",
                    $relativePath,
                    $size,
                    self::MAX_FILE_SIZE,
                ),
            );
        }

        try {
            $content = $this->files->read($fullPath);

            $this->logger->info('Successfully read file', [
                'path' => $relativePath,
                'size' => \strlen($content),
            ]);

            return FileReadResult::success($relativePath, $content);
        } catch (FilesException $e) {
            $this->logger->error('Failed to read file', [
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);

            return FileReadResult::error(
                $relativePath,
                \sprintf("Could not read file '%s': %s", $relativePath, $e->getMessage()),
            );
        }
    }
}
