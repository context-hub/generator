<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier;

/**
 * Interface for applying modifiers to content
 */
interface ModifiersApplierInterface
{
    /**
     * Create a new instance with additional modifiers
     *
     * @param array<Modifier> $modifiers Additional modifiers to include
     * @return self New instance with combined modifiers
     */
    public function withModifiers(array $modifiers): self;

    /**
     * Apply all collected modifiers to the content
     *
     * @param string $content Content to modify
     * @param string $filename Content type identifier (e.g., file extension)
     * @return string Modified content
     */
    public function apply(string $content, string $filename): string;
}
