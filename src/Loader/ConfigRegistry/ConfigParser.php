<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader\ConfigRegistry;

use Butschster\ContextGenerator\Loader\ConfigRegistry\Parser\ConfigParserInterface;
use Butschster\ContextGenerator\Loader\ConfigRegistry\Parser\ConfigParserPluginInterface;

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
        ConfigParserPluginInterface ...$plugins,
    ) {
        $this->plugins = \array_values($plugins);
    }

    /**
     * Parse environment variables in configuration values
     *
     * @param string|null $value The configuration value to parse
     * @return string|null The parsed value
     */
    public static function parseConfigValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Check if the value is an environment variable reference
        if (\preg_match('/^\${([A-Za-z0-9_]+)}$/', $value, $matches)) {
            // Get the environment variable name
            $envName = $matches[1];

            // Get the value from environment
            $envValue = \getenv($envName);

            // Return the environment variable value or null if not set
            return $envValue !== false ? $envValue : null;
        }

        // Check if the value has embedded environment variables
        if (\preg_match_all('/\${([A-Za-z0-9_]+)}/', $value, $matches)) {
            // Replace all environment variables in the string
            foreach ($matches[0] as $index => $placeholder) {
                $envName = $matches[1][$index];
                $envValue = \getenv($envName);

                if ($envValue !== false) {
                    $value = \str_replace($placeholder, $envValue, $value);
                }
            }
        }

        return $value;
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
            if (!$plugin->supports($config)) {
                continue;
            }

            $parsedRegistry = $plugin->parse($config, $this->rootPath);

            if ($parsedRegistry !== null) {
                $registry->register($parsedRegistry);
            }
        }

        return $registry;
    }
}
