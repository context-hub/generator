<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Analysis\Analyzer;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\AnalysisResult;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalyzerInterface;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;
use Spiral\Files\FilesInterface;

/**
 * Analyzes Python projects using requirements.txt, pyproject.toml, setup.py
 */
final readonly class PythonAnalyzer implements ProjectAnalyzerInterface
{
    /**
     * Framework detection patterns in Python dependencies
     */
    private const array FRAMEWORK_PATTERNS = [
        'django' => ['Django', 'django'],
        'flask' => ['Flask', 'flask'],
        'fastapi' => ['fastapi', 'FastAPI'],
        'pyramid' => ['pyramid'],
        'tornado' => ['tornado'],
        'bottle' => ['bottle'],
        'cherrypy' => ['CherryPy', 'cherrypy'],
        'falcon' => ['falcon'],
        'sanic' => ['sanic'],
        'quart' => ['quart'],
        'starlette' => ['starlette'],
    ];

    /**
     * Python project indicator files
     */
    private const array PYTHON_FILES = [
        'requirements.txt',
        'pyproject.toml',
        'setup.py',
        'setup.cfg',
        'Pipfile',
        'poetry.lock',
        'conda.yml',
        'environment.yml',
        'manage.py', // Django
        'app.py',    // Common Flask pattern
        'main.py',   // Common FastAPI pattern
        'wsgi.py',
        'asgi.py',
    ];

    /**
     * Python project directories
     */
    private const array PYTHON_DIRECTORIES = [
        'src',
        'lib',
        'app',
        'apps',
        'project',
        'tests',
        'test',
        'static',
        'templates',
        'migrations',
        'venv',
        'env',
        '.venv',
        '__pycache__',
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

        $dependencies = $this->extractDependencies($projectRoot);
        $detectedFramework = $this->detectFramework($dependencies);
        $existingDirs = $this->structureDetector->detectExistingDirectories($projectRoot);

        $detectedType = $detectedFramework ?? 'python';
        $confidence = $this->calculateConfidence($projectRoot, $dependencies, $detectedFramework, $existingDirs);

        $suggestedTemplates = $detectedFramework ? [$detectedFramework] : ['python'];

        return new AnalysisResult(
            analyzerName: $this->getName(),
            detectedType: $detectedType,
            confidence: $confidence,
            suggestedTemplates: $suggestedTemplates,
            metadata: [
                'dependencies' => $dependencies,
                'detectedFramework' => $detectedFramework,
                'existingDirectories' => $existingDirs,
                'pythonFiles' => $this->getDetectedFiles($projectRoot),
                'packageManagers' => $this->detectPackageManagers($projectRoot),
            ],
        );
    }

    public function canAnalyze(FSPath $projectRoot): bool
    {
        // Check for any Python indicator files
        foreach (self::PYTHON_FILES as $file) {
            if ($projectRoot->join($file)->exists()) {
                return true;
            }
        }

        // Check for .py files in common directories
        foreach (['src', 'app', '.'] as $dir) {
            $dirPath = $projectRoot->join($dir);
            if ($dirPath->exists() && $this->hasPythonFiles($dirPath)) {
                return true;
            }
        }

        return false;
    }

    public function getPriority(): int
    {
        return 75; // High priority for Python framework detection
    }

    public function getName(): string
    {
        return 'python';
    }

    /**
     * Extract dependencies from various Python dependency files
     */
    private function extractDependencies(FSPath $projectRoot): array
    {
        $dependencies = [];

        // Parse requirements.txt
        $requirementsFile = $projectRoot->join('requirements.txt');
        if ($requirementsFile->exists()) {
            $dependencies = \array_merge($dependencies, $this->parseRequirementsTxt($requirementsFile));
        }

        // Parse pyproject.toml
        $pyprojectFile = $projectRoot->join('pyproject.toml');
        if ($pyprojectFile->exists()) {
            $dependencies = \array_merge($dependencies, $this->parsePyprojectToml($pyprojectFile));
        }

        // Parse setup.py (basic extraction)
        $setupFile = $projectRoot->join('setup.py');
        if ($setupFile->exists()) {
            $dependencies = \array_merge($dependencies, $this->parseSetupPy($setupFile));
        }

        // Parse Pipfile
        $pipfile = $projectRoot->join('Pipfile');
        if ($pipfile->exists()) {
            $dependencies = \array_merge($dependencies, $this->parsePipfile($pipfile));
        }

        return \array_unique($dependencies);
    }

    /**
     * Parse requirements.txt file
     */
    private function parseRequirementsTxt(FSPath $requirementsFile): array
    {
        $content = $this->files->read($requirementsFile->toString());
        if ($content === '') {
            return [];
        }

        $dependencies = [];
        $lines = \explode("\n", $content);

        foreach ($lines as $line) {
            $line = \trim($line);

            // Skip comments and empty lines
            if ($line === '' || \str_starts_with($line, '#')) {
                continue;
            }

            // Extract package name (everything before version specifiers)
            if (\preg_match('/^([a-zA-Z0-9_-]+)/', $line, $matches)) {
                $dependencies[] = $matches[1];
            }
        }

        return $dependencies;
    }

    /**
     * Parse pyproject.toml file for dependencies
     */
    private function parsePyprojectToml(FSPath $pyprojectFile): array
    {
        $content = $this->files->read($pyprojectFile->toString());
        if ($content === '') {
            return [];
        }

        $dependencies = [];

        // Basic TOML parsing for dependencies section
        if (\preg_match('/\[tool\.poetry\.dependencies\](.*?)(?=\[|$)/s', $content, $matches)) {
            $dependenciesSection = $matches[1];
            if (\preg_match_all('/^([a-zA-Z0-9_-]+)\s*=/m', $dependenciesSection, $matches)) {
                $dependencies = \array_merge($dependencies, $matches[1]);
            }
        }

        // Also check for PEP 621 format
        if (\preg_match('/\[project\](.*?)(?=\[|$)/s', $content, $matches)) {
            $projectSection = $matches[1];
            if (\preg_match('/dependencies\s*=\s*\[(.*?)\]/s', $projectSection, $depMatches)) {
                $depList = $depMatches[1];
                if (\preg_match_all('/"([a-zA-Z0-9_-]+)/', $depList, $matches)) {
                    $dependencies = \array_merge($dependencies, $matches[1]);
                }
            }
        }

        return $dependencies;
    }

    /**
     * Basic parsing of setup.py for install_requires
     */
    private function parseSetupPy(FSPath $setupFile): array
    {
        $content = $this->files->read($setupFile->toString());
        if ($content === '') {
            return [];
        }

        $dependencies = [];

        // Look for install_requires list
        if (\preg_match('/install_requires\s*=\s*\[(.*?)\]/s', $content, $matches)) {
            $requiresList = $matches[1];
            if (\preg_match_all('/"([a-zA-Z0-9_-]+)/', $requiresList, $matches)) {
                $dependencies = $matches[1];
            } elseif (\preg_match_all("/'([a-zA-Z0-9_-]+)/", $requiresList, $matches)) {
                $dependencies = $matches[1];
            }
        }

        return $dependencies;
    }

    /**
     * Basic parsing of Pipfile
     */
    private function parsePipfile(FSPath $pipfile): array
    {
        $content = $this->files->read($pipfile->toString());
        if ($content === '') {
            return [];
        }

        $dependencies = [];

        // Parse [packages] section
        if (\preg_match('/\[packages\](.*?)(?=\[|$)/s', $content, $matches)) {
            $packagesSection = $matches[1];
            if (\preg_match_all('/^([a-zA-Z0-9_-]+)\s*=/m', $packagesSection, $matches)) {
                $dependencies = \array_merge($dependencies, $matches[1]);
            }
        }

        return $dependencies;
    }

    /**
     * Detect Python framework from dependencies
     */
    private function detectFramework(array $dependencies): ?string
    {
        foreach (self::FRAMEWORK_PATTERNS as $framework => $patterns) {
            foreach ($patterns as $pattern) {
                if (\in_array($pattern, $dependencies, true)) {
                    return $framework;
                }
            }
        }

        return null;
    }

    /**
     * Calculate confidence score for Python project detection
     */
    private function calculateConfidence(
        FSPath $projectRoot,
        array $dependencies,
        ?string $detectedFramework,
        array $existingDirs,
    ): float {
        $confidence = 0.4; // Base confidence for having Python indicators

        // Boost confidence if we detected a framework
        if ($detectedFramework !== null) {
            $confidence += 0.3;
        }

        // Boost confidence for having dependencies
        if (!empty($dependencies)) {
            $confidence += 0.2;
        }

        // Boost confidence based on directory structure
        $pythonDirScore = $this->structureDetector->getPatternMatchConfidence(
            $existingDirs,
            self::PYTHON_DIRECTORIES,
        );
        $confidence += $pythonDirScore * 0.1;

        // Boost confidence if we have manage.py (Django indicator)
        if ($projectRoot->join('manage.py')->exists()) {
            $confidence += 0.1;
        }

        return \min($confidence, 1.0);
    }

    /**
     * Get detected Python files
     */
    private function getDetectedFiles(FSPath $projectRoot): array
    {
        $detected = [];
        foreach (self::PYTHON_FILES as $file) {
            if ($projectRoot->join($file)->exists()) {
                $detected[] = $file;
            }
        }
        return $detected;
    }

    /**
     * Detect which package managers are in use
     */
    private function detectPackageManagers(FSPath $projectRoot): array
    {
        $managers = [];

        if ($projectRoot->join('requirements.txt')->exists()) {
            $managers[] = 'pip';
        }
        if ($projectRoot->join('pyproject.toml')->exists()) {
            $managers[] = 'poetry';
        }
        if ($projectRoot->join('Pipfile')->exists()) {
            $managers[] = 'pipenv';
        }
        if ($projectRoot->join('conda.yml')->exists() || $projectRoot->join('environment.yml')->exists()) {
            $managers[] = 'conda';
        }

        return $managers;
    }

    /**
     * Check if directory contains Python files
     */
    private function hasPythonFiles(FSPath $directory): bool
    {
        if (!$this->files->isDirectory($directory->toString())) {
            return false;
        }

        $files = $this->files->getFiles($directory->toString(), '*.py');
        return !empty($files);
    }
}
