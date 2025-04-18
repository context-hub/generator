<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab;

use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Lib\GitlabClient\GitlabClientInterface;
use Butschster\ContextGenerator\Lib\GitlabClient\Model\GitlabRepository;
use Butschster\ContextGenerator\Lib\PathFilter\ContentsFilter;
use Butschster\ContextGenerator\Lib\PathFilter\ExcludePathFilter;
use Butschster\ContextGenerator\Lib\PathFilter\FilePatternFilter;
use Butschster\ContextGenerator\Lib\PathFilter\FilterInterface;
use Butschster\ContextGenerator\Lib\PathFilter\PathFilter;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Source\Gitlab\Config\ServerRegistry;

final class GitlabFinder implements FinderInterface
{
    /**
     * Filters to apply
     *
     * @var array<FilterInterface>
     */
    private array $filters = [];

    public function __construct(
        private readonly GitlabClientInterface $gitlabClient,
        private readonly ServerRegistry $serverRegistry,
        private readonly VariableResolver $variableResolver = new VariableResolver(),
        private readonly FileTreeBuilder $fileTreeBuilder = new FileTreeBuilder(),
    ) {}

    public function find(FilterableSourceInterface $source, string $basePath = '', array $options = []): FinderResult
    {
        if (!$source instanceof GitlabSource) {
            throw new \InvalidArgumentException('Source must be an instance of GitlabSource');
        }

        // Configure GitLab client based on source configuration
        $this->configureGitlabClient($source);

        // Parse repository from string
        $repository = new GitlabRepository($source->repository, $source->branch);

        // Initialize path filters based on source configuration
        $this->initializePathFilters($source);

        // Get source paths
        $sourcePaths = $source->sourcePaths;
        if (\is_string($sourcePaths)) {
            $sourcePaths = [$sourcePaths];
        }

        // Recursively discover all files from repository paths
        $discoveredItems = $this->discoverRepositoryItems($repository, $sourcePaths);

        // Apply path-based filters
        $filteredItems = $this->applyFilters($discoveredItems);

        // Build result structure
        $files = [];
        $this->buildResultStructure($filteredItems, $repository, $files);

        $files = (new ContentsFilter(
            contains: $source->contains(),
            notContains: $source->notContains(),
        ))->apply($files);

        /** @psalm-suppress InvalidArgument */
        $tree = \array_map(static fn(GitlabFileInfo $file): string => $file->getRelativePathname(), $files);

        // Create the result
        return new FinderResult(
            \array_values($files),
            $this->fileTreeBuilder->buildTree($tree, '', $options),
        );
    }

    public function applyFilters(array $items): array
    {
        foreach ($this->filters as $filter) {
            $items = $filter->apply($items);
        }

        return $items;
    }

    /**
     * Configure the GitLab client based on source configuration
     */
    private function configureGitlabClient(GitlabSource $source): void
    {
        $serverConfig = $source->server;
        $resolvedConfig = $serverConfig->withResolvedVariables($this->variableResolver);

        $this->gitlabClient->setServerUrl($resolvedConfig->url);

        if ($resolvedConfig->token !== null) {
            $this->gitlabClient->setToken($resolvedConfig->token);
        }

        if (!empty($resolvedConfig->headers)) {
            $this->gitlabClient->setHeaders($resolvedConfig->headers);
        }
    }

    /**
     * Initialize path filters based on source configuration
     *
     * @param FilterableSourceInterface $source Source with filter criteria
     */
    private function initializePathFilters(FilterableSourceInterface $source): void
    {
        // Clear existing filters
        $this->filters = [];

        // Add file name pattern filter
        $filePattern = $source->name();
        if ($filePattern) {
            $this->filters[] = new FilePatternFilter($filePattern);
        }

        // Add path inclusion filter
        $path = $source->path();
        if ($path) {
            $this->filters[] = new PathFilter($path);
        }

        // Add path exclusion filter
        $excludePatterns = $source->notPath();
        if ($excludePatterns) {
            $this->filters[] = new ExcludePathFilter($excludePatterns);
        }
    }

    /**
     * Discover all items from repository paths recursively
     *
     * @param GitlabRepository $repository GitLab repository
     * @param array<string> $sourcePaths Source paths to discover
     * @return array<array<string, mixed>> Discovered items
     */
    private function discoverRepositoryItems(GitlabRepository $repository, array $sourcePaths): array
    {
        $allItems = [];

        foreach ($sourcePaths as $path) {
            $items = $this->fetchDirectoryContents($repository, $path);
            $allItems = \array_merge($allItems, $this->traverseDirectoryRecursively($items, $repository));
        }

        return $allItems;
    }

    /**
     * Traverse directory items recursively to discover all files
     */
    private function traverseDirectoryRecursively(array $items, GitlabRepository $repository): array
    {
        $result = [];

        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'tree') {
                $subItems = $this->fetchDirectoryContents($repository, $item['path']);
                $result = \array_merge($result, $this->traverseDirectoryRecursively($subItems, $repository));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Build the final result structure (files and tree)
     */
    private function buildResultStructure(
        array $items,
        GitlabRepository $repository,
        array &$files,
    ): void {
        foreach ($items as $item) {
            $path = $item['path'];

            try {
                $relativePath = \dirname((string) $path);
                if ($relativePath === '.') {
                    $relativePath = '';
                }

                // Add to files array
                $files[] = new GitlabFileInfo(
                    $relativePath,
                    $path,
                    $item,
                    fn() => $this->fetchFileContent($repository, $path),
                );
            } catch (\Exception) {
                // Skip files that can't be processed
                continue;
            }
        }
    }

    /**
     * Fetch directory contents from GitLab API
     */
    private function fetchDirectoryContents(GitlabRepository $repository, string $path = ''): array
    {
        return $this->gitlabClient->getContents($repository, $path);
    }

    /**
     * Fetch file content from GitLab API
     */
    private function fetchFileContent(GitlabRepository $repository, string $path): string
    {
        return $this->gitlabClient->getFileContent($repository, $path);
    }
}
