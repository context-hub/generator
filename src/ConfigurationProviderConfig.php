<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\ConfigLoader\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Psr\Log\LoggerInterface;

final readonly class ConfigurationProviderConfig
{
    /**
     * @param string $rootPath The root path for configuration files
     * @param FilesInterface $files File system interface
     * @param string|null $configPath Specific configuration file path
     * @param string|null $inlineConfig Inline JSON configuration
     * @param HasPrefixLoggerInterface&LoggerInterface $logger Logger instance
     * @param array<ConfigParserPluginInterface> $parserPlugins Parser plugins for configuration
     */
    public function __construct(
        public string $rootPath,
        public FilesInterface $files,
        public LoggerInterface $logger,
        public ?string $configPath = null,
        public ?string $inlineConfig = null,
        public array $parserPlugins = [],
    ) {}
}
