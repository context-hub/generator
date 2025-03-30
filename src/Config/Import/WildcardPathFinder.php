<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;
use Symfony\Component\Finder\Finder;

/**
 * Find files matching a glob pattern in the filesystem using Symfony Finder
 */
final readonly class WildcardPathFinder
{
    /**
     * File extensions recognized as configuration files
     */
    private const array CONFIG_EXTENSIONS = ['yaml', 'yml', 'json', 'php'];

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

        // Determine the base directory to start scanning from
        $baseDir = $this->getBaseDirectoryFromPattern($pattern, $basePath);

        // If base directory doesn't exist, no files can match
        if (!\is_dir($baseDir)) {
            $this->logger?->debug('Base directory does not exist', ['baseDir' => $baseDir]);
            return [];
        }

        // Extract the pattern part after the base directory
        $patternSuffix = $this->getPatternSuffix($pattern, $baseDir, $basePath);

        try {
            // Create and configure Symfony Finder
            $finder = new Finder();
            $finder
                ->files()
                ->in($baseDir)
                ->name('/\.(' . \implode('|', self::CONFIG_EXTENSIONS) . ')$/')
                ->followLinks();

            // Apply the pattern as a path filter, but only if it's not just '*'
            if ($patternSuffix !== '*') {
                // Convert our glob pattern to a regex pattern compatible with Symfony's path() method
                $regexPattern = $this->convertGlobToFinderPathRegex($patternSuffix);
                $finder->path($regexPattern);
            }

            $matches = [];
            foreach ($finder as $file) {
                $matches[] = $file->getRealPath();
            }

            $this->logger?->debug('Found matching paths', ['count' => \count($matches), 'paths' => $matches]);
            return $matches;
        } catch (\InvalidArgumentException $e) {
            // Finder throws this if the directory doesn't exist
            $this->logger?->debug('Directory search failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Convert a glob pattern to a regex pattern compatible with Symfony Finder's path() method
     *
     * This method creates a regex pattern that will be compatible with Symfony Finder's
     * expectations for the path() method. It adds delimiters and ensures the pattern is
     * recognized as a regex pattern by Finder.
     */
    private function convertGlobToFinderPathRegex(string $pattern): string
    {
        // First, convert the glob pattern to a regex pattern using our PathMatcher
        $matcher = new PathMatcher($pattern);
        $regex = $matcher->getRegex();

        // Extract the pattern part between the delimiters (remove the ~^ at the start and $ at the end)
        $patternBody = \substr($regex, 2, -2);

        // Create a new regex pattern that will be recognized by Symfony Finder's path() method
        // We need to make sure it starts with a delimiter so it's recognized as a regex
        return '#' . $patternBody . '#';
    }

    /**
     * Get the pattern suffix (after the base directory)
     */
    private function getPatternSuffix(string $pattern, string $baseDir, string $basePath): string
    {
        $resolvedPattern = $this->resolvePath($pattern, $basePath);

        // If baseDir is part of the resolved pattern, extract the suffix
        $baseLength = \strlen($baseDir);
        if (\strncmp($resolvedPattern, $baseDir, $baseLength) === 0) {
            $suffix = \substr($resolvedPattern, $baseLength + 1);
            return $suffix ?: '*';
        }

        return $pattern;
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
