<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Loader;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Directories;

/**
 * Interface for factories that create config loaders
 */
interface ConfigLoaderFactoryInterface
{
    /**
     * Create a loader for a specific config file
     *
     * @param array<ConfigParserPluginInterface> $parserPlugins Plugins for the config parser
     * @return ConfigLoaderInterface The config loader
     */
    public function create(Directories $dirs, array $parserPlugins = []): ConfigLoaderInterface;

    /**
     * Create a loader for a specific file path
     *
     * @param array<ConfigParserPluginInterface> $parserPlugins Plugins for the config parser
     * @return ConfigLoaderInterface The config loader
     */
    public function createForFile(Directories $dirs, array $parserPlugins = []): ConfigLoaderInterface;

    /**
     * Create a loader for an inline JSON configuration string
     *
     * @param string $jsonConfig The JSON configuration string
     * @param array<ConfigParserPluginInterface> $parserPlugins Plugins for the config parser
     * @return ConfigLoaderInterface The config loader
     */
    public function createFromString(string $jsonConfig, array $parserPlugins = []): ConfigLoaderInterface;
}
