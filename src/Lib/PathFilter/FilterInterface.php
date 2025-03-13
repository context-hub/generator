<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Interface for GitHub content filters
 * @template TItem of array<string, mixed>
 */
interface FilterInterface
{
    /**
     * Apply the filter to the list of file items
     *
     * @param array<TItem> $items GitHub API response items
     * @return array<TItem> Filtered items
     */
    public function apply(array $items): array;
}
