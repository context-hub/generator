<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

final readonly class Application
{
    public function __construct(
        public string $version,
        public string $name,
        public bool $isBinary,
    ) {}
}
