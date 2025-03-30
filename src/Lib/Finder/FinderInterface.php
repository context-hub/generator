<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Finder;

use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;

/**
 * Interface for file finders
 *
 * This interface defines the contract for different file finding implementations,
 * allowing for consistent usage across different sources (local files, GitHub, etc.)
 */
interface FinderInterface
{
    /**
     * Find files based on the given source configuration
     *
     * @param FilterableSourceInterface $source Source configuration with filter criteria
     * @param string $basePath Optional base path to normalize file paths in the tree view
     * @return FinderResult The result containing found files and tree view
     */
    public function find(FilterableSourceInterface $source, string $basePath = '', array $options = []): FinderResult;
}
