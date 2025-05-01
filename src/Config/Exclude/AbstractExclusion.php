<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Exclude;

/**
 * Base class for all exclusion patterns
 */
abstract readonly class AbstractExclusion implements ExclusionPatternInterface
{
    /**
     * @param string $pattern Exclusion pattern value
     */
    public function __construct(
        protected string $pattern,
    ) {}

    /**
     * Get the raw pattern string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Normalize a pattern for consistent comparison
     */
    protected function normalizePattern(string $pattern): string
    {
        $pattern = \preg_replace('#^\./#', '', $pattern);

        // Remove trailing slash
        return \rtrim($pattern, '/');
    }

    /**
     * Abstract method to check if a path matches this pattern
     */
    abstract public function matches(string $path): bool;
}
