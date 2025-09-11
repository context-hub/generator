<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Attribute;

abstract class McpItem
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $title = null,
    ) {}
}
