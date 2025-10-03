<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Filter GitHub items by file name pattern
 */
final class FilePatternFilter extends AbstractFilter
{
    /**
     * Create a new file pattern filter
     *
     * @param string|array<string> $pattern File pattern(s) to match
     */
    public function __construct(
        private readonly string|array $pattern,
    ) {}

    public function apply(array $items): array
    {
        if (empty($this->pattern)) {
            return $items;
        }

        return \array_filter(array: $items, callback: function (array $item): bool {
            // Skip directories
            if ($item['type'] === 'dir') {
                return true;
            }

            $filename = $item['name'];
            return $this->matchPattern(value: $filename, pattern: $this->pattern);
        });
    }
}
