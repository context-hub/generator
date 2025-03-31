<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Registry;

use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Interface for source factories
 */
interface SourceFactoryInterface
{
    /**
     * Get the source type this factory handles
     */
    public function getType(): string;

    /**
     * Create a source from configuration
     *
     * @param array<string, mixed> $config Source configuration
     */
    public function create(array $config): SourceInterface;
}
