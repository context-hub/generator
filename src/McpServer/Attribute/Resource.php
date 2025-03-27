<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Resource extends McpItem
{
    public function __construct(
        string $name,
        string $description,
        public readonly string $uri,
        public readonly string $mimeType = 'text/markdown',
    ) {
        parent::__construct($name, $description);
    }
}
