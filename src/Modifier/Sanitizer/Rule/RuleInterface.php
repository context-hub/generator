<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Sanitizer\Rule;

/**
 * Interface for sanitization rules
 */
interface RuleInterface
{
    /**
     * Get rule unique name
     */
    public function getName(): string;

    /**
     * Apply the sanitization rule to the content
     */
    public function apply(string $content): string;
}
