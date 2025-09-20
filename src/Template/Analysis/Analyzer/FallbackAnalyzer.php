<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Analyzer;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\AnalysisResult;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalyzerInterface;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;

/**
 * Fallback analyzer that provides a default configuration for any project
 * This analyzer always succeeds with low confidence as a safety net
 */
final readonly class FallbackAnalyzer implements ProjectAnalyzerInterface
{
    public function __construct(
        private ProjectStructureDetector $structureDetector,
    ) {}

    #[\Override]
    public function analyze(FSPath $projectRoot): ?AnalysisResult
    {
        // This analyzer always provides a result as a fallback
        $existingDirs = $this->structureDetector->detectExistingDirectories($projectRoot);
        $confidence = $this->structureDetector->calculateStructureConfidence($existingDirs);

        // Determine the best generic template based on what we found
        $suggestedTemplate = $this->determineBestTemplate($existingDirs);

        return new AnalysisResult(
            analyzerName: $this->getName(),
            detectedType: 'generic',
            confidence: $confidence,
            suggestedTemplates: [$suggestedTemplate],
            metadata: [
                'existingDirectories' => $existingDirs,
                'isFallback' => true,
                'directoryCount' => \count($existingDirs),
            ],
        );
    }

    #[\Override]
    public function canAnalyze(FSPath $projectRoot): bool
    {
        // This analyzer can always analyze any project as a fallback
        return true;
    }

    #[\Override]
    public function getPriority(): int
    {
        return 1; // Lowest priority - only used when no other analyzers match
    }

    #[\Override]
    public function getName(): string
    {
        return 'fallback';
    }

    /**
     * Determine the best generic template based on existing directories
     */
    private function determineBestTemplate(array $existingDirs): string
    {
        // If we have src or app directories, assume it's a PHP project
        if (\in_array('src', $existingDirs, true) || \in_array('app', $existingDirs, true)) {
            return 'generic-php';
        }

        // If we have lib directory, might be a library project
        if (\in_array('lib', $existingDirs, true)) {
            return 'generic-php';
        }

        // Default fallback
        return 'generic-php';
    }
}
