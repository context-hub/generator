<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher;

final readonly class UnicodeMatcher implements MatcherInterface
{
    public function findMatch(array $contentLines, string $contextMarker, int $maxSearchLines): MatchResult
    {
        $searchText = $this->normalizeUnicode($this->cleanContextMarker($contextMarker));

        $searchLimit = \min(\count($contentLines), $maxSearchLines);

        for ($i = 0; $i < $searchLimit; $i++) {
            $normalizedLine = $this->normalizeUnicode($contentLines[$i]);

            if (\str_contains($normalizedLine, $searchText)) {
                return MatchResult::found($i, 0.8);
            }
        }

        return MatchResult::notFound('Unicode-normalized match not found');
    }

    public function getConfidenceMultiplier(): float
    {
        return 0.8;
    }

    private function cleanContextMarker(string $contextMarker): string
    {
        return \trim(\str_replace('@@', '', $contextMarker));
    }

    private function normalizeUnicode(string $text): string
    {
        // Normalize whitespace first
        $normalized = \preg_replace('/\s+/', ' ', \trim($text));

        // Convert smart quotes to ASCII quotes
        //$normalized = \str_replace(['"', '"', ''', '''], ['"', '"', "'", "'"], $normalized);

        // Convert em/en dashes to hyphens
        $normalized = \str_replace(['—', '–'], '-', $normalized);

        // Normalize line endings to LF
        $normalized = \str_replace(["\r\n", "\r"], "\n", $normalized);

        // Normalize Unicode combining characters if mbstring is available
        if (\function_exists('mb_convert_encoding')) {
            $normalized = \mb_convert_encoding($normalized, 'UTF-8', 'UTF-8');
        }

        return $normalized;
    }
}
