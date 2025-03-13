<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Sanitizer;

/**
 * Rule for replacing content using regular expressions
 */
readonly class RegexReplacementRule implements RuleInterface
{
    /**
     * @param string $name Unique rule name
     * @param array<string, string> $patterns Array of regex patterns and their replacements
     */
    public function __construct(
        private string $name,
        private array $patterns,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function apply(string $content): string
    {
        foreach ($this->patterns as $pattern => $replacement) {
            $content = \preg_replace($pattern, $replacement, (string) $content);
        }

        return $content;
    }
}
