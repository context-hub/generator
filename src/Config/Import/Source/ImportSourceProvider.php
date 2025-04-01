<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\Import\ImportConfig;
use Butschster\ContextGenerator\Config\Import\Source\Registry\ImportSourceRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service provider for accessing import sources
 */
final readonly class ImportSourceProvider
{
    /**
     * @param ImportSourceRegistry $sourceRegistry Pre-configured registry with all registered import sources
     * @param LoggerInterface|null $logger Optional logger for debugging purposes
     */
    public function __construct(
        private ImportSourceRegistry $sourceRegistry,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get an import source by its name
     *
     * @param string $name Name of the import source
     * @return ImportSourceInterface The requested import source
     * @throws \InvalidArgumentException If the source is not found
     */
    public function getSource(string $name): ImportSourceInterface
    {
        return $this->sourceRegistry->get($name);
    }

    /**
     * Get all available import sources
     *
     * @return array<string, ImportSourceInterface>
     */
    public function getAllSources(): array
    {
        return $this->sourceRegistry->all();
    }

    /**
     * Find an appropriate import source for the given configuration
     *
     * @param ImportConfig $config Import configuration
     * @return ImportSourceInterface|null The matching import source or null if none found
     */
    public function findSourceForConfig(ImportConfig $config): ?ImportSourceInterface
    {
        return $this->sourceRegistry->findForConfig($config);
    }

    /**
     * Get a logger with a source-specific prefix
     *
     * @param string $sourceName Name of the source for logger prefixing
     * @return LoggerInterface Logger with appropriate prefix
     */
    public function getSourceLogger(string $sourceName): LoggerInterface
    {
        if ($this->logger === null) {
            return new NullLogger();
        }

        // Check if logger supports prefixing
        if ($this->logger instanceof HasPrefixLoggerInterface) {
            return $this->logger->withPrefix("import-{$sourceName}");
        }

        return $this->logger;
    }
}
