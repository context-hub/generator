<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable;

/**
 * Main service for resolving variables in strings
 */
final readonly class VariableResolver
{
    public function __construct(
        private VariableReplacementProcessor $processor = new VariableReplacementProcessor(),
    ) {}

    /**
     * Resolve variables in the given text
     */
    public function resolve(string|array|null $strings): string|array|null
    {
        if ($strings === null) {
            return null;
        }

        if (\is_array($strings)) {
            return \array_map($this->resolve(...), $strings);
        }

        return $this->processor->process($strings);
    }
}
