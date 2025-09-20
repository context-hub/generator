<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Analyzer;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\AnalysisResult;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalyzerInterface;
use Butschster\ContextGenerator\Template\Analysis\Util\ComposerFileReader;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;

/**
 * Analyzes generic PHP projects using composer.json
 * Does not handle specific frameworks - those have dedicated analyzers
 */
final readonly class ComposerAnalyzer implements ProjectAnalyzerInterface
{
    public function __construct(
        private ComposerFileReader $composerReader,
        private ProjectStructureDetector $structureDetector,
    ) {}

    public function analyze(FSPath $projectRoot): ?AnalysisResult
    {
        if (!$this->canAnalyze($projectRoot)) {
            return null;
        }

        $composer = $this->composerReader->readComposerFile($projectRoot);

        if ($composer === null) {
            return null;
        }

        $existingDirs = $this->structureDetector->detectExistingDirectories($projectRoot);
        $structureConfidence = $this->structureDetector->calculateStructureConfidence($existingDirs);

        // Base confidence for having composer.json
        $confidence = 0.4;

        // Boost confidence based on directory structure
        $confidence += $structureConfidence * 0.4;

        // Boost confidence if it has a proper package name
        if (isset($composer['name']) && \is_string($composer['name']) && \str_contains($composer['name'], '/')) {
            $confidence += 0.2;
        }

        return new AnalysisResult(
            analyzerName: $this->getName(),
            detectedType: 'generic-php',
            confidence: \min($confidence, 1.0),
            suggestedTemplates: ['generic-php'],
            metadata: [
                'composer' => $composer,
                'existingDirectories' => $existingDirs,
                'packageName' => $composer['name'] ?? null,
                'packages' => $this->composerReader->getAllPackages($composer),
            ],
        );
    }

    public function canAnalyze(FSPath $projectRoot): bool
    {
        return $projectRoot->join('composer.json')->exists();
    }

    public function getPriority(): int
    {
        return 50; // Medium priority - let specific framework analyzers go first
    }

    public function getName(): string
    {
        return 'composer';
    }
}
