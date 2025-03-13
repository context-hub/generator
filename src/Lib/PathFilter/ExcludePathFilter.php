<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Filter GitHub items by path exclusion
 */
class ExcludePathFilter extends AbstractFilter
{
    /**
     * Path patterns to exclude
     *
     * @var array<string>
     */
    private array $excludePatterns;

    /**
     * Create a new exclude path filter
     *
     * @param array<string> $excludePatterns Path pattern(s) to exclude
     */
    public function __construct(array $excludePatterns)
    {
        parent::__construct('exclude_path');
        $this->excludePatterns = $excludePatterns;
    }

    /**
     * Apply the exclude path filter
     *
     * @param array<array<string, mixed>> $items GitHub API response items
     * @return array<array<string, mixed>> Filtered items
     */
    public function apply(array $items): array
    {
        if (empty($this->excludePatterns)) {
            return $items;
        }

        return \array_filter($items, function (array $item): bool {
            $path = $item['path'] ?? '';

            foreach ($this->excludePatterns as $pattern) {
                if (!FileHelper::isRegex($pattern)) {
                    $p = FileHelper::toRegex($pattern);
                }

                if ($this->matchGlob($path, $pattern)) {
                    return false;
                }
            }

            return true;
        });
    }
}
