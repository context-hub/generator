<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
use Butschster\ContextGenerator\Config\Import\Source\Registry\ImportSourceRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service provider for accessing import sources
 */
final readonly class ImportSourceProvider
{
    public function __construct(
        private ImportSourceRegistry $sourceRegistry,
        #[LoggerPrefix(prefix: 'import-sources')]
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
     * Find an appropriate import source for the given source configuration
     *
     * @param SourceConfigInterface $config Source configuration
     * @return ImportSourceInterface|null The matching import source or null if none found
     */
    public function findSourceForConfig(SourceConfigInterface $config): ?ImportSourceInterface
    {
        // First try to find source by type
        $sourceName = $config->getType();
        if ($this->sourceRegistry->has($sourceName)) {
            $source = $this->sourceRegistry->get($sourceName);
            if ($source->supports($config)) {
                return clone $source;
            }
        }

        // If not found by type, try all registered sources
        foreach ($this->sourceRegistry->all() as $source) {
            if ($source->supports($config)) {
                return clone $source;
            }
        }

        $this->logger?->warning('No import source found for config', [
            'path' => $config->getPath(),
            'type' => $config->getType(),
        ]);

        return null;
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
