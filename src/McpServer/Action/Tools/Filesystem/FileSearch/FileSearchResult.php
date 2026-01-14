<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileSearch;

/**
 * Result DTO containing all matches found in a single file.
 */
final readonly class FileSearchResult
{
    /**
     * @param string $file Relative file path
     * @param bool $success Whether search completed without errors
     * @param SearchMatch[] $matches Array of matches found
     * @param string|null $error Error message if search failed
     * @param bool $truncated Whether results were truncated due to limits
     */
    public function __construct(
        public string $file,
        public bool $success,
        public array $matches = [],
        public ?string $error = null,
        public bool $truncated = false,
    ) {}

    public static function success(string $file, array $matches, bool $truncated = false): self
    {
        return new self(
            file: $file,
            success: true,
            matches: $matches,
            truncated: $truncated,
        );
    }

    public static function error(string $file, string $error): self
    {
        return new self(
            file: $file,
            success: false,
            error: $error,
        );
    }

    public function getMatchCount(): int
    {
        return \count($this->matches);
    }

    /**
     * Format all matches in this file for text output.
     */
    public function format(): string
    {
        if (!$this->success) {
            return \sprintf("=== %s ===\nError: %s", $this->file, $this->error);
        }

        if (empty($this->matches)) {
            return '';
        }

        $maxLineNum = \max(\array_map(fn(SearchMatch $m) => $m->lineNumber, $this->matches));
        $lineNumWidth = \strlen((string) $maxLineNum);

        $parts = [\sprintf('=== %s ===', $this->file)];

        foreach ($this->matches as $match) {
            $parts[] = '';
            $parts[] = \sprintf('[Line %d]', $match->lineNumber);
            $parts[] = $match->format($lineNumWidth);
        }

        if ($this->truncated) {
            $parts[] = '';
            $parts[] = '... (results truncated)';
        }

        return \implode("\n", $parts);
    }
}
