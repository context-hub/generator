<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis;

use Butschster\ContextGenerator\Application\FSPath;

/**
 * Chain of responsibility implementation for project analysis
 * Manages analyzer execution order and provides consistent results
 */
final class AnalyzerChain
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
     * Add an analyzer to the chain, maintaining priority order
     */
    public function addAnalyzer(ProjectAnalyzerInterface $analyzer): void
    {
        $this->analyzers[] = $analyzer;
        $this->sortByPriority();
    }

    /**
     * Remove an analyzer from the chain
     */
    public function removeAnalyzer(string $analyzerName): void
    {
        $this->analyzers = \array_filter(
            $this->analyzers,
            static fn($analyzer) => $analyzer->getName() !== $analyzerName,
        );

        $this->analyzers = \array_values($this->analyzers); // Re-index
    }

    /**
     * Execute the chain and collect all results
     *
     * @return array<AnalysisResult>
     */
    public function analyze(FSPath $projectRoot): array
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

        // Sort results by confidence (highest first)
        \usort($results, static fn($a, $b) => $b->confidence <=> $a->confidence);

        return $results;
    }

    /**
     * Get the first analyzer that can handle the project
     */
    public function getFirstApplicableAnalyzer(FSPath $projectRoot): ?ProjectAnalyzerInterface
    {
        foreach ($this->analyzers as $analyzer) {
            if ($analyzer->canAnalyze($projectRoot)) {
                return $analyzer;
            }
        }

        return null;
    }

    /**
     * Get all analyzers that can handle the project
     *
     * @return array<ProjectAnalyzerInterface>
     */
    public function getApplicableAnalyzers(FSPath $projectRoot): array
    {
        return \array_filter(
            $this->analyzers,
            static fn($analyzer) => $analyzer->canAnalyze($projectRoot),
        );
    }

    /**
     * Get analyzer by name
     */
    public function getAnalyzer(string $name): ?ProjectAnalyzerInterface
    {
        foreach ($this->analyzers as $analyzer) {
            if ($analyzer->getName() === $name) {
                return $analyzer;
            }
        }

        return null;
    }

    /**
     * Get all registered analyzers
     *
     * @return array<ProjectAnalyzerInterface>
     */
    public function getAllAnalyzers(): array
    {
        return $this->analyzers;
    }

    /**
     * Check if chain has any analyzers
     */
    public function isEmpty(): bool
    {
        return empty($this->analyzers);
    }

    /**
     * Get count of analyzers in chain
     */
    public function count(): int
    {
        return \count($this->analyzers);
    }

    /**
     * Sort analyzers by priority (highest first)
     */
    private function sortByPriority(): void
    {
        \usort($this->analyzers, static fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }
}
