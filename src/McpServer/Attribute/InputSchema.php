<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class InputSchema
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $description = null,
        public bool $required = false,
        public mixed $default = null,
        public array $properties = [],
        public array $items = [],
        public array $enum = [],
    ) {}
}
