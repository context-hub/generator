<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileSearch;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileSearch\Dto\FileSearchRequest;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Files\FilesInterface;
use Symfony\Component\Finder\Finder;

/**
 * Handler for searching file contents with regex/text patterns.
 */
final readonly class FileSearchHandler
{
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB per file

    public function __construct(
        private FilesInterface $files,
        #[Proxy] private DirectoriesInterface $dirs,
        private ExcludeRegistryInterface $excludeRegistry,
        private LoggerInterface $logger,
    ) {}

    /**
     * Execute search and return results for all matching files.
     *
     * @return FileSearchResult[]
     */
    public function search(FileSearchRequest $request): array
    {
        $rootPath = (string) $this->dirs->getRootPath();
        $searchPath = $request->path !== ''
            ? $rootPath . '/' . \ltrim($request->path, '/')
            : $rootPath;

        if (!\is_dir($searchPath)) {
            return [FileSearchResult::error($request->path, 'Directory does not exist')];
        }

        $finder = $this->createFinder($searchPath, $request);
        $pattern = $request->buildPattern();

        $results = [];
        $totalMatches = 0;
        $maxTotal = $request->maxTotalMatches > 0 ? $request->maxTotalMatches : PHP_INT_MAX;

        foreach ($finder as $file) {
            if ($totalMatches >= $maxTotal) {
                break;
            }

            $relativePath = \str_replace($rootPath . '/', '', $file->getRealPath());

            // Skip excluded files
            if ($this->excludeRegistry->shouldExclude($relativePath)) {
                continue;
            }

            // Skip files that are too large
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                $this->logger->debug('Skipping large file', ['path' => $relativePath]);
                continue;
            }

            $result = $this->searchFile(
                $file->getRealPath(),
                $relativePath,
                $pattern,
                $request->contextLines,
                $request->maxMatchesPerFile,
                $maxTotal - $totalMatches,
                $file->getSize(),
                $file->getMTime(),
            );

            if ($result->success && $result->getMatchCount() > 0) {
                $results[] = $result;
                $totalMatches += $result->getMatchCount();
            }
        }

        return $results;
    }

    private function createFinder(string $searchPath, FileSearchRequest $request): Finder
    {
        $finder = new Finder();
        $finder->files()->in($searchPath);
        $finder->depth('<= ' . $request->depth);
        $finder->sortByName();

        // Apply file pattern filter
        if ($request->pattern !== null && $request->pattern !== '') {
            $patterns = \array_map(\trim(...), \explode(',', $request->pattern));
            $finder->name($patterns);
        }

        // Apply size filter
        if ($request->size !== null && $request->size !== '') {
            $finder->size($request->size);
        }

        // Ignore common non-text directories
        $finder->exclude(['vendor', 'node_modules', '.git', '.idea', 'var', 'cache']);

        return $finder;
    }

    private function searchFile(
        string $fullPath,
        string $relativePath,
        string $pattern,
        int $contextLines,
        int $maxPerFile,
        int $remainingTotal,
        int $fileSize,
        int $lastModified,
    ): FileSearchResult {
        try {
            $content = $this->files->read($fullPath);
        } catch (\Throwable $e) {
            return FileSearchResult::error($relativePath, $e->getMessage());
        }

        // Check if file is binary
        if ($this->isBinary($content)) {
            return FileSearchResult::success($relativePath, []);
        }

        $lines = \explode("\n", $content);
        $matches = [];
        $maxMatches = $maxPerFile > 0 ? \min($maxPerFile, $remainingTotal) : $remainingTotal;
        $truncated = false;

        foreach ($lines as $index => $line) {
            if (\count($matches) >= $maxMatches) {
                $truncated = true;
                break;
            }

            if (@\preg_match($pattern, $line)) {
                $lineNumber = $index + 1;
                $matches[] = $this->createMatch(
                    $relativePath,
                    $lines,
                    $index,
                    $lineNumber,
                    $contextLines,
                );
            }
        }

        return FileSearchResult::success(
            file: $relativePath,
            matches: $matches,
            truncated: $truncated,
            fileSize: $fileSize,
            lastModified: $lastModified,
        );
    }

    private function createMatch(
        string $file,
        array $lines,
        int $index,
        int $lineNumber,
        int $contextLines,
    ): SearchMatch {
        $startIndex = \max(0, $index - $contextLines);
        $endIndex = \min(\count($lines) - 1, $index + $contextLines);

        $contextBefore = [];
        for ($i = $startIndex; $i < $index; $i++) {
            $contextBefore[] = $lines[$i];
        }

        $contextAfter = [];
        for ($i = $index + 1; $i <= $endIndex; $i++) {
            $contextAfter[] = $lines[$i];
        }

        return new SearchMatch(
            file: $file,
            lineNumber: $lineNumber,
            line: $lines[$index],
            contextBefore: $contextBefore,
            contextAfter: $contextAfter,
            contextStartLine: $startIndex + 1,
        );
    }

    private function isBinary(string $content): bool
    {
        // Check first 8KB for null bytes (common indicator of binary)
        $sample = \substr($content, 0, 8192);
        return \str_contains($sample, "\0");
    }
}
