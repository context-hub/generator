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
}
