<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Alias;

use Butschster\ContextGenerator\Modifier\Modifier;

/**
 * Registry for named modifier configurations
 */
final class AliasesRegistry
{
    /**
     * @var array<string, Modifier>
     */
    private array $aliases = [];

    /**
     * Register a named modifier configuration
     */
    public function register(string $alias, Modifier $modifier): self
    {
        $this->aliases[$alias] = $modifier;

        return $this;
    }

    /**
     * Check if an alias exists
     */
    public function has(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    /**
     * Get a modifier by its alias
     */
    public function get(string $alias): ?Modifier
    {
        return $this->aliases[$alias] ?? null;
    }

    /**
     * Get all registered aliases
     *
     * @return array<string, Modifier>
     */
    public function all(): array
    {
        return $this->aliases;
    }
}
