<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher;

interface MatcherInterface
{
    /**
     * Find the best matching position for the given context in the content.
     *
     * @param string[] $contentLines The file content as array of lines
     * @param string $contextMarker The context marker to find
     * @param int $maxSearchLines Maximum number of lines to search
     * @return MatchResult The match result with position and confidence
     */
    public function findMatch(array $contentLines, string $contextMarker, int $maxSearchLines): MatchResult;

    /**
     * Get the confidence score for this matcher type.
     * Higher scores indicate more precise matching.
     */
    public function getConfidenceMultiplier(): float;
}
