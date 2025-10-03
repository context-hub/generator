<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Sanitizer\Rule;

/**
 * Rule for replacing content using regular expressions
 */
final readonly class RegexReplacementRule implements RuleInterface
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
            /** @var string $content */
            $content = \preg_replace(pattern: $pattern, replacement: $replacement, subject: $content);
        }

        return $content;
    }
}
