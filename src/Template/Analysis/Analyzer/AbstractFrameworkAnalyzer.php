<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Analyzer;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\AnalysisResult;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalyzerInterface;
use Butschster\ContextGenerator\Template\Analysis\Util\ComposerFileReader;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;

/**
 * Abstract base class for framework-specific analyzers
 * Provides common functionality for detecting PHP and JavaScript frameworks
 */
abstract class AbstractFrameworkAnalyzer implements ProjectAnalyzerInterface
{
    protected ?ProjectAnalyzerInterface $nextAnalyzer = null;

    public function __construct(
        protected readonly ComposerFileReader $composerReader,
        protected readonly ProjectStructureDetector $structureDetector,
    ) {}

    /**
     * Set the next analyzer in the chain
     */
    public function setNext(?ProjectAnalyzerInterface $analyzer): void
    {
        $this->nextAnalyzer = $analyzer;
    }

    /**
     * Get the next analyzer in the chain
     */
    public function getNext(): ?ProjectAnalyzerInterface
    {
        return $this->nextAnalyzer;
    }

    public function analyze(FSPath $projectRoot): ?AnalysisResult
    {
        if (!$this->canAnalyze($projectRoot)) {
            return null;
        }

        $composer = $this->composerReader->readComposerFile($projectRoot);

        if ($composer === null || !$this->hasFrameworkPackages($composer)) {
            return null;
        }

        $confidence = $this->calculateConfidence($projectRoot, $composer);
        $metadata = $this->buildMetadata($projectRoot, $composer);

        return new AnalysisResult(
            analyzerName: $this->getName(),
            detectedType: $this->getFrameworkType(),
            confidence: \min($confidence, 1.0),
            suggestedTemplates: [$this->getFrameworkType()],
            metadata: $metadata,
        );
    }

    public function canAnalyze(FSPath $projectRoot): bool
    {
        // Must have composer.json to be a PHP framework
        if (!$projectRoot->join('composer.json')->exists()) {
            return false;
        }

        $composer = $this->composerReader->readComposerFile($projectRoot);
        return $composer !== null && $this->hasFrameworkPackages($composer);
    }

    /**
     * Get framework-specific packages to look for
     *
     * @return array<string>
     */
    abstract protected function getFrameworkPackages(): array;

    /**
     * Get framework-specific directories that indicate this framework
     *
     * @return array<string>
     */
    abstract protected function getFrameworkDirectories(): array;

    /**
     * Get framework-specific files that indicate this framework
     *
     * @return array<string>
     */
    abstract protected function getFrameworkFiles(): array;

    /**
     * Get the base confidence score for having framework packages
     */
    protected function getBaseConfidence(): float
    {
        return 0.6;
    }

    /**
     * Get the weight for directory structure matching
     */
    protected function getDirectoryWeight(): float
    {
        return 0.2;
    }

    /**
     * Get the weight for file matching
     */
    protected function getFileWeight(): float
    {
        return 0.2;
    }

    /**
     * Get the framework type identifier (usually same as getName())
     */
    protected function getFrameworkType(): string
    {
        return $this->getName();
    }

    /**
     * Check if composer.json contains framework-specific packages
     */
    protected function hasFrameworkPackages(array $composer): bool
    {
        foreach ($this->getFrameworkPackages() as $package) {
            if ($this->composerReader->hasPackage($composer, $package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate confidence score based on framework indicators
     */
    protected function calculateConfidence(FSPath $projectRoot, array $composer): float
    {
        $confidence = $this->getBaseConfidence();

        // Check for framework-specific files
        $fileScore = $this->checkFrameworkFiles($projectRoot);
        $confidence += $fileScore * $this->getFileWeight();

        // Check for framework-specific directories
        $existingDirs = $this->structureDetector->detectExistingDirectories($projectRoot);
        $directoryScore = $this->structureDetector->getPatternMatchConfidence(
            $existingDirs,
            $this->getFrameworkDirectories(),
        );
        $confidence += $directoryScore * $this->getDirectoryWeight();

        // Allow subclasses to add custom confidence calculations
        $confidence += $this->getAdditionalConfidence($projectRoot, $composer, $existingDirs);

        return $confidence;
    }

    /**
     * Check for framework-specific files and return confidence score
     */
    protected function checkFrameworkFiles(FSPath $projectRoot): float
    {
        $frameworkFiles = $this->getFrameworkFiles();

        if (empty($frameworkFiles)) {
            return 0.0;
        }

        $found = 0;
        foreach ($frameworkFiles as $file) {
            if ($projectRoot->join($file)->exists()) {
                $found++;
            }
        }

        return $found / \count($frameworkFiles);
    }

    /**
     * Allow subclasses to add framework-specific confidence calculations
     */
    protected function getAdditionalConfidence(
        FSPath $projectRoot,
        array $composer,
        array $existingDirectories,
    ): float {
        return 0.0;
    }

    /**
     * Build metadata for the analysis result
     */
    protected function buildMetadata(FSPath $projectRoot, array $composer): array
    {
        $existingDirs = $this->structureDetector->detectExistingDirectories($projectRoot);

        return [
            'composer' => $composer,
            'frameworkPackages' => $this->getDetectedPackages($composer),
            'existingDirectories' => $existingDirs,
            'frameworkDirectoriesFound' => \array_intersect($existingDirs, $this->getFrameworkDirectories()),
            'frameworkFilesFound' => $this->getDetectedFiles($projectRoot),
            'directoryScore' => $this->structureDetector->getPatternMatchConfidence(
                $existingDirs,
                $this->getFrameworkDirectories(),
            ),
            'fileScore' => $this->checkFrameworkFiles($projectRoot),
        ];
    }

    /**
     * Get detected framework packages from composer.json
     */
    protected function getDetectedPackages(array $composer): array
    {
        $detected = [];
        foreach ($this->getFrameworkPackages() as $package) {
            if ($this->composerReader->hasPackage($composer, $package)) {
                $detected[$package] = $this->composerReader->getPackageVersion($composer, $package);
            }
        }
        return $detected;
    }

    /**
     * Get detected framework files
     */
    protected function getDetectedFiles(FSPath $projectRoot): array
    {
        $detected = [];
        foreach ($this->getFrameworkFiles() as $file) {
            if ($projectRoot->join($file)->exists()) {
                $detected[] = $file;
            }
        }
        return $detected;
    }
}
