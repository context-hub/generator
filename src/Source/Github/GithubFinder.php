<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Lib\Finder\FinderResult;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Lib\PathFilter\ContentsFilter;
use Butschster\ContextGenerator\Lib\PathFilter\ExcludePathFilter;
use Butschster\ContextGenerator\Lib\PathFilter\FilePatternFilter;
use Butschster\ContextGenerator\Lib\PathFilter\FilterInterface;
use Butschster\ContextGenerator\Lib\PathFilter\PathFilter;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Psr\Log\LoggerInterface;

/**
 * GitHub content finder implementation
 *
 * Fetches and filters content from GitHub repositories
 */
final class GithubFinder implements FinderInterface
{
    /**
     * Filters to apply
     *
     * @var array<FilterInterface>
     */
    private array $filters = [];

    /**
     * Create a new GitHub finder
     */
    public function __construct(
        private readonly GithubClientInterface $githubClient,
        private readonly ExcludeRegistryInterface $excludeRegistry,
        private readonly VariableResolver $variableResolver = new VariableResolver(),
        private readonly FileTreeBuilder $fileTreeBuilder = new FileTreeBuilder(),
        #[LoggerPrefix(prefix: 'github-finder')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Find files in a GitHub repository based on source configuration
     */
    public function find(FilterableSourceInterface $source, string $basePath = '', array $options = []): FinderResult
    {
        if (!$source instanceof GithubSource) {
            throw new \InvalidArgumentException(message: 'Source must be an instance of GithubSource');
        }

        if ($source->githubToken) {
            $this->githubClient->setToken($this->variableResolver->resolve(strings: $source->githubToken));
        }

        // Parse repository from string
        $repository = new GithubRepository(repository: $source->repository, branch: $source->branch);

        // Initialize path filters based on source configuration
        $this->initializePathFilters(source: $source);

        // Get source paths
        $sourcePaths = $source->sourcePaths;
        if (\is_string(value: $sourcePaths)) {
            $sourcePaths = [$sourcePaths];
        }

        // Recursively discover all files from repository paths
        $discoveredItems = $this->discoverRepositoryItems(repository: $repository, sourcePaths: $sourcePaths);

        // Apply path-based filters
        $filteredItems = $this->applyFilters(items: $discoveredItems);

        // Build result structure
        $files = [];
        $this->buildResultStructure(items: $filteredItems, repository: $repository, files: $files);

        // Apply content filters
        $files = (new ContentsFilter(
            contains: $source->contains(),
            notContains: $source->notContains(),
        ))->apply(items: $files);

        // Apply global exclusion registry
        $files = $this->applyGlobalExclusions(files: $files);

        /** @psalm-suppress InvalidArgument */
        $tree = \array_map(callback: static fn(GithubFileInfo $file): string => $file->getRelativePathname(), array: $files);

        // Create the result
        return new FinderResult(
            files: \array_values(array: $files),
            treeView: $this->fileTreeBuilder->buildTree(files: $tree, basePath: '', options: $options),
        );
    }

    /**
     * Apply all filters to the GitHub API response items
     */
    public function applyFilters(array $items): array
    {
        foreach ($this->filters as $filter) {
            $items = $filter->apply($items);
        }

        return $items;
    }

    /**
     * Apply global exclusion patterns to filter files
     */
    private function applyGlobalExclusions(array $files): array
    {
        return \array_filter(array: $files, callback: function (GithubFileInfo $file): bool {
            $path = $file->getRelativePathname();

            if ($this->excludeRegistry->shouldExclude($path)) {
                $this->logger?->debug('File excluded by global exclusion pattern', [
                    'path' => $path,
                ]);
                return false;
            }

            return true;
        });
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
            $this->filters[] = new FilePatternFilter(pattern: $filePattern);
        }

        // Add path inclusion filter
        $path = $source->path();
        if ($path) {
            $this->filters[] = new PathFilter(pathPattern: $path);
        }

        // Add path exclusion filter
        $excludePatterns = $source->notPath();
        if ($excludePatterns) {
            $this->filters[] = new ExcludePathFilter(excludePatterns: $excludePatterns);
        }
    }

    /**
     * Discover all items from repository paths recursively
     *
     * @param GithubRepository $repository GitHub repository
     * @param array<string> $sourcePaths Source paths to discover
     * @return array<array<string, mixed>> Discovered items
     */
    private function discoverRepositoryItems(GithubRepository $repository, array $sourcePaths): array
    {
        $allItems = [];

        foreach ($sourcePaths as $path) {
            $items = $this->fetchDirectoryContents(repository: $repository, path: $path);
            $allItems = \array_merge($allItems, $this->traverseDirectoryRecursively(items: $items, repository: $repository));
        }

        return $allItems;
    }

    /**
     * Traverse directory items recursively to discover all files
     */
    private function traverseDirectoryRecursively(array $items, GithubRepository $repository): array
    {
        $result = [];

        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'dir') {
                $subItems = $this->fetchDirectoryContents(repository: $repository, path: $item['path']);
                $result = \array_merge($result, $this->traverseDirectoryRecursively(items: $subItems, repository: $repository));
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
        GithubRepository $repository,
        array &$files,
    ): void {
        foreach ($items as $item) {
            $path = $item['path'];

            try {
                $relativePath = \dirname(path: (string) $path);
                if ($relativePath === '.') {
                    $relativePath = '';
                }

                // Add to files array
                $files[] = new GithubFileInfo(
                    relativePath: $relativePath,
                    relativePathname: $path,
                    metadata: $item,
                    content: fn() => $this->fetchFileContent(repository: $repository, path: $path),
                );
            } catch (\Exception) {
                // Skip files that can't be processed
                continue;
            }
        }
    }

    /**
     * Fetch directory contents from GitHub API
     */
    private function fetchDirectoryContents(GithubRepository $repository, string $path = ''): array
    {
        return $this->githubClient->getContents($repository, $path);
    }

    /**
     * Fetch file content from GitHub API
     */
    private function fetchFileContent(GithubRepository $repository, string $path): string
    {
        return $this->githubClient->getFileContent($repository, $path);
    }
}
