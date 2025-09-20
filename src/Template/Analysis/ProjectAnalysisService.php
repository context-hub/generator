<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis;

use Butschster\ContextGenerator\Application\FSPath;

/**
 * Service for orchestrating project analysis
 */
final class ProjectAnalysisService
{
    /** @var array<ProjectAnalyzerInterface> */
    private array $analyzers = [];

    /**
     * @param array<ProjectAnalyzerInterface> $analyzers
     */
    public function __construct(array $analyzers = [])
    {
        foreach ($analyzers as $analyzer) {
            $this->addAnalyzer($analyzer);
        }
    }

    /**
     * Add an analyzer to the service
     */
    public function addAnalyzer(ProjectAnalyzerInterface $analyzer): void
    {
        $this->analyzers[] = $analyzer;

        // Sort by priority (highest first)
        \usort($this->analyzers, static fn($a, $b) => $b->getPriority() <=> $a->getPriority());
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
        $results = [];

        foreach ($this->analyzers as $analyzer) {
            if ($analyzer->canAnalyze($projectRoot)) {
                $result = $analyzer->analyze($projectRoot);
                if ($result !== null) {
                    $results[] = $result;
                }
            }
        }

        // Sort by confidence (highest first)
        \usort($results, static fn($a, $b) => $b->confidence <=> $a->confidence);

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
     * Get the best matching analysis result
     *
     * This method guarantees to always return a result.
     */
    public function getBestMatch(FSPath $projectRoot): AnalysisResult
    {
        $results = $this->analyzeProject($projectRoot);

        return $results[0];
    }

    /**
     * Get all registered analyzers
     *
     * @return array<ProjectAnalyzerInterface>
     */
    public function getAnalyzers(): array
    {
        return $this->analyzers;
    }
}
