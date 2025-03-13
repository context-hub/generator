<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Filter GitHub items by path inclusion
 */
final class PathFilter extends AbstractFilter
{
    /**
     * Path pattern to include
     *
     * @var string|array<string>
     */
    private string|array $pathPattern;

    /**
     * Create a new path filter
     *
     * @param string|array<string> $pathPattern Path pattern(s) to include
     */
    public function __construct(string|array $pathPattern)
    {
        parent::__construct('path');
        $this->pathPattern = $pathPattern;
    }

    /**
     * Apply the path filter
     *
     * @param array<array<string, mixed>> $items GitHub API response items
     * @return array<array<string, mixed>> Filtered items
     */
    public function apply(array $items): array
    {
        if (empty($this->pathPattern)) {
            return $items;
        }

        return \array_filter($items, function (array $item): bool {
            $path = $item['path'] ?? '';
            return $this->matchPattern($path, $this->pathPattern);
        });
    }
}
