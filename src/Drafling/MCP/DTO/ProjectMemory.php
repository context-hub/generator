<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\MCP\DTO;

final readonly class ProjectMemory
{
    public function __construct(
        public string $record,
    ) {}
}
