<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Import;

use Butschster\ContextGenerator\FilesInterface;
use RecursiveIteratorIterator;
use Psr\Log\LoggerInterface;

/**
 * Find files matching a glob pattern in the filesystem
 */
final readonly class WildcardPathFinder
{
    /**
     * File extensions recognized as configuration files
     */
    private const CONFIG_EXTENSIONS = [
        'yaml' => true,
        'yml' => true,
        'json' => true,
        'php' => true,
    ];

    public function __construct(
        private FilesInterface $files,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Find files matching the given glob pattern
     *
     * @param string $pattern The glob pattern (can contain * and **)
     * @param string $basePath The base path for resolving relative patterns
     * @return array<string> List of absolute file paths that match the pattern
     */
    public function findMatchingPaths(string $pattern, string $basePath): array
    {
        $this->logger?->debug('Finding paths matching pattern', [
            'pattern' => $pattern,
            'basePath' => $basePath,
        ]);

        // If the pattern doesn't contain wildcards, just check if the file exists
        if (!PathMatcher::containsWildcard($pattern)) {
            $path = $this->resolvePath($pattern, $basePath);
            return $this->files->exists($path) ? [$path] : [];
        }

        // Create pattern matcher
        $matcher = new PathMatcher($pattern);

        // Determine the base directory to start scanning from
        $baseDir = $this->getBaseDirectoryFromPattern($pattern, $basePath);

        // If base directory doesn't exist, no files can match
        if (!\is_dir($baseDir)) {
            $this->logger?->debug('Base directory does not exist', ['baseDir' => $baseDir]);
            return [];
        }

        $matches = [];
        $this->scanDirectory($baseDir, $matcher, $matches);

        $this->logger?->debug('Found matching paths', ['count' => \count($matches), 'paths' => $matches]);

        return $matches;
    }

    /**
     * Recursively scan directory and find files matching the pattern
     */
    private function scanDirectory(string $directory, PathMatcher $matcher, array &$matches): void
    {
        // Use RecursiveIteratorIterator to recursively scan the directory
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS,
            ),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $path => $file) {
            if ($file->isFile() && $this->isConfigFile($file->getFilename())) {
                $relativePath = $this->getRelativePath($path, $directory);
                $fullPattern = $this->resolvePath($matcher->getPattern(), $directory);

                // For pattern matching, normalize directory separators
                $normalizedPath = \str_replace('\\', '/', $relativePath);
                $normalizedPattern = \str_replace('\\', '/', $fullPattern);

                if ($matcher->isMatch($normalizedPath)) {
                    $matches[] = $path;
                }
            }
        }
    }

    /**
     * Get the base directory to start scanning from based on the pattern
     */
    private function getBaseDirectoryFromPattern(string $pattern, string $basePath): string
    {
        // Find the position of the first wildcard
        $firstWildcard = \strcspn($pattern, '*?[{');

        // Get the part of the pattern before the first wildcard
        $fixedPrefix = \substr($pattern, 0, $firstWildcard);

        // Get the directory part of the fixed prefix
        $baseDir = $fixedPrefix;
        if (\str_contains($fixedPrefix, '/')) {
            $baseDir = \dirname($fixedPrefix);
        }

        // If the base directory is empty, use the base path
        if ($baseDir === '' || $baseDir === '.') {
            return $basePath;
        }

        // Resolve the base directory against the base path
        return $this->resolvePath($baseDir, $basePath);
    }

    /**
     * Get the path relative to a base directory
     */
    private function getRelativePath(string $path, string $baseDir): string
    {
        $baseDirLength = \strlen($baseDir);
        if (\strncmp($path, $baseDir, $baseDirLength) === 0) {
            return \substr($path, $baseDirLength + 1);
        }
        return $path;
    }

    /**
     * Check if a file is a recognized configuration file
     */
    private function isConfigFile(string $filename): bool
    {
        $extension = \strtolower(\pathinfo($filename, PATHINFO_EXTENSION));
        return isset(self::CONFIG_EXTENSIONS[$extension]);
    }

    /**
     * Resolve a relative path to an absolute path
     */
    private function resolvePath(string $path, string $basePath): string
    {
        // If it's an absolute path, use it directly
        if (\str_starts_with($path, '/')) {
            return $path;
        }

        // Otherwise, resolve it relative to the base path
        return \rtrim($basePath, '/') . '/' . $path;
    }
}
