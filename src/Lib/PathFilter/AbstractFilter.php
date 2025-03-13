<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

/**
 * Abstract base class for GitHub content filters
 */
abstract class AbstractFilter implements FilterInterface
{
    /**
     * Filter name
     */
    protected string $name;

    /**
     * Create a new filter
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the filter name
     */
    public function getName(): string
    {
        return $this->name;
    }

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

        $patterns = \is_array($pattern) ? $pattern : [$pattern];

        foreach ($patterns as $p) {
            if (!FileHelper::isRegex($pattern)) {
                $p = FileHelper::toRegex($p);
            }

            if ($this->matchGlob($value, $p)) {
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
        return (bool) \preg_match($regex, $value);
    }
}
