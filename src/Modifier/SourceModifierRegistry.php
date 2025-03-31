<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier;

use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class SourceModifierRegistry
{
    /** @var array<non-empty-string, SourceModifierInterface> */
    private array $modifiers = [];

    /** @var array<non-empty-string, Modifier> */
    private array $aliases = [];

    /**
     * Register a modifier
     */
    public function register(SourceModifierInterface ...$modifiers): self
    {
        foreach ($modifiers as $modifier) {
            $this->modifiers[$modifier->getIdentifier()] = $modifier;
        }

        return $this;
    }

    /**
     * Register a named modifier configuration (alias)
     */
    public function registerAlias(string $alias, Modifier $modifier): self
    {
        $this->aliases[$alias] = $modifier;

        return $this;
    }

    /**
     * Check if an alias exists
     */
    public function hasAlias(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    /**
     * Get a modifier configuration by its alias
     */
    public function getAlias(string $alias): ?Modifier
    {
        return $this->aliases[$alias] ?? null;
    }

    /**
     * Get a modifier by identifier or ModifierDto
     *
     * @param Modifier $identifier The modifier identifier or DTO
     * @return SourceModifierInterface|null The modifier instance or null if not found
     */
    public function get(Modifier $identifier): ?SourceModifierInterface
    {
        $modifierName = $identifier->name;

        return $this->modifiers[$modifierName] ?? null;
    }

    /**
     * Check if a modifier is registered
     *
     * @param Modifier $identifier The modifier identifier or DTO
     * @return bool Whether the modifier is registered
     */
    public function has(Modifier $identifier): bool
    {
        return isset($this->modifiers[$identifier->name]);
    }
}
