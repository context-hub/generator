<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Schema\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class Enum
{
    /**
     * @param array<non-empty-string> $values
     */
    public function __construct(
        public array $values,
    ) {}
}