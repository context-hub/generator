<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\FileStorage;

use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Files\FilesInterface;
use Symfony\Component\Finder\Finder;

/**
 * Scans and manages project directory structures using Symfony Finder
 */
final readonly class DirectoryScanner
{
    public function __construct(
        private FilesInterface $files,
        private ExceptionReporterInterface $reporter,
    ) {}

    /**
     * Scan project root directory for project subdirectories
     *
     * @return array Array of project directory paths
     */
    public function scanProjects(string $projectsPath): array
    {
        if (!$this->files->isDirectory($projectsPath)) {
            return [];
        }

        $projects = [];

        try {
            $finder = new Finder();
            $finder
                ->directories()
                ->in($projectsPath)
                ->depth(0) // Only immediate subdirectories
                ->filter(static function (\SplFileInfo $file): bool {
                    // Check if this directory contains a project.yaml file
                    $configPath = $file->getRealPath() . '/project.yaml';
                    return \file_exists($configPath);
                });

            foreach ($finder as $directory) {
                $projects[] = $directory->getRealPath();
            }
        } catch (\Throwable $e) {
            $this->reporter->report($e);
            // Handle cases where directory is not accessible
            // Return empty array - calling code can handle this gracefully
        }

        return $projects;
    }

    /**
     * Scan project directory for entry files
     *
     * @param string $projectPath Path to project directory
     * @param array $entryDirs Entry directories to scan (relative to project)
     * @return array Array of entry file paths
     */
    public function scanEntries(string $projectPath, array $entryDirs = []): array
    {
        if (!$this->files->exists($projectPath) || !$this->files->isDirectory($projectPath)) {
            return [];
        }

        $entryFiles = [];

        try {
            $finder = new Finder();
            $finder
                ->files()
                ->in($projectPath)
                ->name('*.md');

            foreach ($finder as $file) {
                $entryFiles[] = $file->getRealPath();
            }
        } catch (\Throwable) {
            // Handle cases where directories are not accessible
            // Return empty array - calling code can handle this gracefully
        }

        return $entryFiles;
    }

    /**
     * Get all subdirectories in project that could contain entries
     */
    public function getEntryDirectories(string $projectPath): array
    {
        if (!$this->files->exists($projectPath) || !$this->files->isDirectory($projectPath)) {
            return [];
        }

        $directories = [];

        try {
            $finder = new Finder();
            $finder
                ->directories()
                ->in($projectPath)
                ->depth(0) // Only immediate subdirectories
                ->filter(static function (\SplFileInfo $file): bool {
                    // Skip special directories
                    $name = $file->getFilename();
                    return !\in_array($name, ['.project', 'resources', '.git', '.idea', 'node_modules'], true);
                });

            foreach ($finder as $directory) {
                $directories[] = $directory->getFilename(); // Return relative directory name
            }
        } catch (\Throwable) {
            // Handle cases where directory is not accessible
            // Return empty array
        }

        return $directories;
    }

    /**
     * Create project directory structure
     */
    public function createProjectDirectory(string $projectPath, array $entryDirs = []): void
    {
        // Create main project directory
        $this->files->ensureDirectory($projectPath);

        // Create .project subdirectory for metadata
        $this->files->ensureDirectory($projectPath . '/.project');

        // Create entry directories
        foreach ($entryDirs as $dir) {
            $dirPath = $this->files->normalizePath($projectPath . '/' . $dir);
            $this->files->ensureDirectory($dirPath);
        }
    }

    /**
     * Validate project directory structure
     */
    public function validateProjectStructure(string $projectPath): array
    {
        $errors = [];

        if (!$this->files->exists($projectPath)) {
            $errors[] = "Project directory does not exist: {$projectPath}";
            return $errors;
        }

        if (!$this->files->isDirectory($projectPath)) {
            $errors[] = "Project path is not a directory: {$projectPath}";
            return $errors;
        }

        // Check for project configuration file
        $configPath = $projectPath . '/project.yaml';
        if (!$this->files->exists($configPath)) {
            $errors[] = "Missing project configuration file: {$configPath}";
        }

        // Check for .project metadata directory
        $metadataDir = $projectPath . '/.project';
        if (!$this->files->exists($metadataDir)) {
            $errors[] = "Missing project metadata directory: {$metadataDir}";
        }

        return $errors;
    }

    /**
     * Get project statistics
     */
    public function getProjectStats(string $projectPath): array
    {
        $stats = [
            'total_files' => 0,
            'entry_files' => 0,
            'directories' => 0,
            'size_bytes' => 0,
            'last_modified' => null,
        ];

        if (!$this->files->exists($projectPath) || !$this->files->isDirectory($projectPath)) {
            return $stats;
        }

        try {
            // Count all files
            $fileFinder = new Finder();
            $fileFinder
                ->files()
                ->in($projectPath);

            foreach ($fileFinder as $file) {
                $stats['total_files']++;

                // Check if it's an entry file
                if ($file->getExtension() === 'md') {
                    $stats['entry_files']++;
                }

                // Add to size
                $stats['size_bytes'] += $file->getSize();

                // Update last modified
                $modified = $file->getMTime();
                if ($stats['last_modified'] === null || $modified > $stats['last_modified']) {
                    $stats['last_modified'] = $modified;
                }
            }

            // Count directories
            $dirFinder = new Finder();
            $dirFinder
                ->directories()
                ->in($projectPath);

            foreach ($dirFinder as $_) {
                $stats['directories']++;
            }
        } catch (\Throwable) {
            // Return basic stats if scanning fails
        }

        return $stats;
    }

    /**
     * Find specific files by pattern
     *
     * @param string $path Base path to search in
     * @param string $pattern File pattern (e.g., "*.yaml", "project.*")
     * @param int $depth Maximum depth to search (0 = only immediate directory)
     * @return array Array of file paths
     */
    public function findFiles(string $path, string $pattern, int $depth = 0): array
    {
        if (!$this->files->exists($path) || !$this->files->isDirectory($path)) {
            return [];
        }

        $files = [];

        try {
            $finder = new Finder();
            $finder
                ->files()
                ->in($path)
                ->name($pattern)
                ->depth($depth);

            foreach ($finder as $file) {
                $files[] = $file->getRealPath();
            }
        } catch (\Throwable) {
            // Return empty array if scanning fails
        }

        return $files;
    }

    /**
     * Check if directory contains any markdown files
     */
    public function hasMarkdownFiles(string $path): bool
    {
        if (!$this->files->exists($path) || !$this->files->isDirectory($path)) {
            return false;
        }

        try {
            $finder = new Finder();
            $finder
                ->files()
                ->in($path)
                ->name('*.md')
                ->depth(0);

            return $finder->hasResults();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get directory size recursively
     */
    public function getDirectorySize(string $path): int
    {
        if (!$this->files->exists($path) || !$this->files->isDirectory($path)) {
            return 0;
        }

        $totalSize = 0;

        try {
            $finder = new Finder();
            $finder
                ->files()
                ->in($path);

            foreach ($finder as $file) {
                $totalSize += $file->getSize();
            }
        } catch (\Throwable) {
            // Return 0 if calculation fails
        }

        return $totalSize;
    }
}
