<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Alias;

use Butschster\ContextGenerator\Modifier\Modifier;

/**
 * Service for resolving modifier aliases to actual Modifier objects
 */
final readonly class ModifierResolver
{
    public function __construct(
        private AliasesRegistry $aliasesRegistry = new AliasesRegistry(),
    ) {}

    /**
     * Resolve a modifier reference that might be an alias or a direct modifier definition
     *
     * @param string|array<string, mixed>|Modifier $reference
     */
    public function resolve(string|array|Modifier $reference): Modifier
    {
        // If already a Modifier instance, return it directly
        if ($reference instanceof Modifier) {
            return $reference;
        }

        // If it's a string, check if it's an alias
        if (\is_string($reference)) {
            if ($this->aliasesRegistry->has($reference)) {
                $modifier = $this->aliasesRegistry->get($reference);
                \assert($modifier !== null);
                return $modifier;
            }

            // Not an alias, treat as a direct modifier name
            return new Modifier(name: $reference);
        }

        // Otherwise, it's an array configuration
        return Modifier::from($reference);
    }

    /**
     * Resolve an array of modifier references
     *
     * @param array<string|array<string, mixed>|Modifier> $references
     * @return array<Modifier>
     */
    public function resolveAll(array $references): array
    {
        return \array_map(
            fn($reference) => $this->resolve($reference),
            $references,
        );
    }
}
