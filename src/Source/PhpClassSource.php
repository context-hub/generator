<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

final class PhpClassSource extends FileSource
{
    public function __construct(
        array|string $sourcePaths,
        string $description = '',
        string $filePattern = '*.php',
        array $excludePatterns = [],
        bool $showTreeView = true,
        public readonly bool $onlySignatures = true,
    ) {
        parent::__construct($sourcePaths, $description, $filePattern, $excludePatterns, $showTreeView);
    }
}
