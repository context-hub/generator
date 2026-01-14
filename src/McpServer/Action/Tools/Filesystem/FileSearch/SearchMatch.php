<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileSearch;

/**
 * Value object representing a single search match with surrounding context.
 */
final readonly class SearchMatch
{
    /**
     * @param string $file Relative file path
     * @param int $lineNumber Line number where match was found (1-based)
     * @param string $line The matched line content
     * @param string[] $contextBefore Lines before the match
     * @param string[] $contextAfter Lines after the match
     * @param int $contextStartLine Line number of first context line (1-based)
     */
    public function __construct(
        public string $file,
        public int $lineNumber,
        public string $line,
        public array $contextBefore = [],
        public array $contextAfter = [],
        public int $contextStartLine = 1,
    ) {}

    /**
     * Format this match for text output with line numbers.
     */
    public function format(int $maxLineNumWidth = 4): string
    {
        $output = [];
        $currentLine = $this->contextStartLine;

        // Context before
        foreach ($this->contextBefore as $ctxLine) {
            $output[] = \sprintf(
                '  %s | %s',
                \str_pad((string) $currentLine, $maxLineNumWidth, ' ', STR_PAD_LEFT),
                $ctxLine,
            );
            $currentLine++;
        }

        // Matched line (highlighted with >)
        $output[] = \sprintf(
            '> %s | %s',
            \str_pad((string) $this->lineNumber, $maxLineNumWidth, ' ', STR_PAD_LEFT),
            $this->line,
        );
        $currentLine++;

        // Context after
        foreach ($this->contextAfter as $ctxLine) {
            $output[] = \sprintf(
                '  %s | %s',
                \str_pad((string) $currentLine, $maxLineNumWidth, ' ', STR_PAD_LEFT),
                $ctxLine,
            );
            $currentLine++;
        }

        return \implode("\n", $output);
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'lineNumber' => $this->lineNumber,
            'line' => $this->line,
            'contextBefore' => $this->contextBefore,
            'contextAfter' => $this->contextAfter,
        ];
    }
}
