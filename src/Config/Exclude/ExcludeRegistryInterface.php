<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Exclude;

/**
 * Interface for exclusion pattern registry
 */
interface ExcludeRegistryInterface
{
    /**
     * Add an exclusion pattern
     */
    public function addPattern(ExclusionPatternInterface $pattern): self;

    /**
     * Check if a path should be excluded
     */
    public function shouldExclude(string $path): bool;

    /**
     * Get all registered exclusion patterns
     *
     * @return array<ExclusionPatternInterface>
     */
    public function getPatterns(): array;
}
