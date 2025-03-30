<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Parser;

use Butschster\ContextGenerator\Config\Registry\RegistryInterface;

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
     * Update the configuration array before parsing
     *
     * This allows plugins to modify configuration by importing or transforming it.
     * Return the original array if no changes are needed.
     *
     * @param array<mixed> $config The current configuration array
     * @param string $rootPath The root path for resolving relative paths
     * @return array<mixed> The updated configuration array
     */
    public function updateConfig(array $config, string $rootPath): array;

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
