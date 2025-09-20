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
     * Add an analyzer to the service
     */
    public function addAnalyzer(ProjectAnalyzerInterface $analyzer): void
    {
        $this->analyzerChain->addAnalyzer($analyzer);
    }

    /**
     * Remove an analyzer from the service
     */
    public function removeAnalyzer(string $analyzerName): void
    {
        $this->analyzerChain->removeAnalyzer($analyzerName);
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

    /**
     * Get the best analysis result (highest confidence)
     */
    public function getBestAnalysis(FSPath $projectRoot): ?AnalysisResult
    {
        $results = $this->analyzeProject($projectRoot);
        return $results[0] ?? null;
    }

    /**
     * Get the first analyzer that can handle the project
     */
    public function getFirstApplicableAnalyzer(FSPath $projectRoot): ?ProjectAnalyzerInterface
    {
        return $this->analyzerChain->getFirstApplicableAnalyzer($projectRoot);
    }

    /**
     * Get all analyzers that can handle the project
     *
     * @return array<ProjectAnalyzerInterface>
     */
    public function getApplicableAnalyzers(FSPath $projectRoot): array
    {
        return $this->analyzerChain->getApplicableAnalyzers($projectRoot);
    }

    /**
     * Get analyzer by name
     */
    public function getAnalyzer(string $name): ?ProjectAnalyzerInterface
    {
        return $this->analyzerChain->getAnalyzer($name);
    }

    /**
     * Get all registered analyzers
     *
     * @return array<ProjectAnalyzerInterface>
     */
    public function getAllAnalyzers(): array
    {
        return $this->analyzerChain->getAllAnalyzers();
    }

    /**
     * Get the analyzer chain for direct access
     */
    public function getAnalyzerChain(): AnalyzerChain
    {
        return $this->analyzerChain;
    }
}
