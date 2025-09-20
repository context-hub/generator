<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis;

use Butschster\ContextGenerator\Application\FSPath;

/**
 * Interface for project analyzers
 */
interface ProjectAnalyzerInterface
{
    /**
     * Analyze a project directory and return analysis results
     */
    public function analyze(FSPath $projectRoot): ?AnalysisResult;

    /**
     * Check if this analyzer can analyze the given project
     */
    public function canAnalyze(FSPath $projectRoot): bool;

    /**
     * Get the priority of this analyzer (higher = runs first)
     */
    public function getPriority(): int;

    /**
     * Get the name of this analyzer
     */
    public function getName(): string;
}
