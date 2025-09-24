<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis;

use Butschster\ContextGenerator\Application\FSPath;

/**
 * Improved service for orchestrating project analysis using analyzer chain
 */
final readonly class ProjectAnalysisService
{
    private AnalyzerChain $analyzerChain;

    /**
     * @param array<ProjectAnalyzerInterface> $analyzers
     */
    public function __construct(array $analyzers = [])
    {
        $this->analyzerChain = new AnalyzerChain($analyzers);
    }

    /**
     * Analyze a project and return analysis results
     *
     * This method guarantees to always return at least one result.
     * If no specific analyzers match, the fallback analyzer will provide a default result.
     *
     * @return array<AnalysisResult>
     */
    public function analyzeProject(FSPath $projectRoot): array
    {
        $results = $this->analyzerChain->analyze($projectRoot);

        // This should never happen if FallbackAnalyzer is registered,
        // but add safety check just in case
        if (empty($results)) {
            throw new \RuntimeException(
                'No analysis results returned. Ensure FallbackAnalyzer is registered.',
            );
        }

        return $results;
    }
}
