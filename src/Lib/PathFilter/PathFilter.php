<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Filter GitHub items by path inclusion
 */
final class PathFilter extends AbstractFilter
{
    /**
     * Create a new path filter
     *
     * @param string|array<string> $pathPattern Path pattern(s) to include
     */
    public function __construct(
        private readonly string|array $pathPattern,
    ) {}

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
