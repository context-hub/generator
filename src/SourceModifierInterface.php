<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

interface SourceModifierInterface
{
    /**
     * Get the identifier for this modifier
     */
    public function getIdentifier(): string;

    /**
     * Check if this modifier supports the given content type
     */
    public function supports(string $contentType): bool;

    /**
     * Modify the source content
     */
    public function modify(string $content, array $context = []): string;
}
