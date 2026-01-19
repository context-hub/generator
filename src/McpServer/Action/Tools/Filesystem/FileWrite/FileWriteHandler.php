<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWrite;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileWriteRequest;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\FilesInterface;

/**
 * Handler for writing content to files.
 */
final readonly class FileWriteHandler
{
    public function __construct(
        private FilesInterface $files,
        #[Proxy] private DirectoriesInterface $dirs,
        private ExcludeRegistryInterface $excludeRegistry,
        private LoggerInterface $logger,
    ) {}

    /**
     * Execute the file write operation.
     */
    public function handle(FileWriteRequest $request): FileWriteResult
    {
        $path = (string) $this->dirs->getRootPath()->join($request->path);

        // Check if path is excluded
        if ($this->excludeRegistry->shouldExclude($request->path)) {
            return FileWriteResult::error(
                \sprintf("Path '%s' is excluded by project configuration", $request->path),
            );
        }

        if (empty($path)) {
            return FileWriteResult::error('Missing path parameter');
        }

        try {
            // Ensure directory exists if requested
            if ($request->createDirectory) {
                $directory = \dirname($path);
                if (!$this->files->exists($directory)) {
                    if (!$this->files->ensureDirectory($directory)) {
                        return FileWriteResult::error(\sprintf("Could not create directory '%s'", $directory));
                    }
                }
            }

            if (\is_dir($path)) {
                return FileWriteResult::error(\sprintf("'%s' is a directory", $request->path));
            }

            $success = $this->files->write($path, $request->content);

            if (!$success) {
                return FileWriteResult::error(\sprintf("Could not write to file '%s'", $request->path));
            }

            $this->logger->info('Successfully wrote file', [
                'path' => $request->path,
                'bytes' => \strlen($request->content),
            ]);

            return FileWriteResult::success($request->path, \strlen($request->content));
        } catch (\Throwable $e) {
            $this->logger->error('Error writing file', [
                'path' => $request->path,
                'error' => $e->getMessage(),
            ]);

            return FileWriteResult::error($e->getMessage());
        }
    }
}
