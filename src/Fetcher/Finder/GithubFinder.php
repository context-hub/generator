<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher\Finder;

use Butschster\ContextGenerator\Fetcher\FileTreeBuilder;
use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Fetcher\FinderInterface;
use Butschster\ContextGenerator\Source\GithubSource;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Finder implementation for GitHub repositories
 *
 * This class provides a Finder implementation for GitHub repositories
 * that works with the FilterableSourceInterface.
 */
final readonly class GithubFinder implements FinderInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private UriFactoryInterface $uriFactory,
        private FileTreeBuilder $fileTreeBuilder = new FileTreeBuilder(),
        private ?string $githubToken = null,
    ) {}

    /**
     * Find files in a GitHub repository based on the given source configuration
     *
     * @param FilterableSourceInterface $source Source configuration with filter criteria
     * @param string $basePath Optional base path to normalize file paths in the tree view
     * @return FinderResult The result containing found files and tree view
     */
    public function find(FilterableSourceInterface $source, string $basePath = ''): FinderResult
    {
        if (!$source instanceof GithubSource) {
            throw new \InvalidArgumentException(
                \sprintf('Source must be an instance of GithubSource, %s given', $source::class),
            );
        }

        $files = $this->fetchFiles($source);
        $treeView = $this->generateTreeView($files, $basePath);

        return new FinderResult(
            files: new \ArrayIterator($files),
            treeView: $treeView,
        );
    }

    /**
     * Fetch files from GitHub repository based on source configuration
     *
     * @param GithubSource $source The GitHub source configuration
     * @return array<string> List of file paths
     */
    private function fetchFiles(GithubSource $source): array
    {
        $files = [];
        $token = $source->githubToken ?? $this->githubToken;
        $paths = (array) $source->in();

        if (empty($paths)) {
            throw new \InvalidArgumentException('No paths provided for GitHub source');
        }

        foreach ($paths as $path) {
            $this->fetchContentsRecursively($source, $path, $files, $token);
        }

        // Apply filters to the found files
        return $this->applyFilters($files, $source);
    }

    /**
     * Generate a tree view of the found files
     *
     * @param array<string> $files The list of file paths
     * @param string $basePath Optional base path to normalize file paths
     * @return string Text representation of the file tree
     */
    private function generateTreeView(array $files, string $basePath): string
    {
        if (empty($files)) {
            return "No files found.\n";
        }

        return $this->fileTreeBuilder->buildTree($files, $basePath);
    }

    /**
     * Fetch repository contents recursively
     *
     * @param GithubSource $source GitHub source
     * @param string $path Path to fetch
     * @param array<string> &$files Reference to files array to populate
     * @param string|null $token GitHub token for authentication
     * @throws \RuntimeException If API request fails
     */
    private function fetchContentsRecursively(
        GithubSource $source,
        string $path,
        array &$files,
        ?string $token = null,
    ): void {
        try {
            $contentsUrl = $source->getContentsUrl($path);
            $request = $this->requestFactory->createRequest('GET', $this->uriFactory->createUri($contentsUrl));

            // Add authentication headers
            foreach ($source->getAuthHeaders($token) as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            $response = $this->httpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(
                    \sprintf('Failed to fetch repository contents with status code %d: %s', $statusCode, $path),
                );
            }

            $contents = \json_decode((string) $response->getBody(), true);

            if (!\is_array($contents)) {
                throw new \RuntimeException('Invalid response from GitHub API');
            }

            // If it's a single file (not an array of items)
            if (isset($contents['type']) && $contents['type'] === 'file') {
                $files[] = $path;
                return;
            }

            // Process each item in the directory
            foreach ($contents as $item) {
                $itemPath = $item['path'];

                if ($item['type'] === 'file') {
                    $files[] = $itemPath;
                } elseif ($item['type'] === 'dir') {
                    $this->fetchContentsRecursively($source, $itemPath, $files, $token);
                }
            }
        } catch (ClientExceptionInterface $e) {
            throw new \RuntimeException(
                \sprintf('Failed to fetch repository contents: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * Apply filters to the list of files
     *
     * @param array<string> $files The files to filter
     * @param GithubSource $source The source containing filter criteria
     * @return array<string> The filtered files
     */
    private function applyFilters(array $files, GithubSource $source): array
    {
        $filteredFiles = [];

        // Convert filePattern to array
        $filePatterns = (array) $source->filePattern;

        // Convert excludePatterns to array
        $excludePatterns = (array) $source->excludePatterns;

        foreach ($files as $file) {
            $fileName = \basename($file);
            $filePath = \dirname($file);

            // Check if file matches include patterns
            $includeFile = false;

            if (empty($filePatterns)) {
                $includeFile = true;
            } else {
                foreach ($filePatterns as $pattern) {
                    if ($this->matchesPattern($fileName, $pattern)) {
                        $includeFile = true;
                        break;
                    }
                }
            }

            // Check if file matches exclude patterns
            if ($includeFile && !empty($excludePatterns)) {
                foreach ($excludePatterns as $pattern) {
                    if ($this->matchesPattern($file, $pattern)) {
                        $includeFile = false;
                        break;
                    }
                }
            }

            if ($includeFile) {
                $filteredFiles[] = $file;
            }
        }

        return $filteredFiles;
    }

    /**
     * Check if a string matches a pattern (supports glob patterns)
     *
     * @param string $string The string to check
     * @param string $pattern The pattern to match against
     * @return bool Whether the string matches the pattern
     */
    private function matchesPattern(string $string, string $pattern): bool
    {
        // Convert glob pattern to regex
        $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/i';
        return (bool) preg_match($regex, $string);
    }
}