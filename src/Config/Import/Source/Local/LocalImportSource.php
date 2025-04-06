<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Local;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Source\AbstractImportSource;
use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
use Butschster\ContextGenerator\Config\Import\Source\Exception;
use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Config\Reader\ReaderInterface;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Import source for local filesystem configurations
 */
#[LoggerPrefix(prefix: 'import-source-local')]
final class LocalImportSource extends AbstractImportSource
{
    public function __construct(
        private readonly FilesInterface $files,
        private readonly ConfigReaderRegistry $readers,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function getName(): string
    {
        return 'local';
    }

    public function supports(SourceConfigInterface $config): bool
    {
        // Only support local source configs
        if (!$config instanceof LocalSourceConfig) {
            return false;
        }

        // Check if the file exists
        return $this->files->exists($config->getAbsolutePath());
    }

    public function load(SourceConfigInterface $config): array
    {
        if (!$config instanceof LocalSourceConfig) {
            throw Exception\ImportSourceException::sourceNotSupported(
                $config->getPath(),
                $config->getType(),
            );
        }

        if (!$this->supports($config)) {
            throw Exception\ImportSourceException::sourceNotSupported(
                $config->getPath(),
                $config->getType(),
            );
        }

        $this->logger->debug('Loading local import', [
            'path' => $config->getPath(),
            'absolutePath' => $config->getAbsolutePath(),
        ]);

        // Find an appropriate reader for the file
        $reader = $this->getReaderForFile($config->getAbsolutePath());

        if (!$reader) {
            throw new Exception\ImportSourceException(
                \sprintf('Unsupported file format for import: %s', $config->getAbsolutePath()),
            );
        }

        // Read and parse the configuration
        $importedConfig = $this->readConfig($config->getAbsolutePath(), $reader);

        // Process selective imports if specified
        return $this->processSelectiveImports($importedConfig, $config);
    }

    public function allowedSections(): array
    {
        return [];
    }

    /**
     * Get an appropriate reader for the given file
     */
    private function getReaderForFile(string $path): ?ReaderInterface
    {
        $extension = \pathinfo($path, PATHINFO_EXTENSION);

        if ($this->readers->has($extension)) {
            return $this->readers->get($extension);
        }

        return null;
    }
}
