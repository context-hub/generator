<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Registry;

use Butschster\ContextGenerator\Source\SourceInterface;

interface SourceProviderInterface
{
    /**
     * Check if a source type is registered
     */
    public function has(string $type): bool;

    /**
     * Create a source from configuration
     */
    public function create(string $type, array $config): SourceInterface;
}
