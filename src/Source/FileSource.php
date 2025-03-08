<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

/**
 * Source for files and directories
 */
final class FileSource extends BaseSource
{
    /**
     * @param string $description Human-readable description
     * @param string|array<string> $sourcePaths Paths to source files or directories
     * @param string $filePattern Pattern to match files
     * @param array<string> $excludePatterns Patterns to exclude files
     */
    public function __construct(
        public readonly string|array $sourcePaths,
        string $description = '',
        public readonly string $filePattern = '*.*',
        public readonly array $excludePatterns = [],
        public readonly bool $showTreeView = true,
    ) {
        parent::__construct($description);
    }
}
