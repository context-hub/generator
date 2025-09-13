<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher;

final readonly class ExactMatcher implements MatcherInterface
{
    public function findMatch(array $contentLines, string $contextMarker, int $maxSearchLines): MatchResult
    {
        // Remove @@ prefix if present
        $searchText = $this->cleanContextMarker($contextMarker);

        $searchLimit = \min(\count($contentLines), $maxSearchLines);

        for ($i = 0; $i < $searchLimit; $i++) {
            if (\str_contains($contentLines[$i], $searchText)) {
                return MatchResult::found($i, 1.0);
            }
        }

        return MatchResult::notFound('Exact match not found');
    }

    public function getConfidenceMultiplier(): float
    {
        return 1.0;
    }

    private function cleanContextMarker(string $contextMarker): string
    {
        // Remove @@ prefix and trim whitespace
        return \trim(\str_replace('@@', '', $contextMarker));
    }
}
