<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable;

/**
 * Main service for resolving variables in strings
 */
final readonly class VariableResolver
{
    public function __construct(
        private VariableReplacementProcessorInterface $processor = new CompositeProcessor(),
    ) {}

    public function with(VariableReplacementProcessorInterface $processor): self
    {
        return new self(new CompositeProcessor(processors: [
            $this->processor,
            $processor,
        ]));
    }

    /**
     * Resolve variables in the given text
     */
    public function resolve(string|array|null $strings): string|array|null
    {
        if ($strings === null) {
            return null;
        }

        if (\is_array(value: $strings)) {
            return \array_map(callback: $this->resolve(...), array: $strings);
        }

        return $this->processor->process($strings);
    }
}
