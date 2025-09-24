<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Domain\Model;

/**
 * Template status definition with display properties
 */
final readonly class Status
{
    public function __construct(
        public string $value,
        public string $displayName,
    ) {}
}
