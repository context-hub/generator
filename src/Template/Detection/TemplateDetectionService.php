<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Detection\Strategy\CompositeDetectionStrategy;
use Butschster\ContextGenerator\Template\Detection\Strategy\TemplateDetectionStrategy;

/**
 * Improved template detection service using strategy pattern
 * Delegates detection to configurable strategies
 */
final readonly class TemplateDetectionService
{
    public function __construct(
        private CompositeDetectionStrategy $detectionStrategy,
        private ProjectMetadataExtractor $metadataExtractor,
    ) {}

    /**
     * Detect the best template for a project using composite strategy
     */
    public function detectBestTemplate(FSPath $projectRoot): TemplateDetectionResult
    {
        $projectMetadata = $this->metadataExtractor->extractMetadata($projectRoot);

        $result = $this->detectionStrategy->detect($projectRoot, $projectMetadata);

        // If no template was detected, return empty result
        if ($result === null) {
            return new TemplateDetectionResult(
                template: null,
                confidence: 0.0,
                detectionMethod: 'none',
                metadata: [
                    'projectMetadata' => $projectMetadata,
                    'reason' => 'No templates matched detection criteria',
                ],
            );
        }

        return $result;
    }

    /**
     * Get all possible templates for a project with confidence scores
     *
     * @return array<TemplateDetectionResult>
     */
    public function getAllPossibleTemplates(FSPath $projectRoot): array
    {
        $projectMetadata = $this->metadataExtractor->extractMetadata($projectRoot);
        return $this->detectionStrategy->getAllPossibleResults($projectRoot, $projectMetadata);
    }

    /**
     * Get the high confidence threshold used for template detection
     */
    public function getHighConfidenceThreshold(): float
    {
        return 0.90; // This could be made configurable in the future
    }

    /**
     * Get all registered strategies
     *
     * @return array<TemplateDetectionStrategy>
     */
    public function getStrategies(): array
    {
        return $this->detectionStrategy->getStrategies();
    }
}
