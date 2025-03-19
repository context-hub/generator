<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable\Provider;

/**
 * Provider that reads variables from environment variables
 */
final readonly class EnvironmentVariableProvider implements VariableProviderInterface
{
    /**
     * @param string $prefix Prefix for environment variables (optional)
     */
    public function __construct(
        private string $prefix = '',
    ) {}

    public function has(string $name): bool
    {
        $envName = $this->prefix . $name;
        return isset($_ENV[$envName]) || isset($_SERVER[$envName]) || \getenv($envName) !== false;
    }

    public function get(string $name): ?string
    {
        $envName = $this->prefix . $name;

        if (isset($_ENV[$envName])) {
            return $_ENV[$envName];
        }

        if (isset($_SERVER[$envName])) {
            return (string) $_SERVER[$envName];
        }

        $value = \getenv($envName);
        return $value !== false ? $value : null;
    }
}
