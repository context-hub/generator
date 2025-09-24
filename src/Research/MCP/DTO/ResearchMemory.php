<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\MCP\DTO;

final readonly class ResearchMemory
{
    public function __construct(
        public string $record,
    ) {}
}
