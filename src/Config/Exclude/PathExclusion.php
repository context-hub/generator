<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Exclude;

/**
 * Exact path exclusion pattern
 *
 * Excludes specific paths (directories or files)
 */
final readonly class PathExclusion extends AbstractExclusion
{
    private string $normalizedPattern;

    public function __construct(string $pattern)
    {
        parent::__construct($pattern);
        $this->normalizedPattern = $this->normalizePattern(pattern: $pattern);
    }

    /**
     * Check if a path matches this exclusion pattern
     *
     * A path matches if it's exactly the same as the pattern
     * or if it's a file within the directory specified by the pattern
     */
    public function matches(string $path): bool
    {
        $normalizedPath = $this->normalizePattern(pattern: $path);

        return $normalizedPath === $this->normalizedPattern ||
            \str_contains(haystack: $normalizedPath, needle: $this->normalizedPattern);
    }

    public function jsonSerialize(): array
    {
        return [
            'pattern' => $this->pattern,
        ];
    }
}
