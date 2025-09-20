<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection;

use Butschster\ContextGenerator\Template\Template;

/**
 * Result of template matching against project metadata
 */
final readonly class TemplateMatchResult
{
    /**
     * @param Template $template The matched template
     * @param float $confidence Confidence score (0.0 to 1.0)
     * @param array<string, array<string>> $matchingCriteria Which criteria matched (files, directories, patterns)
     */
    public function __construct(
        public Template $template,
        public float $confidence,
        public array $matchingCriteria = [],
    ) {}

    /**
     * Check if this match has high confidence (>= 0.8)
     */
    public function hasHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }

    /**
     * Get the number of criteria that matched
     */
    public function getMatchCount(): int
    {
        $count = 0;
        foreach ($this->matchingCriteria as $criteria) {
            $count += \count($criteria);
        }
        return $count;
    }

    /**
     * Check if specific criteria type was matched
     */
    public function hasMatchingCriteria(string $type): bool
    {
        return isset($this->matchingCriteria[$type]) && !empty($this->matchingCriteria[$type]);
    }
}
