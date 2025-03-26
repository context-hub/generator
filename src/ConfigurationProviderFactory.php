<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactory;
use Butschster\ContextGenerator\ConfigLoader\ConfigurationProvider;
use Butschster\ContextGenerator\ConfigLoader\Parser\ParserPluginRegistry;

final readonly class ConfigurationProviderFactory
{
    public function __construct(
        private ParserPluginRegistry $parserPluginRegistry,
    ) {}

    public function create(ConfigurationProviderConfig $config): ConfigurationProvider
    {
        // If custom parser plugins are not provided, use the ones from the registry
        $parserPlugins = $config->parserPlugins;
        if (empty($parserPlugins)) {
            $parserPlugins = $this->parserPluginRegistry->getPlugins();
        }

        return new ConfigurationProvider(
            loaderFactory: new ConfigLoaderFactory(
                files: $config->files,
                rootPath: $config->rootPath,
                logger: $config->logger->withPrefix('config-loader'),
            ),
            files: $config->files,
            rootPath: $config->rootPath,
            logger: $config->logger->withPrefix('config-provider'),
            parserPlugins: $parserPlugins,
        );
    }
}