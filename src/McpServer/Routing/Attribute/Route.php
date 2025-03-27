<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $name = null,
        public readonly string $method = 'GET',
        public readonly array $middleware = [],
    ) {}
}
