<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Parser;

use Butschster\ContextGenerator\ConfigLoader\Registry\RegistryInterface;

/**
 * Interface for configuration parser plugins
 */
interface ConfigParserPluginInterface
{
    /**
     * Get the configuration key this plugin handles
     */
    public function getConfigKey(): string;

    /**
     * Parse the configuration section and return a registry
     *
     * @param array<mixed> $config The full configuration array
     * @param string $rootPath The root path for resolving relative paths
     */
    public function parse(array $config, string $rootPath): ?RegistryInterface;

    /**
     * Check if this plugin supports the given configuration
     *
     * @param array<mixed> $config The full configuration array
     */
    public function supports(array $config): bool;
}
