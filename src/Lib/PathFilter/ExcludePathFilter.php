<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Filter GitHub items by path exclusion
 */
final class ExcludePathFilter extends AbstractFilter
{
    /**
     * Create a new exclude path filter
     *
     * @param array<string> $excludePatterns Path pattern(s) to exclude
     */
    public function __construct(
        private readonly array $excludePatterns,
    ) {}

    public function apply(array $items): array
    {
        if (empty($this->excludePatterns)) {
            return $items;
        }

        return \array_filter($items, function (array $item): bool {
            $path = $item['path'] ?? '';
            $filename = $item['name'] ?? \basename($path);

            foreach ($this->excludePatterns as $pattern) {
                // Check against full path
                if ($this->matchesPattern($path, $pattern)) {
                    return false;
                }

                // Also check against just the filename
                if ($this->matchesPattern($filename, $pattern)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Check if a string matches a pattern
     *
     * @param string $string The string to check
     * @param string $pattern The pattern to match against
     * @return bool Whether the string matches the pattern
     */
    private function matchesPattern(string $string, string $pattern): bool
    {
        if (\str_contains($string, $pattern)) {
            return true;
        }

        if (FileHelper::isRegex($pattern)) {
            return (bool) \preg_match($pattern, $string);
        }

        // Convert to regex if it's not already
        $regex = FileHelper::toRegex($pattern);
        return (bool) \preg_match($regex, $string);
    }
}
