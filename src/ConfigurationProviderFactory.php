<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactory;
use Butschster\ContextGenerator\ConfigLoader\ConfigurationProvider;
use Butschster\ContextGenerator\ConfigLoader\Parser\ParserPluginRegistry;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;

final readonly class ConfigurationProviderFactory
{
    public function __construct(
        private ParserPluginRegistry $parserPluginRegistry,
        private FilesInterface $files,
        private HasPrefixLoggerInterface $logger,
    ) {}

    public function create(Directories $dirs): ConfigurationProvider
    {
        return new ConfigurationProvider(
            loaderFactory: new ConfigLoaderFactory(
                files: $this->files,
                rootPath: $dirs->rootPath,
                logger: $this->logger->withPrefix('config-loader'),
            ),
            files: $this->files,
            rootPath: $dirs->rootPath,
            logger: $this->logger->withPrefix('config-provider'),
            parserPlugins: $this->parserPluginRegistry->getPlugins(),
        );
    }
}
