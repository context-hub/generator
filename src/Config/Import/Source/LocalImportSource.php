<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Import\ImportConfig;
use Butschster\ContextGenerator\Config\Reader\JsonReader;
use Butschster\ContextGenerator\Config\Reader\PhpReader;
use Butschster\ContextGenerator\Config\Reader\ReaderInterface;
use Butschster\ContextGenerator\Config\Reader\YamlReader;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Import source for local filesystem configurations
 */
final class LocalImportSource extends AbstractImportSource
{
    public function __construct(
        private readonly FilesInterface $files,
        private readonly JsonReader $jsonReader,
        private readonly YamlReader $yamlReader,
        private readonly PhpReader $phpReader,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function getName(): string
    {
        return 'local';
    }

    public function supports(ImportConfig $config): bool
    {
        // Local import source is the default fallback
        // It's used when no type is specified or type is explicitly 'local'
        $type = $config->type ?? 'local';
        if ($type !== 'local') {
            return false;
        }

        // Check if the file exists
        return $this->files->exists($config->absolutePath);
    }

    public function load(ImportConfig $config): array
    {
        if (!$this->supports($config)) {
            throw Exception\ImportSourceException::sourceNotSupported(
                $config->path,
                $config->type ?? 'local',
            );
        }

        $this->logger->debug('Loading local import', [
            'path' => $config->path,
            'absolutePath' => $config->absolutePath,
        ]);

        // Find an appropriate reader for the file
        $reader = $this->getReaderForFile($config->absolutePath);

        if (!$reader) {
            throw new Exception\ImportSourceException(
                \sprintf('Unsupported file format for import: %s', $config->absolutePath),
            );
        }

        // Read and parse the configuration
        $importedConfig = $this->readConfig($config->absolutePath, $reader);

        // Process selective imports if specified
        return $this->processSelectiveImports($importedConfig, $config);
    }

    /**
     * Get an appropriate reader for the given file
     */
    private function getReaderForFile(string $path): ?ReaderInterface
    {
        $extension = \pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'json' => $this->jsonReader,
            'yaml', 'yml' => $this->yamlReader,
            'php' => $this->phpReader,
            default => null,
        };
    }
}
