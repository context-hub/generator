<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher;

final readonly class WhitespaceTolerantMatcher implements MatcherInterface
{
    public function findMatch(array $contentLines, string $contextMarker, int $maxSearchLines): MatchResult
    {
        $searchText = $this->normalizeWhitespace($this->cleanContextMarker($contextMarker));

        $searchLimit = \min(\count($contentLines), $maxSearchLines);

        for ($i = 0; $i < $searchLimit; $i++) {
            $normalizedLine = $this->normalizeWhitespace($contentLines[$i]);

            if (\str_contains($normalizedLine, $searchText)) {
                return MatchResult::found($i, 0.9);
            }
        }

        return MatchResult::notFound('Whitespace-tolerant match not found');
    }

    public function getConfidenceMultiplier(): float
    {
        return 0.9;
    }

    private function cleanContextMarker(string $contextMarker): string
    {
        return \trim(\str_replace('@@', '', $contextMarker));
    }

    private function normalizeWhitespace(string $text): string
    {
        // Convert multiple whitespace sequences to single spaces
        $normalized = \preg_replace('/\s+/', ' ', $text);

        // Trim leading and trailing whitespace
        return \trim((string) $normalized);
    }
}
