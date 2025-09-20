<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalysisService;
use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;

/**
 * Service for detecting the best template for a project
 * Combines template detection criteria with analyzer-based detection
 */
final readonly class TemplateDetectionService
{
    private const float HIGH_CONFIDENCE_THRESHOLD = 0.90;

    public function __construct(
        private TemplateRegistry $templateRegistry,
        private TemplateMatchingService $templateMatchingService,
        private ProjectMetadataExtractor $metadataExtractor,
        private ProjectAnalysisService $analysisService,
    ) {}

    /**
     * Detect the best template for a project
     * Returns the template with the highest confidence score
     * Only uses template detection if confidence > 90%, otherwise falls back to analyzers
     */
    public function detectBestTemplate(FSPath $projectRoot): TemplateDetectionResult
    {
        // Step 1: Extract project metadata for template detection
        $projectMetadata = $this->metadataExtractor->extractMetadata($projectRoot);

        // Step 2: Try template detection criteria first, but only use if very high confidence
        $templateMatches = $this->templateMatchingService->matchTemplates($projectMetadata);

        if (!empty($templateMatches)) {
            // Sort by confidence (highest first)
            \usort($templateMatches, static fn($a, $b) => $b->confidence <=> $a->confidence);
            $bestMatch = $templateMatches[0];

            // Only use template detection if confidence is very high (> 90%)
            if ($bestMatch->confidence > self::HIGH_CONFIDENCE_THRESHOLD) {
                return new TemplateDetectionResult(
                    template: $bestMatch->template,
                    confidence: $bestMatch->confidence,
                    detectionMethod: 'template_criteria',
                    metadata: [
                        'templateMatches' => $templateMatches,
                        'projectMetadata' => $projectMetadata,
                        'reason' => 'High confidence template match',
                    ],
                );
            }
        }

        // Step 3: Fall back to analyzer-based detection
        $analysisResults = $this->analysisService->analyzeProject($projectRoot);
        $bestAnalysis = $analysisResults[0];

        // Try to find a template that matches the detected type
        $suggestedTemplate = null;
        if ($bestAnalysis->getPrimaryTemplate() !== null) {
            $suggestedTemplate = $this->templateRegistry->getTemplate($bestAnalysis->getPrimaryTemplate());
        }

        return new TemplateDetectionResult(
            template: $suggestedTemplate,
            confidence: $bestAnalysis->confidence,
            detectionMethod: 'analyzer',
            metadata: [
                'analysisResults' => $analysisResults,
                'bestAnalysis' => $bestAnalysis,
                'projectMetadata' => $projectMetadata,
                'reason' => empty($templateMatches)
                    ? 'No template matches found'
                    : 'Template matches below high confidence threshold',
                'templateMatchesConsidered' => !empty($templateMatches) ? $templateMatches[0]->confidence : 0.0,
            ],
        );
    }

    /**
     * Get all possible templates for a project with confidence scores
     *
     * @return array<TemplateDetectionResult>
     */
    public function getAllPossibleTemplates(FSPath $projectRoot): array
    {
        $results = [];
        $projectMetadata = $this->metadataExtractor->extractMetadata($projectRoot);

        // Get template-based matches (all of them, regardless of confidence for analysis)
        $templateMatches = $this->templateMatchingService->matchTemplates($projectMetadata);
        foreach ($templateMatches as $match) {
            $results[] = new TemplateDetectionResult(
                template: $match->template,
                confidence: $match->confidence,
                detectionMethod: 'template_criteria',
                metadata: [
                    'projectMetadata' => $projectMetadata,
                    'matchingCriteria' => $match->matchingCriteria,
                    'meetsHighConfidenceThreshold' => $match->confidence > self::HIGH_CONFIDENCE_THRESHOLD,
                ],
            );
        }

        // Get analyzer-based matches
        $analysisResults = $this->analysisService->analyzeProject($projectRoot);
        foreach ($analysisResults as $analysis) {
            if ($analysis->getPrimaryTemplate() !== null) {
                $template = $this->templateRegistry->getTemplate($analysis->getPrimaryTemplate());
                if ($template !== null) {
                    $results[] = new TemplateDetectionResult(
                        template: $template,
                        confidence: $analysis->confidence,
                        detectionMethod: 'analyzer',
                        metadata: [
                            'analysisResult' => $analysis,
                            'analyzerName' => $analysis->analyzerName,
                        ],
                    );
                }
            }
        }

        // Remove duplicates and sort by confidence
        $uniqueResults = [];
        $seenTemplates = [];

        foreach ($results as $result) {
            if ($result->template !== null && !isset($seenTemplates[$result->template->name])) {
                $uniqueResults[] = $result;
                $seenTemplates[$result->template->name] = true;
            }
        }

        \usort($uniqueResults, static fn($a, $b) => $b->confidence <=> $a->confidence);

        return $uniqueResults;
    }

    /**
     * Get the high confidence threshold used for template detection
     */
    public function getHighConfidenceThreshold(): float
    {
        return self::HIGH_CONFIDENCE_THRESHOLD;
    }
}
