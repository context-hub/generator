<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Interface for GitHub content filters
 */
interface FilterInterface
{
    /**
     * Apply the filter to the list of file items
     *
     * @param array<array<string, mixed>> $items GitHub API response items
     * @return array<array<string, mixed>> Filtered items
     */
    public function apply(array $items): array;

    /**
     * Get the filter name
     */
    public function getName(): string;
}
