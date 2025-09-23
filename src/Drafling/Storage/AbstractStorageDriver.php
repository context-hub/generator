<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage;

use Butschster\ContextGenerator\Drafling\Config\DraflingConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for storage drivers with common functionality
 *
 * @template TConfig of object
 * @implements StorageDriverInterface<TConfig>
 */
abstract class AbstractStorageDriver implements StorageDriverInterface
{
    /** @var TConfig */
    protected object $config;

    public function __construct(
        protected readonly DraflingConfigInterface $draflingConfig,
        protected readonly ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function initialize(object $config): void
    {
        $this->config = $config;
        $this->logger?->debug('Storage driver initialized', [
            'driver' => $this->getName(),
            'config' => $config,
        ]);
    }

    #[\Override]
    public function synchronize(): void
    {
        $this->logger?->debug('Synchronizing storage state', [
            'driver' => $this->getName(),
        ]);

        // Base implementation - override in concrete classes
        $this->performSynchronization();
    }

    /**
     * Perform driver-specific synchronization
     */
    abstract protected function performSynchronization(): void;

    /**
     * Validate project ID format
     */
    protected function validateProjectId(string $projectId): void
    {
        if (empty($projectId)) {
            throw new \InvalidArgumentException('Project ID cannot be empty');
        }
    }

    /**
     * Validate entry ID format
     */
    protected function validateEntryId(string $entryId): void
    {
        if (empty($entryId)) {
            throw new \InvalidArgumentException('Entry ID cannot be empty');
        }
    }

    /**
     * Generate unique ID for entities
     */
    protected function generateId(string $prefix = ''): string
    {
        $id = \uniqid($prefix, true);
        return \str_replace('.', '_', $id);
    }

    /**
     * Get current timestamp
     */
    protected function getCurrentTimestamp(): \DateTime
    {
        return new \DateTime();
    }

    /**
     * Sanitize filename for file system safety
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove or replace unsafe characters
        $filename = \preg_replace('/[^a-zA-Z0-9\-_\.]/', '-', $filename);

        // Remove consecutive dashes
        $filename = \preg_replace('/-+/', '-', $filename);

        // Trim dashes from ends
        return \trim($filename, '-');
    }

    /**
     * Create slug from title
     */
    protected function createSlug(string $title): string
    {
        $slug = \strtolower($title);
        $slug = \preg_replace('/[^a-z0-9\s\-]/', '', $slug);
        $slug = \preg_replace('/[\s\-]+/', '-', $slug);
        return \trim($slug, '-');
    }

    /**
     * Log operation with context
     */
    protected function logOperation(string $operation, array $context = []): void
    {
        $this->logger?->info("Storage operation: {$operation}", [
            'driver' => $this->getName(),
            ...$context,
        ]);
    }

    /**
     * Log error with context
     */
    protected function logError(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        $this->logger?->error($message, [
            'driver' => $this->getName(),
            'exception' => $exception?->getMessage(),
            ...$context,
        ]);
    }
}
