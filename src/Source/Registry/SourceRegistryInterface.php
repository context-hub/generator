<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Registry;

/**
 * Registry for source factories
 */
interface SourceRegistryInterface
{
    /**
     * Register a factory for a source type
     */
    public function register(SourceFactoryInterface $factory): self;
}
