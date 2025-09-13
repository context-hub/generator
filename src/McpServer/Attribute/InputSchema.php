<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class InputSchema
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
    ) {}
}
