<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\BinaryUpdater;

use Butschster\ContextGenerator\Lib\BinaryUpdater\Strategy\UpdateStrategyInterface;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Handles binary file updates, especially for self-update operations.
 * Uses different strategies based on the operating system to handle
 * the "file busy" scenario when updating a running binary.
 */
final readonly class BinaryUpdater
{
    public function __construct(
        private FilesInterface $files,
        private UpdateStrategyInterface $strategy,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Update a binary file, handling the case where the target file is currently in use.
     *
     * @param string $sourcePath Path to the source file containing the update
     * @param string $targetPath Path to the target binary that needs to be updated
     * @param bool $createDirectory Whether to create the target directory if it doesn't exist
     * @return bool Whether the update process was started successfully
     */
    public function update(string $sourcePath, string $targetPath, bool $createDirectory = true): bool
    {
        // Log the update attempt
        $this->logger?->info("Attempting to update binary: {$targetPath}");

        // Create the target directory if needed
        if ($createDirectory) {
            $targetDir = \dirname($targetPath);
            $this->files->ensureDirectory($targetDir);
        }

        // Try direct update first (might work if file is not in use)
        try {
            $this->logger?->info("Trying direct file update...");

            // On Windows, we need to delete the file first
            if (\PHP_OS_FAMILY === 'Windows' && $this->files->exists($targetPath)) {
                $this->files->delete($targetPath);
            }

            // Read source content
            $content = $this->files->read($sourcePath);

            // Write to target
            if (!$this->files->write($targetPath, $content)) {
                throw new \RuntimeException(\sprintf("Failed to write to target file: %s", $targetPath));
            }

            // Make executable (except on Windows)
            if (\PHP_OS_FAMILY !== 'Windows') {
                \chmod($targetPath, 0755);
            }

            $this->logger?->info("Direct update successful");
            return true;
        } catch (\Throwable $e) {
            // If direct update fails, try using platform-specific strategy
            $this->logger?->info("Direct update failed: {$e->getMessage()}");
            $this->logger?->info("Trying platform-specific update strategy...");

            // Execute the update
            return $this->strategy->update($sourcePath, $targetPath);
        }
    }
}
