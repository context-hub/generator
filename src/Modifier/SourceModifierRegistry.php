<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier;

use Butschster\ContextGenerator\SourceModifierInterface;

final class SourceModifierRegistry
{
    /** @var array<non-empty-string, SourceModifierInterface> */
    private array $modifiers = [];

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
