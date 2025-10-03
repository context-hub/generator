<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Abstract base class for GitHub content filters
 * @implements FilterInterface<array<string, mixed>>
 */
abstract class AbstractFilter implements FilterInterface
{
    /**
     * Helper method to check if a value matches a pattern
     *
     * @param string $value The value to check
     * @param string|array<string> $pattern The pattern or patterns to match against
     */
    protected function matchPattern(string $value, string|array $pattern): bool
    {
        if (empty($pattern)) {
            return true; // No pattern means match all
        }

        $patterns = \is_array(value: $pattern) ? $pattern : [$pattern];

        foreach ($patterns as $p) {
            if (\str_contains(haystack: $value, needle: $p)) {
                return true;
            }

            if (!FileHelper::isRegex(str: $pattern)) {
                $p = FileHelper::toRegex(glob: $p);
            }

            if ($this->matchGlob(value: $value, regex: $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a value against a glob pattern
     */
    protected function matchGlob(string $value, string $regex): bool
    {
        return (bool) \preg_match(pattern: $regex, subject: $value);
    }
}
