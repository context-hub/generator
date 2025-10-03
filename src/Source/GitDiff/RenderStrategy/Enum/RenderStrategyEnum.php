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
        $normalizedValue = \strtolower(string: \trim(string: $value));

        return match ($normalizedValue) {
            'raw' => self::Raw,
            'llm' => self::LLM,
            default => throw new \InvalidArgumentException(
                message: \sprintf(
                    'Invalid render strategy "%s". Valid strategies are: %s',
                    $value,
                    \implode(separator: ', ', array: \array_column(array: self::cases(), column_key: 'value')),
                ),
            ),
        };
    }
}
