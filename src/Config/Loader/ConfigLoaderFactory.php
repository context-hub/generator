<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Parser\CompositeConfigParser;
use Butschster\ContextGenerator\Config\Parser\ConfigParser;
use Butschster\ContextGenerator\Config\Parser\ParserPluginRegistry;
use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Config\Reader\StringJsonReader;
use Butschster\ContextGenerator\DirectoriesInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating config loaders
 */
final readonly class ConfigLoaderFactory implements ConfigLoaderFactoryInterface
{
    public function __construct(
        private ConfigReaderRegistry $readers,
        private ParserPluginRegistry $pluginRegistry,
        private DirectoriesInterface $dirs,
        #[LoggerPrefix(prefix: 'config-loader')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function create(string $configPath): ConfigLoaderInterface
    {
        $dirs = $this->dirs->withConfigPath($configPath);
        $configPathObj = $dirs->getRootPath();

        // Create composite parser using the injected plugin registry
        $compositeParser = new CompositeConfigParser(
            new ConfigParser(
                (string) $configPathObj,
                $this->pluginRegistry,
                $this->logger,
            ),
        );

        // Try different file extensions
        $jsonLoader = new ConfigLoader(
            configPath: (string) $configPathObj->join('context.json'),
            reader: $this->readers->get('json'),
            parser: $compositeParser,
            logger: $this->logger,
        );

        $yamlLoader = new ConfigLoader(
            configPath: (string) $configPathObj->join('context.yaml'),
            reader: $this->readers->get('yaml'),
            parser: $compositeParser,
            logger: $this->logger,
        );

        $ymlLoader = new ConfigLoader(
            configPath: (string) $configPathObj->join('context.yml'),
            reader: $this->readers->get('yml'),
            parser: $compositeParser,
            logger: $this->logger,
        );

        $phpLoader = new ConfigLoader(
            configPath: (string) $configPathObj->join('context.php'),
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

    public function createForFile(string $configPath): ConfigLoaderInterface
    {
        $dirs = $this->dirs->withConfigPath($configPath);
        $configPathObj = $dirs->getConfigPath();

        // Create parser with the injected plugin registry
        $parser = new ConfigParser(
            rootPath: (string) $configPathObj,
            pluginRegistry: $this->pluginRegistry,
            logger: $this->logger,
        );

        // Create composite parser
        $compositeParser = new CompositeConfigParser($parser);

        // Determine the file extension
        $extension = \pathinfo((string) $configPathObj, PATHINFO_EXTENSION);

        // Create loader for the specific file
        return new ConfigLoader(
            configPath: (string) $configPathObj,
            reader: $this->readers->get($extension),
            parser: $compositeParser,
            logger: $this->logger,
        );
    }

    public function createFromString(string $jsonConfig): ConfigLoaderInterface
    {
        // Create parser with the injected plugin registry
        $parser = new ConfigParser(
            rootPath: (string) $this->dirs->getRootPath(),
            pluginRegistry: $this->pluginRegistry,
            logger: $this->logger,
        );

        // Create composite parser
        $compositeParser = new CompositeConfigParser($parser);

        // Create string JSON reader
        $stringJsonReader = new StringJsonReader(
            jsonContent: $jsonConfig,
            logger: $this->logger instanceof HasPrefixLoggerInterface
                ? $this->logger->withPrefix('string-json-reader')
                : $this->logger,
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
