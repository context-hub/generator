<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;

final readonly class ResolvedConfig
{
    /**
     * @param SourceConfigInterface[] $imports
     */
    public function __construct(
        public array $config,
        public array $imports = [],
    ) {}
}
