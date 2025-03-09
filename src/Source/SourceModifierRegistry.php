<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\SourceModifierInterface;

final class SourceModifierRegistry
{
    /**
     * @var array<non-empty-string, SourceModifierInterface>
     */
    private array $modifiers = [];

    /**
     * Register a modifier
     */
    public function register(SourceModifierInterface $modifier): self
    {
        $this->modifiers[$modifier->getIdentifier()] = $modifier;
        return $this;
    }

    /**
     * Get a modifier by identifier
     */
    public function get(string $identifier): ?SourceModifierInterface
    {
        return $this->modifiers[$identifier] ?? null;
    }

    /**
     * Check if a modifier is registered
     */
    public function has(string $identifier): bool
    {
        return isset($this->modifiers[$identifier]);
    }
}
