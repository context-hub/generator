<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Get extends Route
{
    public function __construct(
        string $path,
        ?string $name = null,
        array $middleware = [],
    ) {
        parent::__construct($path, $name, 'GET', $middleware);
    }
}
