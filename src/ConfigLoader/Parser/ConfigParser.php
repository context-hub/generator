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

    /**
     * Parse a JSON configuration array
     *
     * @param array<mixed> $config The configuration array
     */
    public function parse(array $config): ConfigRegistry
    {
        $registry = new ConfigRegistry();

        foreach ($this->plugins as $plugin) {
            try {
                if (!$plugin->supports($config)) {
                    continue;
                }

                $parsedRegistry = $plugin->parse($config, $this->rootPath);

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
}
