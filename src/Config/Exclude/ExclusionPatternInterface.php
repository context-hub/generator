<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Exclude;

/**
 * Interface for all exclusion patterns
 */
interface ExclusionPatternInterface extends \JsonSerializable
{
    /**
     * Check if a path matches this exclusion pattern
     */
    public function matches(string $path): bool;

    /**
     * Get the raw pattern string
     */
    public function getPattern(): string;
}
