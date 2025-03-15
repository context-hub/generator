<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Finder;

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
        public iterable $files,
        public string $treeView,
    ) {}

    public function count(): int
    {
        return \iterator_count($this->files);
    }
}
