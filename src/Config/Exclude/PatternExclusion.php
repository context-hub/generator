<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Exclude;

use Butschster\ContextGenerator\Config\Import\PathMatcher;

/**
 * Glob pattern exclusion pattern
 *
 * Excludes files and directories using glob patterns
 * with wildcards like *, **, ?, and other glob syntax
 */
final readonly class PatternExclusion extends AbstractExclusion
{
    private PathMatcher $matcher;

    public function __construct(string $pattern)
    {
        parent::__construct($pattern);
        $this->matcher = new PathMatcher(pattern: $pattern);
    }

    /**
     * Check if a path matches this exclusion pattern
     *
     * Uses glob pattern matching via PathMatcher
     */
    public function matches(string $path): bool
    {
        return $this->matcher->isMatch(path: $path);
    }

    public function jsonSerialize(): array
    {
        return [
            'pattern' => $this->pattern,
        ];
    }
}
