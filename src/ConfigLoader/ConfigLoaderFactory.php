<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader;

use Butschster\ContextGenerator\ConfigLoader\Parser\CompositeConfigParser;
use Butschster\ContextGenerator\ConfigLoader\Parser\ConfigParser;
use Butschster\ContextGenerator\ConfigLoader\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\ConfigLoader\Reader\JsonReader;
use Butschster\ContextGenerator\ConfigLoader\Reader\PhpReader;
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
            rootPath: $this->rootPath,
            logger: $this->logger,
        );

        $yamlLoader = new ConfigLoader(
            configPath: $rootPath . '/context.yaml',
            reader: $yamlReader,
            parser: $compositeParser,
            rootPath: $this->rootPath,
            logger: $this->logger,
        );

        $ymlLoader = new ConfigLoader(
            configPath: $rootPath . '/context.yml',
            reader: $yamlReader,
            parser: $compositeParser,
            rootPath: $this->rootPath,
            logger: $this->logger,
        );

        $phpLoader = new ConfigLoader(
            configPath: $rootPath . '/context.php',
            reader: $phpReader,
            parser: $compositeParser,
            rootPath: $this->rootPath,
            logger: $this->logger,
        );

        // Create composite loader
        return new CompositeConfigLoader(
            loaders: [$jsonLoader, $yamlLoader, $ymlLoader, $phpLoader],
            logger: $this->logger,
        );
    }
}
