<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Finder;

/**
 * Data Transfer Object containing the results of a finder operation
 */
final readonly class FinderResult
{
    /**
     * @param array<int, mixed> $files The found files
     * @param string $treeView Text representation of the file tree structure
     */
    public function __construct(
        public array $files,
        public string $treeView,
    ) {}

    public function count(): int
    {
        return \count(value: $this->files);
    }
}
