<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Local;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Import\Source\AbstractImportSource;
use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
use Butschster\ContextGenerator\Config\Import\Source\Exception;
use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Config\Reader\MarkdownDirectoryReader;
use Butschster\ContextGenerator\Config\Reader\MarkdownMetadataReader;
use Butschster\ContextGenerator\Config\Reader\ReaderInterface;
use Psr\Log\LoggerInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Files\FilesInterface;

/**
 * Import source for local filesystem configurations
 */
#[LoggerPrefix(prefix: 'import-source-local')]
final class LocalImportSource extends AbstractImportSource
{
    private MarkdownDirectoryReader $markdownDirectoryReader;
    private MarkdownToResourceTransformer $markdownTransformer;

    public function __construct(
        private readonly FilesInterface $files,
        private readonly ConfigReaderRegistry $readers,
        ExceptionReporterInterface $reporter,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);

        // Initialize markdown-related dependencies
        $markdownReader = new MarkdownMetadataReader($files, $logger);
        $this->markdownDirectoryReader = new MarkdownDirectoryReader($markdownReader, $reporter, $logger);
        $this->markdownTransformer = new MarkdownToResourceTransformer($logger);
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

        // For markdown imports, check if path is a directory
        if ($config->isMarkdownImport()) {
            return \is_dir($config->getAbsolutePath());
        }

        // For regular config imports, check if the file exists
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

        // Handle markdown imports differently
        if ($config->isMarkdownImport()) {
            return $this->loadMarkdownImport($config);
        }

        // Handle regular config file imports
        return $this->loadConfigImport($config);
    }

    public function allowedSections(): array
    {
        return [];
    }

    /**
     * Load markdown import from a directory
     */
    private function loadMarkdownImport(LocalSourceConfig $config): array
    {
        $this->logger->debug('Loading markdown import', [
            'path' => $config->getPath(),
            'absolutePath' => $config->getAbsolutePath(),
            'format' => $config->getFormat(),
        ]);

        // Read all markdown files from the directory
        $markdownData = $this->markdownDirectoryReader->read($config->getAbsolutePath());

        // Transform markdown files into CTX resources
        $transformedConfig = $this->markdownTransformer->transform($markdownData);

        // Process selective imports if specified
        return $this->processSelectiveImports($transformedConfig, $config);
    }

    /**
     * Load regular configuration file import
     */
    private function loadConfigImport(LocalSourceConfig $config): array
    {
        $this->logger->debug('Loading config import', [
            'path' => $config->getPath(),
            'absolutePath' => $config->getAbsolutePath(),
            'format' => $config->getFormat(),
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
