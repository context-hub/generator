<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection\Strategy;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Detection\TemplateDetectionResult;

/**
 * Composite detection strategy that combines multiple detection approaches
 * Applies strategies in priority order and returns the best result
 */
final class CompositeDetectionStrategy implements TemplateDetectionStrategy
{
    /** @var array<TemplateDetectionStrategy> */
    private array $strategies = [];

    /**
     * @param array<TemplateDetectionStrategy> $strategies
     */
    public function __construct(array $strategies = [])
    {
        foreach ($strategies as $strategy) {
            $this->addStrategy($strategy);
        }
    }

    /**
     * Add a detection strategy
     */
    public function addStrategy(TemplateDetectionStrategy $strategy): void
    {
        $this->strategies[] = $strategy;
        $this->sortStrategiesByPriority();
    }

    /**
     * Remove a strategy by name
     */
    public function removeStrategy(string $strategyName): void
    {
        $this->strategies = \array_filter(
            $this->strategies,
            static fn($strategy) => $strategy->getName() !== $strategyName,
        );

        $this->strategies = \array_values($this->strategies); // Re-index
    }

    public function detect(FSPath $projectRoot, array $projectMetadata): ?TemplateDetectionResult
    {
        foreach ($this->strategies as $strategy) {
            $result = $strategy->detect($projectRoot, $projectMetadata);

            if ($result !== null && $result->confidence >= $strategy->getConfidenceThreshold()) {
                // Add strategy information to metadata
                $metadata = $result->metadata;
                $metadata['selectedStrategy'] = $strategy->getName();
                $metadata['strategyPriority'] = $strategy->getPriority();

                return new TemplateDetectionResult(
                    template: $result->template,
                    confidence: $result->confidence,
                    detectionMethod: $result->detectionMethod,
                    metadata: $metadata,
                );
            }
        }

        return null;
    }

    /**
     * Get all possible results from all strategies
     *
     * @return array<TemplateDetectionResult>
     */
    public function getAllPossibleResults(FSPath $projectRoot, array $projectMetadata): array
    {
        $results = [];

        foreach ($this->strategies as $strategy) {
            $result = $strategy->detect($projectRoot, $projectMetadata);

            if ($result !== null) {
                // Add strategy information to metadata
                $metadata = $result->metadata;
                $metadata['strategy'] = $strategy->getName();
                $metadata['strategyPriority'] = $strategy->getPriority();
                $metadata['meetsThreshold'] = $result->confidence >= $strategy->getConfidenceThreshold();

                $results[] = new TemplateDetectionResult(
                    template: $result->template,
                    confidence: $result->confidence,
                    detectionMethod: $result->detectionMethod,
                    metadata: $metadata,
                );
            }
        }

        // Sort by confidence (highest first)
        \usort($results, static fn($a, $b) => $b->confidence <=> $a->confidence);

        return $results;
    }

    public function getConfidenceThreshold(): float
    {
        // Return the minimum threshold among all strategies
        if (empty($this->strategies)) {
            return 0.0;
        }

        return \min(\array_map(static fn($strategy) => $strategy->getConfidenceThreshold(), $this->strategies));
    }

    public function getPriority(): int
    {
        // Composite strategy has highest priority as it orchestrates others
        return 1000;
    }

    public function getName(): string
    {
        return 'composite';
    }

    /**
     * Get all registered strategies
     *
     * @return array<TemplateDetectionStrategy>
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * Sort strategies by priority (highest first)
     */
    private function sortStrategiesByPriority(): void
    {
        \usort($this->strategies, static fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }
}
