<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Post extends Route
{
    public function __construct(
        string $path,
        ?string $name = null,
        array $middleware = [],
    ) {
        parent::__construct($path, $name, 'POST', $middleware);
    }
}
