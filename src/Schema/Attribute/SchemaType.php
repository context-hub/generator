<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Schema\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class SchemaType
{
    public function __construct(
        public string $name,
        public string $description = '',
    ) {}
}
