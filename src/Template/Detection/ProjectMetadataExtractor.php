<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Detection;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Analysis\Util\ComposerFileReader;
use Spiral\Files\FilesInterface;

/**
 * Extracts project metadata for template detection
 */
final readonly class ProjectMetadataExtractor
{
    /**
     * Common project files to detect
     */
    private const array PROJECT_FILES = [
        'composer.json',
        'package.json',
        'artisan',
        'yii',
        'bin/console',
        'symfony.lock',
        'requirements.php',
        'next.config.js',
        'nuxt.config.js',
        'vue.config.js',
        'vite.config.js',
        '.rr.yaml',
        '.rr.yml',
    ];

    /**
     * Common project directories to detect
     */
    private const array PROJECT_DIRECTORIES = [
        'src',
        'app',
        'lib',
        'tests',
        'config',
        'public',
        'web',
        'database',
        'routes',
        'controllers',
        'models',
        'views',
        'templates',
        'components',
        'pages',
        'layouts',
        'plugins',
        'middleware',
        'assets',
        'static',
        'resources',
        'styles',
        'bin',
    ];

    public function __construct(
        private FilesInterface $files,
        private ComposerFileReader $composerReader,
    ) {}

    /**
     * Extract comprehensive project metadata for template detection
     *
     * @return array<string, mixed>
     */
    public function extractMetadata(FSPath $projectRoot): array
    {
        return [
            'files' => $this->detectFiles($projectRoot),
            'directories' => $this->detectDirectories($projectRoot),
            'composer' => $this->extractComposerMetadata($projectRoot),
            'packageJson' => $this->extractPackageJsonMetadata($projectRoot),
        ];
    }

    /**
     * Detect which project files exist
     *
     * @return array<string>
     */
    private function detectFiles(FSPath $projectRoot): array
    {
        $existingFiles = [];

        foreach (self::PROJECT_FILES as $file) {
            if ($projectRoot->join($file)->exists()) {
                $existingFiles[] = $file;
            }
        }

        return $existingFiles;
    }

    /**
     * Detect which project directories exist
     *
     * @return array<string>
     */
    private function detectDirectories(FSPath $projectRoot): array
    {
        $existingDirectories = [];

        foreach (self::PROJECT_DIRECTORIES as $directory) {
            if ($projectRoot->join($directory)->exists() && $this->files->isDirectory($projectRoot->join($directory)->toString())) {
                $existingDirectories[] = $directory;
            }
        }

        return $existingDirectories;
    }

    /**
     * Extract composer.json metadata
     *
     * @return array<string, mixed>
     */
    private function extractComposerMetadata(FSPath $projectRoot): array
    {
        $composer = $this->composerReader->readComposerFile($projectRoot);

        if ($composer === null) {
            return [];
        }

        return [
            'raw' => $composer,
            'packages' => $this->composerReader->getAllPackages($composer),
            'name' => $composer['name'] ?? null,
            'type' => $composer['type'] ?? null,
        ];
    }

    /**
     * Extract package.json metadata
     *
     * @return array<string, mixed>
     */
    private function extractPackageJsonMetadata(FSPath $projectRoot): array
    {
        $packagePath = $projectRoot->join('package.json');

        if (!$packagePath->exists()) {
            return [];
        }

        $content = $this->files->read($packagePath->toString());
        if ($content === '') {
            return [];
        }

        $decoded = \json_decode($content, true);
        if (!\is_array($decoded)) {
            return [];
        }

        return [
            'raw' => $decoded,
            'dependencies' => $this->getAllDependencies($decoded),
            'name' => $decoded['name'] ?? null,
            'scripts' => $decoded['scripts'] ?? [],
        ];
    }

    /**
     * Get all dependencies from package.json
     *
     * @return array<string, string>
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
