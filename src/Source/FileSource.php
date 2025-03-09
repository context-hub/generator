<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

/**
 * Source for files and directories
 */
class FileSource extends BaseSource
{
    /**
     * @param string $description Human-readable description
     * @param string|array<string> $sourcePaths Paths to source files or directories
     * @param string $filePattern Pattern to match files
     * @param array<string> $excludePatterns Patterns to exclude files
     * @param array<string> $modifiers Identifiers for content modifiers to apply
     */
    public function __construct(
        public readonly string|array $sourcePaths,
        string $description = '',
        public readonly string $filePattern = '*.*',
        public readonly array $excludePatterns = [],
        public readonly bool $showTreeView = true,
        public readonly array $modifiers = [],
    ) {
        parent::__construct($description);
    }
}
