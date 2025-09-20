<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Analyzer;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\AnalysisResult;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalyzerInterface;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;
use Spiral\Files\FilesInterface;

/**
 * Analyzes Go projects using go.mod, go.sum, and Go project structure
 */
final readonly class GoAnalyzer implements ProjectAnalyzerInterface
{
    /**
     * Framework/library detection patterns in Go dependencies
     */
    private const array FRAMEWORK_PATTERNS = [
        'gin' => ['github.com/gin-gonic/gin'],
        'echo' => ['github.com/labstack/echo'],
        'fiber' => ['github.com/gofiber/fiber'],
        'chi' => ['github.com/go-chi/chi'],
        'mux' => ['github.com/gorilla/mux'],
        'beego' => ['github.com/beego/beego'],
        'iris' => ['github.com/kataras/iris'],
        'buffalo' => ['github.com/gobuffalo/buffalo'],
        'revel' => ['github.com/revel/revel'],
        'fasthttp' => ['github.com/valyala/fasthttp'],
        'grpc' => ['google.golang.org/grpc'],
        'cobra' => ['github.com/spf13/cobra'], // CLI framework
    ];

    /**
     * Go project indicator files
     */
    private const array GO_FILES = [
        'go.mod',
        'go.sum',
        'go.work',
        'main.go',
        'cmd',
        'Makefile',
        'Dockerfile',
        '.gitignore',
    ];

    /**
     * Go project directories
     */
    private const array GO_DIRECTORIES = [
        'cmd',
        'pkg',
        'internal',
        'api',
        'web',
        'configs',
        'scripts',
        'build',
        'deployments',
        'test',
        'tests',
        'docs',
        'tools',
        'vendor',
        'bin',
        'assets',
        'static',
        'templates',
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

        $goModData = $this->parseGoMod($projectRoot);
        $dependencies = $goModData['dependencies'] ?? [];
        $detectedFramework = $this->detectFramework($dependencies);
        $existingDirs = $this->structureDetector->detectExistingDirectories($projectRoot);
        $projectType = $this->determineProjectType($projectRoot, $dependencies);

        $detectedType = $detectedFramework ?? $projectType;
        $confidence = $this->calculateConfidence($projectRoot, $goModData, $detectedFramework, $existingDirs);

        $suggestedTemplates = $detectedFramework ? [$detectedFramework] : ['go'];

        return new AnalysisResult(
            analyzerName: $this->getName(),
            detectedType: $detectedType,
            confidence: $confidence,
            suggestedTemplates: $suggestedTemplates,
            metadata: [
                'goMod' => $goModData,
                'dependencies' => $dependencies,
                'detectedFramework' => $detectedFramework,
                'projectType' => $projectType,
                'existingDirectories' => $existingDirs,
                'goFiles' => $this->getDetectedFiles($projectRoot),
                'goVersion' => $goModData['goVersion'] ?? null,
                'moduleName' => $goModData['module'] ?? null,
            ],
        );
    }

    public function canAnalyze(FSPath $projectRoot): bool
    {
        // Primary indicator: go.mod file
        if ($projectRoot->join('go.mod')->exists()) {
            return true;
        }

        // Secondary indicators: Go files in expected locations
        if ($projectRoot->join('main.go')->exists()) {
            return true;
        }

        // Check cmd directory for Go files
        $cmdDir = $projectRoot->join('cmd');
        if ($cmdDir->exists() && $this->hasGoFiles($cmdDir)) {
            return true;
        }

        return false;
    }

    public function getPriority(): int
    {
        return 80; // High priority for Go framework detection
    }

    public function getName(): string
    {
        return 'go';
    }

    /**
     * Parse go.mod file for module info and dependencies
     */
    private function parseGoMod(FSPath $projectRoot): array
    {
        $goModFile = $projectRoot->join('go.mod');

        if (!$goModFile->exists()) {
            return [];
        }

        $content = $this->files->read($goModFile->toString());
        if ($content === '') {
            return [];
        }

        $data = [
            'module' => null,
            'goVersion' => null,
            'dependencies' => [],
        ];

        $lines = \explode("\n", $content);
        $inRequireBlock = false;

        foreach ($lines as $line) {
            $line = \trim($line);

            // Skip empty lines and comments
            if ($line === '' || \str_starts_with($line, '//')) {
                continue;
            }

            // Parse module name
            if (\preg_match('/^module\s+(.+)$/', $line, $matches)) {
                $data['module'] = \trim($matches[1]);
                continue;
            }

            // Parse go version
            if (\preg_match('/^go\s+(.+)$/', $line, $matches)) {
                $data['goVersion'] = \trim($matches[1]);
                continue;
            }

            // Handle require block
            if (\str_starts_with($line, 'require (')) {
                $inRequireBlock = true;
                continue;
            }

            if ($inRequireBlock && $line === ')') {
                $inRequireBlock = false;
                continue;
            }

            // Parse single require line
            if (\str_starts_with($line, 'require ') && !\str_contains($line, '(')) {
                if (\preg_match('/^require\s+([^\s]+)/', $line, $matches)) {
                    $data['dependencies'][] = $matches[1];
                }
                continue;
            }

            // Parse require block content
            if ($inRequireBlock) {
                if (\preg_match('/^\s*([^\s]+)/', $line, $matches)) {
                    $data['dependencies'][] = $matches[1];
                }
            }
        }

        return $data;
    }

    /**
     * Detect Go framework from dependencies
     */
    private function detectFramework(array $dependencies): ?string
    {
        foreach (self::FRAMEWORK_PATTERNS as $framework => $patterns) {
            foreach ($patterns as $pattern) {
                foreach ($dependencies as $dependency) {
                    if (\str_starts_with((string) $dependency, $pattern)) {
                        return $framework;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Determine project type based on structure and dependencies
     */
    private function determineProjectType(FSPath $projectRoot, array $dependencies): string
    {
        // Check for CLI patterns
        if ($projectRoot->join('cmd')->exists() || $this->hasCliDependencies($dependencies)) {
            return 'go-cli';
        }

        // Check for web service patterns
        if ($this->hasWebDependencies($dependencies)) {
            return 'go-web';
        }

        // Check for gRPC patterns
        if ($this->hasGrpcDependencies($dependencies)) {
            return 'go-grpc';
        }

        // Default Go project
        return 'go';
    }

    /**
     * Calculate confidence score for Go project detection
     */
    private function calculateConfidence(
        FSPath $projectRoot,
        array $goModData,
        ?string $detectedFramework,
        array $existingDirs,
    ): float {
        $confidence = 0.6; // Base confidence for having Go indicators

        // High confidence boost for go.mod file
        if ($projectRoot->join('go.mod')->exists()) {
            $confidence += 0.2;
        }

        // Boost confidence if we detected a framework
        if ($detectedFramework !== null) {
            $confidence += 0.15;
        }

        // Boost confidence for having dependencies
        if (!empty($goModData['dependencies'] ?? [])) {
            $confidence += 0.1;
        }

        // Boost confidence based on directory structure
        $goDirScore = $this->structureDetector->getPatternMatchConfidence(
            $existingDirs,
            self::GO_DIRECTORIES,
        );
        $confidence += $goDirScore * 0.05;

        return \min($confidence, 1.0);
    }

    /**
     * Get detected Go files
     */
    private function getDetectedFiles(FSPath $projectRoot): array
    {
        $detected = [];
        foreach (self::GO_FILES as $file) {
            if ($projectRoot->join($file)->exists()) {
                $detected[] = $file;
            }
        }
        return $detected;
    }

    /**
     * Check if directory contains Go files
     */
    private function hasGoFiles(FSPath $directory): bool
    {
        if (!$this->files->isDirectory($directory->toString())) {
            return false;
        }

        $files = $this->files->getFiles($directory->toString(), '*.go');
        return !empty($files);
    }

    /**
     * Check for CLI framework dependencies
     */
    private function hasCliDependencies(array $dependencies): bool
    {
        $cliPatterns = [
            'github.com/spf13/cobra',
            'github.com/urfave/cli',
            'github.com/alecthomas/kingpin',
            'github.com/jessevdk/go-flags',
        ];

        foreach ($dependencies as $dependency) {
            foreach ($cliPatterns as $pattern) {
                if (\str_starts_with((string) $dependency, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check for web framework dependencies
     */
    private function hasWebDependencies(array $dependencies): bool
    {
        $webPatterns = [
            'github.com/gin-gonic/gin',
            'github.com/labstack/echo',
            'github.com/gofiber/fiber',
            'github.com/go-chi/chi',
            'github.com/gorilla/mux',
            'net/http', // Standard library
        ];

        foreach ($dependencies as $dependency) {
            foreach ($webPatterns as $pattern) {
                if (\str_starts_with((string) $dependency, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check for gRPC dependencies
     */
    private function hasGrpcDependencies(array $dependencies): bool
    {
        $grpcPatterns = [
            'google.golang.org/grpc',
            'google.golang.org/protobuf',
            'github.com/grpc-ecosystem',
        ];

        foreach ($dependencies as $dependency) {
            foreach ($grpcPatterns as $pattern) {
                if (\str_starts_with((string) $dependency, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }
}
