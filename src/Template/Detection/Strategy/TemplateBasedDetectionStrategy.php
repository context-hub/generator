<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection\Strategy;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Detection\TemplateDetectionResult;
use Butschster\ContextGenerator\Template\Detection\TemplateMatchingService;

/**
 * Template-based detection strategy using template detection criteria
 */
final readonly class TemplateBasedDetectionStrategy implements TemplateDetectionStrategy
{
    private const float CONFIDENCE_THRESHOLD = 0.7;

    public function __construct(
        private TemplateMatchingService $templateMatchingService,
    ) {}

    public function detect(FSPath $projectRoot, array $projectMetadata): ?TemplateDetectionResult
    {
        $templateMatches = $this->templateMatchingService->matchTemplates($projectMetadata);

        if (empty($templateMatches)) {
            return null;
        }

        // Sort by confidence (highest first)
        \usort($templateMatches, static fn($a, $b) => $b->confidence <=> $a->confidence);

        $bestMatch = $templateMatches[0];

        // Only return result if confidence meets threshold
        if ($bestMatch->confidence < $this->getConfidenceThreshold()) {
            return null;
        }

        return new TemplateDetectionResult(
            template: $bestMatch->template,
            confidence: $bestMatch->confidence,
            detectionMethod: 'template_criteria',
            metadata: [
                'templateMatches' => $templateMatches,
                'matchingCriteria' => $bestMatch->matchingCriteria,
                'projectMetadata' => $projectMetadata,
                'reason' => 'High confidence template match',
            ],
        );
    }

    public function getConfidenceThreshold(): float
    {
        return self::CONFIDENCE_THRESHOLD;
    }

    public function getPriority(): int
    {
        return 100; // High priority - template detection should run first
    }

    public function getName(): string
    {
        return 'template-based';
    }
}
