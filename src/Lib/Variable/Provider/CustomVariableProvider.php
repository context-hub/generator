<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable\Provider;

/**
 * Provider for custom user-defined variables
 * todo: add into a settings section
 */
final class CustomVariableProvider implements VariableProviderInterface
{
    /**
     * @var array<string, string> Map of variable names to values
     */
    private array $variables = [];

    /**
     * Set a variable value
     *
     * @param string $name Variable name
     * @param string $value Variable value
     * @return self
     */
    public function set(string $name, string $value): self
    {
        $this->variables[$name] = $value;
        return $this;
    }

    /**
     * Set multiple variables at once
     *
     * @param array<string, string> $variables Map of variable names to values
     * @return self
     */
    public function setMany(array $variables): self
    {
        foreach ($variables as $name => $value) {
            $this->set($name, $value);
        }
        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->variables);
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
    public function all(): array
    {
        return $this->variables;
    }

    /**
     * Remove a variable
     *
     * @param string $name Variable name
     * @return self
     */
    public function remove(string $name): self
    {
        unset($this->variables[$name]);
        return $this;
    }

    /**
     * Clear all variables
     *
     * @return self
     */
    public function clear(): self
    {
        $this->variables = [];
        return $this;
    }
}
