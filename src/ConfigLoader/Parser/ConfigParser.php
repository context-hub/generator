<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Parser;

use Butschster\ContextGenerator\ConfigLoader\Registry\ConfigRegistry;
use Psr\Log\LoggerInterface;

final readonly class ConfigParser implements ConfigParserInterface
{
    /** @var array<ConfigParserPluginInterface> */
    private array $plugins;

    /**
     * @param string $rootPath The root path for resolving relative paths
     * @param array<ConfigParserPluginInterface> $plugins The parser plugins
     */
    public function __construct(
        private string $rootPath,
        private ?LoggerInterface $logger = null,
        ConfigParserPluginInterface ...$plugins,
    ) {
        $this->plugins = \array_values($plugins);
    }

    public function parse(array $config): ConfigRegistry
    {
        $registry = new ConfigRegistry();

        // First, allow plugins to update the configuration (imports etc.)
        $currentConfig = $this->preprocessConfig($config);

        foreach ($this->plugins as $plugin) {
            try {
                if (!$plugin->supports($currentConfig)) {
                    continue;
                }

                $parsedRegistry = $plugin->parse($currentConfig, $this->rootPath);

                if ($parsedRegistry !== null) {
                    $registry->register($parsedRegistry);
                }
            } catch (\Throwable $e) {
                // Log the error and continue with other plugins
                $pluginClass = $plugin::class;

                $this->logger?->error("Error parsing config with plugin '{$pluginClass}': {$e->getMessage()}", [
                    'exception' => $e,
                    'plugin' => $pluginClass,
                    'configKey' => $plugin->getConfigKey(),
                ]);
            }
        }

        return $registry;
    }

    /**
     * Preprocess configuration with all plugins that can update it
     */
    private function preprocessConfig(array $config): array
    {
        $currentConfig = $config;

        foreach ($this->plugins as $plugin) {
            try {
                // Check if plugin can update this config
                if (!$plugin->supports($currentConfig)) {
                    continue;
                }

                // Update the config
                $updatedConfig = $plugin->updateConfig($currentConfig, $this->rootPath);

                // If the config was changed, log it
                if ($updatedConfig !== $currentConfig) {
                    $this->logger?->debug('Configuration updated by plugin', [
                        'plugin' => $plugin::class,
                        'configKey' => $plugin->getConfigKey(),
                    ]);

                    $currentConfig = $updatedConfig;
                }
            } catch (\Throwable $e) {
                // Log the error and continue with other plugins
                $pluginClass = $plugin::class;

                $this->logger?->error(
                    \sprintf("Error preprocessing config with plugin '%s': %s", $pluginClass, $e->getMessage()),
                    [
                        'exception' => $e,
                        'plugin' => $pluginClass,
                        'configKey' => $plugin->getConfigKey(),
                    ],
                );
            }
        }

        return $currentConfig;
    }
}
