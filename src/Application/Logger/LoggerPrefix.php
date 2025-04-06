<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Logger;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::TARGET_CLASS)]
final readonly class LoggerPrefix
{
    public function __construct(
        public string $prefix,
    ) {}
}
