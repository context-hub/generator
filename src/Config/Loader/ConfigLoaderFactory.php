<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\Import\ImportParserPlugin;
use Butschster\ContextGenerator\Config\Import\ImportResolver;
use Butschster\ContextGenerator\Config\Import\Source\ImportSourceProvider;
use Butschster\ContextGenerator\Config\Import\Source\Registry\ImportSourceRegistry;
use Butschster\ContextGenerator\Config\Parser\CompositeConfigParser;
use Butschster\ContextGenerator\Config\Parser\ConfigParser;
use Butschster\ContextGenerator\Config\Parser\ParserPluginRegistry;
use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Config\Reader\StringJsonReader;
use Butschster\ContextGenerator\Directories;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Factory for creating config loaders
 */
final readonly class ConfigLoaderFactory implements ConfigLoaderFactoryInterface
{
    public function __construct(
        private ConfigReaderRegistry $readers,
        private ParserPluginRegistry $pluginRegistry,
        private FilesInterface $files,
        private Directories $dirs,
        private ?LoggerInterface $logger = null,
    ) {}

    public function create(string $configPath): ConfigLoaderInterface
    {
        $dirs = $this->dirs->withConfigPath($configPath);

        \assert($this->logger instanceof HasPrefixLoggerInterface);

        // Create import resolver
        $importResolver = new ImportResolver(
            dirs: $dirs,
            files: $this->files,
            sourceRegistry: $sourceRegistry,
            logger: $this->logger?->withPrefix('import-resolver'),
        );

        // Create composite parser
        $compositeParser = new CompositeConfigParser(
            new ConfigParser(
                $dirs->configPath,
                $this->pluginRegistry,
                $this->logger,
            ),
        );

        // Try different file extensions
        $jsonLoader = new ConfigLoader(
            configPath: $dirs->getConfigPath('context.json'),
            reader: $this->readers->get('json'),
            parser: $compositeParser,
            logger: $this->logger,
        );

        $yamlLoader = new ConfigLoader(
            configPath: $dirs->getConfigPath('context.yaml'),
            reader: $this->readers->get('yaml'),
            parser: $compositeParser,
            logger: $this->logger,
        );

        $ymlLoader = new ConfigLoader(
            configPath: $dirs->getConfigPath('context.yml'),
            reader: $this->readers->get('yml'),
            parser: $compositeParser,
            logger: $this->logger,
        );

        $phpLoader = new ConfigLoader(
            configPath: $dirs->getConfigPath('context.php'),
            reader: $this->readers->get('php'),
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

        // Create import source registry and import sources
        $sourceRegistry = new ImportSourceRegistry(
            logger: $this->logger?->withPrefix('import-registry'),
        );

        $sourceProvider = new ImportSourceProvider(
            files: $this->files,
            githubClient: $this->githubClient,
            composerClient: $this->composerClient,
            logger: $this->logger?->withPrefix('import-sources'),
        );

        // Register all available import sources
        $sourceProvider->registerSources($sourceRegistry);

        // Create import resolver
        $importResolver = new ImportResolver(
            dirs: $dirs,
            files: $this->files,
            sourceRegistry: $sourceRegistry,
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
        $parser = new ConfigParser($this->dirs->rootPath, $this->pluginRegistry, $this->logger);

        // Create composite parser
        $compositeParser = new CompositeConfigParser($parser);

        // Determine the file extension
        $extension = \pathinfo($dirs->configPath, PATHINFO_EXTENSION);

        // Create loader for the specific file
        return new ConfigLoader(
            configPath: $dirs->configPath,
            reader: $this->readers->get($extension),
            parser: $compositeParser,
            logger: $this->logger,
        );
    }

    public function createFromString(string $jsonConfig, array $parserPlugins = []): ConfigLoaderInterface
    {
        \assert($this->logger instanceof HasPrefixLoggerInterface);

        // Create import source registry and import sources
        $sourceRegistry = new ImportSourceRegistry(
            logger: $this->logger?->withPrefix('import-registry'),
        );


        // Create parser
        $parser = new ConfigParser($this->dirs->rootPath, $this->pluginRegistry, $this->logger);

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
