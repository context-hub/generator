<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigLoader\Import\ImportResolver;
use Butschster\ContextGenerator\ConfigLoader\Parser\CompositeConfigParser;
use Butschster\ContextGenerator\ConfigLoader\Parser\ConfigParser;
use Butschster\ContextGenerator\ConfigLoader\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\ConfigLoader\Parser\ImportParserPlugin;
use Butschster\ContextGenerator\ConfigLoader\Reader\JsonReader;
use Butschster\ContextGenerator\ConfigLoader\Reader\PhpReader;
use Butschster\ContextGenerator\ConfigLoader\Reader\StringJsonReader;
use Butschster\ContextGenerator\ConfigLoader\Reader\YamlReader;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating config loaders
 */
final readonly class ConfigLoaderFactory
{
    public function __construct(
        private FilesInterface $files,
        private string $rootPath,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Create a loader for a specific config file
     *
     * @param string $rootPath Full path to the config file
     * @param array<ConfigParserPluginInterface> $parserPlugins Plugins for the config parser
     * @return ConfigLoaderInterface The config loader
     */
    public function create(string $rootPath, array $parserPlugins = []): ConfigLoaderInterface
    {
        \assert($this->logger instanceof HasPrefixLoggerInterface);

        // Create import resolver
        $importResolver = new ImportResolver(
            files: $this->files,
            loaderFactory: $this,
            logger: $this->logger?->withPrefix('import-resolver'),
        );

        // Create import parser plugin
        $importParserPlugin = new ImportParserPlugin(
            importResolver: $importResolver,
            logger: $this->logger?->withPrefix('import-parser'),
        );

        // Add import parser plugin first in the list
        $parserPlugins = [$importParserPlugin, ...$parserPlugins];

        // Create parser
        $parser = new ConfigParser($this->rootPath, $this->logger, ...$parserPlugins);

        // Create composite parser
        $compositeParser = new CompositeConfigParser($parser);

        // Create readers for different formats
        $jsonReader = new JsonReader(
            files: $this->files,
            logger: $this->logger?->withPrefix('json-reader'),
        );

        $yamlReader = new YamlReader(
            files: $this->files,
            logger: $this->logger?->withPrefix('yaml-reader'),
        );

        $phpReader = new PhpReader(
            files: $this->files,
            logger: $this->logger?->withPrefix('php-reader'),
        );

        // Try different file extensions
        $jsonLoader = new ConfigLoader(
            configPath: $rootPath . '/context.json',
            reader: $jsonReader,
            parser: $compositeParser,
            logger: $this->logger,
        );

        $yamlLoader = new ConfigLoader(
            configPath: $rootPath . '/context.yaml',
            reader: $yamlReader,
            parser: $compositeParser,
            logger: $this->logger,
        );

        $ymlLoader = new ConfigLoader(
            configPath: $rootPath . '/context.yml',
            reader: $yamlReader,
            parser: $compositeParser,
            logger: $this->logger,
        );

        $phpLoader = new ConfigLoader(
            configPath: $rootPath . '/context.php',
            reader: $phpReader,
            parser: $compositeParser,
            logger: $this->logger,
        );

        // Create composite loader
        return new CompositeConfigLoader(
            loaders: [$jsonLoader, $yamlLoader, $ymlLoader, $phpLoader],
            logger: $this->logger,
        );
    }

    // Add a new method to ConfigLoaderFactory to create a loader for a specific file path
    public function createForFile(string $filePath, array $parserPlugins = []): ConfigLoaderInterface
    {
        \assert($this->logger instanceof HasPrefixLoggerInterface);

        // Create import resolver
        $importResolver = new ImportResolver(
            files: $this->files,
            loaderFactory: $this,
            logger: $this->logger?->withPrefix('import-resolver'),
        );

        // Create import parser plugin
        $importParserPlugin = new ImportParserPlugin(
            importResolver: $importResolver,
            logger: $this->logger?->withPrefix('import-parser'),
        );

        // Add import parser plugin first in the list
        $parserPlugins = [$importParserPlugin, ...$parserPlugins];

        // Create parser
        $parser = new ConfigParser($this->rootPath, $this->logger, ...$parserPlugins);

        // Create composite parser
        $compositeParser = new CompositeConfigParser($parser);

        // Determine the file extension
        $extension = \pathinfo($filePath, PATHINFO_EXTENSION);

        // Create the appropriate reader based on file extension
        $reader = match ($extension) {
            'json' => new JsonReader(
                files: $this->files,
                logger: $this->logger?->withPrefix('json-reader'),
            ),
            'yaml', 'yml' => new YamlReader(
                files: $this->files,
                logger: $this->logger?->withPrefix('yaml-reader'),
            ),
            'php' => new PhpReader(
                files: $this->files,
                logger: $this->logger?->withPrefix('php-reader'),
            ),
            default => throw new ConfigLoaderException(\sprintf("Unsupported file extension: %s", $extension)),
        };

        // Create loader for the specific file
        return new ConfigLoader(
            configPath: $filePath,
            reader: $reader,
            parser: $compositeParser,
            logger: $this->logger,
        );
    }

    /**
     * Create a loader for an inline JSON configuration string
     *
     * @param string $jsonConfig The JSON configuration string
     * @param array<ConfigParserPluginInterface> $parserPlugins Plugins for the config parser
     * @return ConfigLoaderInterface The config loader
     */
    public function createFromString(string $jsonConfig, array $parserPlugins = []): ConfigLoaderInterface
    {
        \assert($this->logger instanceof HasPrefixLoggerInterface);

        // Create parser
        $parser = new ConfigParser($this->rootPath, $this->logger, ...$parserPlugins);

        // Create composite parser
        $compositeParser = new CompositeConfigParser($parser);

        // Create string JSON reader
        $stringJsonReader = new StringJsonReader(
            jsonContent: $jsonConfig,
            logger: $this->logger?->withPrefix('string-json-reader'),
        );

        // Create loader with a dummy path (not used by StringJsonReader)
        return new ConfigLoader(
            configPath: 'inline-config',
            reader: $stringJsonReader,
            parser: $compositeParser,
            logger: $this->logger,
        );
    }
}
