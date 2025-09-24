<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Storage\FileStorage;

use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Files\FilesInterface;
use Symfony\Component\Finder\Finder;

/**
 * Scans and manages research directory structures using Symfony Finder
 */
final readonly class DirectoryScanner
{
    public function __construct(
        private FilesInterface $files,
        private ExceptionReporterInterface $reporter,
    ) {}

    /**
     * Scan research root directory
     *
     * @return array Array of research directory paths
     */
    public function scanResearches(string $path): array
    {
        if (!$this->files->isDirectory($path)) {
            return [];
        }

        $researches = [];

        try {
            $finder = new Finder();
            $finder
                ->directories()
                ->in($path)
                ->depth(0) // Only immediate subdirectories
                ->filter(static function (\SplFileInfo $file): bool {
                    // Check if this directory contains a research.yaml file
                    $configPath = $file->getRealPath() . '/research.yaml';
                    return \file_exists($configPath);
                });

            foreach ($finder as $directory) {
                $researches[] = $directory->getRealPath();
            }
        } catch (\Throwable $e) {
            $this->reporter->report($e);
            // Handle cases where directory is not accessible
            // Return empty array - calling code can handle this gracefully
        }

        return $researches;
    }

    /**
     * Scan research directory for entry files
     *
     * @param string $path Path to research directory
     * @return array Array of entry file paths
     */
    public function scanEntries(string $path): array
    {
        if (!$this->files->exists($path) || !$this->files->isDirectory($path)) {
            return [];
        }

        $entryFiles = [];

        try {
            $finder = new Finder();
            $finder
                ->files()
                ->in($path)
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
     * Get all subdirectories in research that could contain entries
     */
    public function getEntryDirectories(string $path): array
    {
        if (!$this->files->exists($path) || !$this->files->isDirectory($path)) {
            return [];
        }

        $directories = [];

        try {
            $finder = new Finder();
            $finder
                ->directories()
                ->in($path)
                ->depth(0) // Only immediate subdirectories
                ->filter(static function (\SplFileInfo $file): bool {
                    // Skip special directories
                    $name = $file->getFilename();
                    return !\in_array($name, ['.research', 'resources', '.git', '.idea', 'node_modules'], true);
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
