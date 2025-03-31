<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Import\ImportParserPlugin;
use Butschster\ContextGenerator\Config\Import\ImportResolver;
use Butschster\ContextGenerator\Config\Parser\CompositeConfigParser;
use Butschster\ContextGenerator\Config\Parser\ConfigParser;
use Butschster\ContextGenerator\Config\Reader\JsonReader;
use Butschster\ContextGenerator\Config\Reader\PhpReader;
use Butschster\ContextGenerator\Config\Reader\StringJsonReader;
use Butschster\ContextGenerator\Config\Reader\YamlReader;
use Butschster\ContextGenerator\Directories;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Factory for creating config loaders
 */
final readonly class ConfigLoaderFactory implements ConfigLoaderFactoryInterface
{
    public function __construct(
        private FilesInterface $files,
        private Directories $dirs,
        private ?LoggerInterface $logger = null,
    ) {}

    public function create(Directories $dirs, array $parserPlugins = []): ConfigLoaderInterface
    {
        \assert($this->logger instanceof HasPrefixLoggerInterface);

        // Create import resolver
        $importResolver = new ImportResolver(
            dirs: $dirs,
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
        $parser = new ConfigParser($dirs->configPath, $this->logger, ...$parserPlugins);

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
            configPath: $dirs->getConfigPath('context.json'),
            reader: $jsonReader,
            parser: $compositeParser,
            logger: $this->logger,
        );

        $yamlLoader = new ConfigLoader(
            configPath: $dirs->getConfigPath('context.yaml'),
            reader: $yamlReader,
            parser: $compositeParser,
            logger: $this->logger,
        );

        $ymlLoader = new ConfigLoader(
            configPath: $dirs->getConfigPath('context.yml'),
            reader: $yamlReader,
            parser: $compositeParser,
            logger: $this->logger,
        );

        $phpLoader = new ConfigLoader(
            configPath: $dirs->getConfigPath('context.php'),
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

    public function createForFile(Directories $dirs, array $parserPlugins = []): ConfigLoaderInterface
    {
        \assert($this->logger instanceof HasPrefixLoggerInterface);

        // Create import resolver
        $importResolver = new ImportResolver(
            dirs: $dirs,
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
        $parser = new ConfigParser($this->dirs->rootPath, $this->logger, ...$parserPlugins);

        // Create composite parser
        $compositeParser = new CompositeConfigParser($parser);

        // Determine the file extension
        $extension = \pathinfo($dirs->configPath, PATHINFO_EXTENSION);

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
            configPath: $dirs->configPath,
            reader: $reader,
            parser: $compositeParser,
            logger: $this->logger,
        );
    }

    public function createFromString(string $jsonConfig, array $parserPlugins = []): ConfigLoaderInterface
    {
        \assert($this->logger instanceof HasPrefixLoggerInterface);

        // Create parser
        $parser = new ConfigParser($this->dirs->rootPath, $this->logger, ...$parserPlugins);

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
