<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryList;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryList\Dto\DirectoryListRequest;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Symfony\Component\Finder\Finder;

/**
 * Handler for listing directories and files with filtering options.
 */
final readonly class DirectoryListHandler
{
    public function __construct(
        #[Proxy] private DirectoriesInterface $dirs,
        private FileTreeBuilder $treeBuilder,
        private ExcludeRegistryInterface $excludeRegistry,
        private LoggerInterface $logger,
    ) {}

    /**
     * Execute the directory listing operation.
     */
    public function handle(DirectoryListRequest $request): DirectoryListResult
    {
        $relativePath = $request->path;
        $path = (string) $this->dirs->getRootPath()->join($relativePath);

        if (empty($path)) {
            return DirectoryListResult::error('Missing path parameter');
        }

        // Check if path is excluded
        if ($this->excludeRegistry->shouldExclude($relativePath)) {
            return DirectoryListResult::error(
                \sprintf("Path '%s' is excluded by project configuration", $relativePath),
            );
        }

        if (!\file_exists($path)) {
            return DirectoryListResult::error(\sprintf("Path '%s' does not exist", $relativePath));
        }

        if (!\is_dir($path)) {
            return DirectoryListResult::error(\sprintf("Path '%s' is not a directory", $relativePath));
        }

        try {
            $finder = $this->createFinder($path, $request);
            $allFiles = $this->collectFiles($finder);

            $totalMatched = \count($allFiles);
            $truncated = false;
            $warning = null;

            // Apply maxResults limit
            if ($request->maxResults > 0 && $totalMatched > $request->maxResults) {
                $allFiles = \array_slice($allFiles, 0, $request->maxResults);
                $truncated = true;
                $warning = \sprintf(
                    'Results truncated: found %d items, returned %d. Consider using pattern filter or reducing depth.',
                    $totalMatched,
                    $request->maxResults,
                );
            }

            // Generate tree view if requested
            if ($request->showTree === true) {
                $treeView = $this->buildTreeView($allFiles, $request);

                return DirectoryListResult::successWithTree(
                    $relativePath,
                    $allFiles,
                    $treeView,
                    $totalMatched,
                    $truncated,
                    $warning,
                );
            }

            return DirectoryListResult::success(
                $relativePath,
                $allFiles,
                $totalMatched,
                $truncated,
                $warning,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error listing directory', [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DirectoryListResult::error($e->getMessage());
        }
    }

    private function createFinder(string $path, DirectoryListRequest $request): Finder
    {
        $finder = new Finder();
        $finder->in($path);

        // Apply pattern filter if provided
        if (!empty($request->pattern)) {
            $patterns = \array_map(\trim(...), \explode(',', $request->pattern));
            $finder->name($patterns);
        }

        // Apply depth filter
        $finder->depth('<= ' . $request->depth);

        // Apply size filter if provided
        if (!empty($request->size)) {
            $finder->size($request->size);
        }

        // Apply date filter if provided
        if (!empty($request->date)) {
            $finder->date($request->date);
        }

        // Apply content filter if provided
        if (!empty($request->contains)) {
            $finder->contains($request->contains);
        }

        // Apply type filter
        $type = \strtolower($request->type);
        if ($type === 'file') {
            $finder->files();
        } elseif ($type === 'directory') {
            $finder->directories();
        } else {
            // Default: include both files and directories
            $finder->ignoreDotFiles(false);
        }

        // Apply sorting
        $sort = \strtolower($request->sort);
        match ($sort) {
            'name' => $finder->sortByName(),
            'type' => $finder->sortByType(),
            'date' => $finder->sortByModifiedTime(),
            'size' => $finder->sortBySize(),
            default => $finder->sortByName(),
        };

        return $finder;
    }

    /**
     * Collect files from finder, excluding paths as configured.
     *
     * @return array<array{name: string, path: string, fullPath: string, isDirectory: bool, size: int|null, lastModified: string}>
     */
    private function collectFiles(Finder $finder): array
    {
        $files = [];
        $rootPath = (string) $this->dirs->getRootPath();

        try {
            foreach ($finder as $file) {
                $fileRelativePath = \str_replace($rootPath . '/', '', $file->getRealPath());

                // Skip excluded files and directories
                if ($this->excludeRegistry->shouldExclude($fileRelativePath)) {
                    continue;
                }

                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $fileRelativePath,
                    'fullPath' => $file->getRealPath(),
                    'isDirectory' => $file->isDir(),
                    'size' => $file->isFile() ? $file->getSize() : null,
                    'lastModified' => \date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('Finder search warning', [
                'error' => $e->getMessage(),
            ]);
        }

        return $files;
    }

    /**
     * Build tree view from collected files.
     *
     * @param array<array{name: string, path: string, fullPath: string, isDirectory: bool, size: int|null, lastModified: string}> $files
     */
    private function buildTreeView(array $files, DirectoryListRequest $request): string
    {
        $filePaths = \array_column($files, 'fullPath');

        if (empty($filePaths)) {
            return 'No files match the specified criteria.';
        }

        $treeViewConfig = new TreeViewConfig();

        if ($request->treeView !== null) {
            $treeViewConfig = new TreeViewConfig(
                enabled: true,
                showSize: $request->treeView->showSize,
                showLastModified: $request->treeView->showLastModified,
                showCharCount: $request->treeView->showCharCount,
                includeFiles: $request->treeView->includeFiles,
                maxDepth: $request->depth,
                dirContext: [],
            );
        }

        return $this->treeBuilder->buildTree(
            $filePaths,
            (string) $this->dirs->getRootPath(),
            $treeViewConfig->getOptions(),
        );
    }
}
