<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Analyzer;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\AnalysisResult;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalyzerInterface;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;
use Spiral\Files\FilesInterface;

/**
 * Analyzes JavaScript/Node.js projects using package.json
 */
final readonly class PackageJsonAnalyzer implements ProjectAnalyzerInterface
{
    /**
     * Framework detection patterns in package.json dependencies
     */
    private const array FRAMEWORK_PATTERNS = [
        'react' => ['react', 'react-dom'],
        'vue' => ['vue'],
        'next' => ['next'],
        'nuxt' => ['nuxt', '@nuxt/kit'],
        'express' => ['express'],
        'angular' => ['@angular/core'],
        'svelte' => ['svelte'],
        'gatsby' => ['gatsby'],
    ];

    public function __construct(
        private FilesInterface $files,
        private ProjectStructureDetector $structureDetector,
    ) {}

    public function analyze(FSPath $projectRoot): ?AnalysisResult
    {
        if (!$this->canAnalyze($projectRoot)) {
            return null;
        }

        $packageJson = $this->readPackageJson($projectRoot);

        if ($packageJson === null) {
            return null;
        }

        $detectedFramework = $this->detectFramework($packageJson);
        $existingDirs = $this->structureDetector->detectExistingDirectories($projectRoot);

        if ($detectedFramework === null) {
            // Generic Node.js project
            return new AnalysisResult(
                analyzerName: $this->getName(),
                detectedType: 'node',
                confidence: 0.6,
                suggestedTemplates: ['node'],
                metadata: [
                    'packageJson' => $packageJson,
                    'existingDirectories' => $existingDirs,
                    'packageName' => $packageJson['name'] ?? null,
                    'scripts' => $packageJson['scripts'] ?? [],
                ],
            );
        }

        $confidence = $this->calculateFrameworkConfidence($packageJson, $detectedFramework);

        return new AnalysisResult(
            analyzerName: $this->getName(),
            detectedType: $detectedFramework,
            confidence: $confidence,
            suggestedTemplates: [$detectedFramework],
            metadata: [
                'packageJson' => $packageJson,
                'detectedFramework' => $detectedFramework,
                'existingDirectories' => $existingDirs,
                'packageName' => $packageJson['name'] ?? null,
                'scripts' => $packageJson['scripts'] ?? [],
                'dependencies' => $this->getAllDependencies($packageJson),
            ],
        );
    }

    public function canAnalyze(FSPath $projectRoot): bool
    {
        return $projectRoot->join('package.json')->exists();
    }

    public function getPriority(): int
    {
        return 80; // High priority for JavaScript framework detection
    }

    public function getName(): string
    {
        return 'package-json';
    }

    /**
     * Read and parse package.json file
     */
    private function readPackageJson(FSPath $projectRoot): ?array
    {
        $packagePath = $projectRoot->join('package.json');

        if (!$packagePath->exists()) {
            return null;
        }

        $content = $this->files->read($packagePath->toString());

        if ($content === '') {
            return null;
        }

        $decoded = \json_decode($content, true);

        if (!\is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Detect the framework based on package.json dependencies
     */
    private function detectFramework(array $packageJson): ?string
    {
        $allDependencies = $this->getAllDependencies($packageJson);

        // Check for framework-specific packages
        foreach (self::FRAMEWORK_PATTERNS as $framework => $patterns) {
            foreach ($patterns as $pattern) {
                if (\array_key_exists($pattern, $allDependencies)) {
                    return $framework;
                }
            }
        }

        return null;
    }

    /**
     * Calculate confidence score for detected framework
     */
    private function calculateFrameworkConfidence(array $packageJson, string $framework): float
    {
        $confidence = 0.7; // Base confidence for framework detection

        // Boost confidence if multiple framework packages are present
        $frameworkPatterns = self::FRAMEWORK_PATTERNS[$framework] ?? [];
        $allDependencies = $this->getAllDependencies($packageJson);

        $matchCount = 0;
        foreach ($frameworkPatterns as $pattern) {
            if (\array_key_exists($pattern, $allDependencies)) {
                $matchCount++;
            }
        }

        if ($matchCount > 1) {
            $confidence += 0.2;
        }

        // Boost confidence if there are relevant scripts
        $scripts = $packageJson['scripts'] ?? [];
        if ($this->hasRelevantScripts($scripts, $framework)) {
            $confidence += 0.1;
        }

        return \min($confidence, 1.0);
    }

    /**
     * Check if scripts are relevant to the detected framework
     */
    private function hasRelevantScripts(array $scripts, string $framework): bool
    {
        $relevantScripts = match ($framework) {
            'react' => ['start', 'build', 'test'],
            'vue' => ['serve', 'build', 'test'],
            'next' => ['dev', 'build', 'start'],
            'nuxt' => ['dev', 'build', 'generate'],
            'express' => ['start', 'dev'],
            'angular' => ['ng', 'start', 'build'],
            default => ['start', 'build'],
        };

        foreach ($relevantScripts as $script) {
            if (isset($scripts[$script])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all dependencies from package.json
     */
    private function getAllDependencies(array $packageJson): array
    {
        $dependencies = [];

        if (isset($packageJson['dependencies'])) {
            $dependencies = \array_merge($dependencies, $packageJson['dependencies']);
        }

        if (isset($packageJson['devDependencies'])) {
            $dependencies = \array_merge($dependencies, $packageJson['devDependencies']);
        }

        if (isset($packageJson['peerDependencies'])) {
            $dependencies = \array_merge($dependencies, $packageJson['peerDependencies']);
        }

        return $dependencies;
    }
}
