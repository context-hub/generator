<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable;

final readonly class CompositeProcessor implements VariableReplacementProcessorInterface
{
    /**
     * @param VariableReplacementProcessor[] $processors
     */
    public function __construct(
        public array $processors = [],
    ) {}

    public function process(string $text): string
    {
        foreach ($this->processors as $processor) {
            $text = $processor->process($text);
        }

        return $text;
    }
}
