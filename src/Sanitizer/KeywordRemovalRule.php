<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Sanitizer;

/**
 * Rule for removing content containing specific keywords
 */
readonly class KeywordRemovalRule implements RuleInterface
{
    /**
     * @param string $name Unique rule name
     * @param array<string> $keywords Keywords to search for
     * @param string $replacement Replacement text (default: '[REMOVED]')
     * @param bool $caseSensitive Whether matching should be case-sensitive
     * @param bool $removeLines Whether to remove entire lines containing the keyword
     */
    public function __construct(
        private string $name,
        private array $keywords,
        private string $replacement = '[REMOVED]',
        private bool $caseSensitive = false,
        private bool $removeLines = true,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function apply(string $content): string
    {
        if (empty($this->keywords)) {
            return $content;
        }

        $lines = \explode(PHP_EOL, $content);
        $result = [];

        foreach ($lines as $line) {
            $matches = false;

            foreach ($this->keywords as $keyword) {
                $matchFunction = $this->caseSensitive ? 'str_contains' : 'stripos';

                if ($matchFunction($line, $keyword) !== false) {
                    $matches = true;
                    break;
                }
            }

            if ($matches) {
                if (!$this->removeLines) {
                    // Replace just the keywords
                    $line = $this->replaceKeywords($line);
                    $result[] = $line;
                } else {
                    // If removeLines is true, we skip this line entirely
                    $result[] = $this->replacement;
                }
            } else {
                $result[] = $line;
            }
        }

        return \implode(PHP_EOL, $result);
    }

    /**
     * Replace keywords in a line
     */
    private function replaceKeywords(string $line): string
    {
        foreach ($this->keywords as $keyword) {
            if ($this->caseSensitive) {
                $line = \str_replace($keyword, $this->replacement, $line);
            } else {
                $line = \preg_replace('/' . \preg_quote($keyword, '/') . '/i', $this->replacement, (string) $line);
            }
        }

        return $line;
    }
}
