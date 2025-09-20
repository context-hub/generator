<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection\Strategy;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalysisService;
use Butschster\ContextGenerator\Template\Detection\TemplateDetectionResult;
use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;

/**
 * Analyzer-based detection strategy using project analysis
 */
final readonly class AnalyzerBasedDetectionStrategy implements TemplateDetectionStrategy
{
    private const float CONFIDENCE_THRESHOLD = 0.3;

    public function __construct(
        private ProjectAnalysisService $analysisService,
        private TemplateRegistry $templateRegistry,
    ) {}

    public function detect(FSPath $projectRoot, array $projectMetadata): ?TemplateDetectionResult
    {
        $analysisResults = $this->analysisService->analyzeProject($projectRoot);

        if (empty($analysisResults)) {
            return null;
        }

        $bestAnalysis = $analysisResults[0];

        // Find corresponding template
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
                'analyzerName' => $bestAnalysis->analyzerName,
                'projectMetadata' => $projectMetadata,
                'reason' => 'Analyzer-based project detection',
            ],
        );
    }

    public function getConfidenceThreshold(): float
    {
        return self::CONFIDENCE_THRESHOLD;
    }

    public function getPriority(): int
    {
        return 50; // Medium priority - runs after template detection
    }

    public function getName(): string
    {
        return 'analyzer-based';
    }
}
