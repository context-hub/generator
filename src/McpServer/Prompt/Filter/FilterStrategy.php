<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Filter;

enum FilterStrategy: string
{
    /**
     * At least one of the filter conditions must match (OR).
     */
    case ANY = 'any';

    /**
     * All filter conditions must match (AND).
     */
    case ALL = 'all';

    public static function fromString(?string $strategy): self
    {
        return match (\strtolower((string) $strategy)) {
            'all' => self::ALL,
            default => self::ANY,
        };
    }
}
