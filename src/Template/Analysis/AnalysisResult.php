<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis;

/**
 * Result of project analysis
 */
final readonly class AnalysisResult
{
    /**
     * @param string $analyzerName Name of the analyzer that produced this result
     * @param string $detectedType Type of project detected (e.g., 'laravel', 'generic-php')
     * @param float $confidence Confidence level (0.0 to 1.0)
     * @param array<string> $suggestedTemplates List of template names that match this analysis
     * @param array<string, mixed> $metadata Additional metadata discovered during analysis
     */
    public function __construct(
        public string $analyzerName,
        public string $detectedType,
        public float $confidence,
        public array $suggestedTemplates = [],
        public array $metadata = [],
    ) {}

    /**
     * Get the primary suggested template (first in the list)
     */
    public function getPrimaryTemplate(): ?string
    {
        return $this->suggestedTemplates[0] ?? null;
    }
}
