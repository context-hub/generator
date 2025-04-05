<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable\Provider;

use Spiral\Core\Attribute\Singleton;

/**
 * Provider for custom variables defined in the configuration file
 */
#[Singleton]
final class ConfigVariableProvider implements VariableProviderInterface
{
    /**
     * @var array<string, string> Custom variables from config
     */
    private array $variables = [];

    public function __construct(array $variables = [])
    {
        $this->setVariables($variables);
    }

    /**
     * Set variables from config
     *
     * @param array<string, mixed> $variables Variables from config
     */
    public function setVariables(array $variables): self
    {
        $this->variables = [];

        // Convert all values to strings
        foreach ($variables as $key => $value) {
            // Skip non-scalar values
            if (!\is_scalar($value)) {
                continue;
            }

            $this->variables[(string) $key] = (string) $value;
        }

        return $this;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->variables);
    }

    public function get(string $name): ?string
    {
        return $this->variables[$name] ?? null;
    }

    /**
     * Get all variables
     *
     * @return array<string, string>
     */
    public function getAll(): array
    {
        return $this->variables;
    }
}
