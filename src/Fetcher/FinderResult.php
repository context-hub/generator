<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

/**
 * Data Transfer Object containing the results of a finder operation
 */
final readonly class FinderResult
{
    /**
     * @param \Traversable $files The found files
     * @param string $treeView Text representation of the file tree structure
     */
    public function __construct(
        public \Traversable $files,
        public string $treeView,
    ) {}
}
