<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\BinaryUpdater;

use Butschster\ContextGenerator\Lib\BinaryUpdater\Strategy\UnixUpdateStrategy;
use Butschster\ContextGenerator\Lib\BinaryUpdater\Strategy\UpdateStrategyInterface;
use Butschster\ContextGenerator\Lib\BinaryUpdater\Strategy\WindowsUpdateStrategy;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Factory for creating platform-specific update strategies.
 */
final readonly class UpdaterFactory
{
    /**
     * @param FilesInterface $files File system service
     */
    public function __construct(
        private FilesInterface $files,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Create an appropriate update strategy based on the current platform.
     */
    public function createStrategy(): UpdateStrategyInterface
    {
        // Create the appropriate strategy based on the operating system
        return match (\PHP_OS_FAMILY) {
            'Windows' => new WindowsUpdateStrategy($this->files, $this->logger),
            default => new UnixUpdateStrategy($this->files, $this->logger),
        };
    }
}
