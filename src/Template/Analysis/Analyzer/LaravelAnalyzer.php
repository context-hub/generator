<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Analyzer;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\AnalysisResult;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalyzerInterface;
use Butschster\ContextGenerator\Template\Analysis\Util\ComposerFileReader;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;

/**
 * Analyzes Laravel PHP projects
 */
final readonly class LaravelAnalyzer implements ProjectAnalyzerInterface
{
    /**
     * Laravel-specific directories that indicate a Laravel project
     */
    private const array LARAVEL_DIRECTORIES = [
        'app',
        'database',
        'routes',
    ];

    /**
     * Laravel-specific files that indicate a Laravel project
     */
    private const array LARAVEL_FILES = [
        'artisan',
    ];

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

        if ($composer === null || !$this->composerReader->hasPackage($composer, 'laravel/framework')) {
            return null;
        }

        $confidence = 0.6; // Base confidence for having laravel/framework

        // Check for Laravel-specific files
        $laravelFilesScore = $this->checkLaravelFiles($projectRoot);
        $confidence += $laravelFilesScore * 0.2;

        // Check for Laravel-specific directories
        $existingDirs = $this->structureDetector->detectExistingDirectories($projectRoot);
        $laravelDirScore = $this->structureDetector->getPatternMatchConfidence(
            $existingDirs,
            self::LARAVEL_DIRECTORIES,
        );
        $confidence += $laravelDirScore * 0.2;

        return new AnalysisResult(
            analyzerName: $this->getName(),
            detectedType: 'laravel',
            confidence: \min($confidence, 1.0),
            suggestedTemplates: ['laravel'],
            metadata: [
                'composer' => $composer,
                'laravelVersion' => $this->composerReader->getPackageVersion($composer, 'laravel/framework'),
                'hasArtisan' => $projectRoot->join('artisan')->exists(),
                'existingDirectories' => $existingDirs,
                'laravelDirectoriesFound' => \array_intersect($existingDirs, self::LARAVEL_DIRECTORIES),
                'laravelFilesScore' => $laravelFilesScore,
                'laravelDirScore' => $laravelDirScore,
            ],
        );
    }

    public function canAnalyze(FSPath $projectRoot): bool
    {
        // Must have composer.json to be a Laravel project
        if (!$projectRoot->join('composer.json')->exists()) {
            return false;
        }

        $composer = $this->composerReader->readComposerFile($projectRoot);

        return $composer !== null && $this->composerReader->hasPackage($composer, 'laravel/framework');
    }

    public function getPriority(): int
    {
        return 100; // High priority - specific framework detection should run first
    }

    public function getName(): string
    {
        return 'laravel';
    }

    /**
     * Check for Laravel-specific files and return confidence score
     */
    private function checkLaravelFiles(FSPath $projectRoot): float
    {
        $found = 0;
        $total = \count(self::LARAVEL_FILES);

        foreach (self::LARAVEL_FILES as $file) {
            if ($projectRoot->join($file)->exists()) {
                $found++;
            }
        }

        return $total > 0 ? $found / $total : 0.0;
    }
}
