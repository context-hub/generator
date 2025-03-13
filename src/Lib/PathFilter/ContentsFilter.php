<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

use Symfony\Component\Finder\SplFileInfo;

/**
 * Filter items based on their content
 */
final class ContentsFilter extends AbstractFilter
{
    public function __construct(
        private readonly string|array|null $contains = null,
        private readonly string|array|null $notContains = null,
    ) {}

    public function apply(array $items): array
    {
        if (empty($this->contains) && empty($this->notContains)) {
            return $items;
        }

        /** @psalm-suppress InvalidArgument */
        return \array_filter($items, function (SplFileInfo $item): bool {
            if ($item->isDir()) {
                return true;
            }

            try {
                // Fetch content (this would need to be implemented based on your source type)
                $content = $item->getContents();

                // Check "contains" patterns
                if (!empty($this->contains) && !$this->contentContains($content, $this->contains)) {
                    return false;
                }

                // Check "notContains" patterns
                if (!empty($this->notContains) && $this->contentContains($content, $this->notContains)) {
                    return false;
                }

                return true;
            } catch (\Throwable) {
                // Log the error or handle it as needed
                return false;
            }
        });
    }

    /**
     * Check if content contains any of the specified patterns
     *
     * @param string $content The content to check
     * @param string|array<string> $patterns Pattern(s) to look for
     * @return bool Whether any pattern was found
     */
    private function contentContains(string $content, string|array $patterns): bool
    {
        $patternArray = \is_array($patterns) ? $patterns : [$patterns];

        foreach ($patternArray as $pattern) {
            if (\str_contains($content, $pattern)) {
                return true;
            }

            // Also check if it's a regex pattern
            if (FileHelper::isRegex($pattern)) {
                if (\preg_match($pattern, $content)) {
                    return true;
                }
            }
        }

        return false;
    }
}
