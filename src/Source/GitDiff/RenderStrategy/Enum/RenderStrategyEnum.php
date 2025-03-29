<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Enum;

enum RenderStrategyEnum: string
{
    case Raw = 'raw';
    case LLM = 'llm';

    /**
     * Create from string value with case insensitivity
     */
    public static function fromString(string $value): self
    {
        $normalizedValue = \strtolower(\trim($value));

        return match ($normalizedValue) {
            'raw' => self::Raw,
            'llm' => self::LLM,
            default => throw new \InvalidArgumentException(
                \sprintf(
                    'Invalid render strategy "%s". Valid strategies are: %s',
                    $value,
                    \implode(', ', \array_column(self::cases(), 'value')),
                ),
            ),
        };
    }
}
