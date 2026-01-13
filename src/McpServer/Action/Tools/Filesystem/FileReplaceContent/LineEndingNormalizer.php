<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent;

/**
 * Utility class for detecting and normalizing line endings.
 */
final readonly class LineEndingNormalizer
{
    /**
     * Detect the predominant line ending style in content.
     *
     * Detection priority:
     * 1. CRLF (\r\n) - checked first as most specific
     * 2. LF (\n) - Unix/macOS standard
     * 3. CR (\r) - Legacy Mac
     * 4. Default to LF if no line endings found
     */
    public function detect(string $content): LineEnding
    {
        // Check for CRLF first (most specific - must check before LF)
        if (\str_contains($content, "\r\n")) {
            return LineEnding::Crlf;
        }

        // Check for standalone LF
        if (\str_contains($content, "\n")) {
            return LineEnding::Lf;
        }

        // Check for standalone CR (legacy Mac)
        if (\str_contains($content, "\r")) {
            return LineEnding::Cr;
        }

        // Default to LF if no line endings found
        return LineEnding::Lf;
    }

    /**
     * Normalize all line endings in content to the target style.
     */
    public function normalize(string $content, LineEnding $target): string
    {
        // First normalize everything to LF
        $normalized = \str_replace("\r\n", "\n", $content);
        $normalized = \str_replace("\r", "\n", $normalized);

        // Then convert to target if not LF
        if ($target !== LineEnding::Lf) {
            $normalized = \str_replace("\n", $target->value, $normalized);
        }

        return $normalized;
    }
}
