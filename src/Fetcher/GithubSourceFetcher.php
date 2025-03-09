<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Source\GithubSource;
use Butschster\ContextGenerator\Source\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\Finder\Glob;

/**
 * Fetcher for GitHub repository sources
 * @implements SourceFetcherInterface<GithubSource>
 */
final readonly class GithubSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private ?ClientInterface $httpClient,
        private ?RequestFactoryInterface $requestFactory,
        private ?UriFactoryInterface $uriFactory,
        private SourceModifierRegistry $modifiers,
        private FileTreeBuilder $treeBuilder = new FileTreeBuilder(),
        private ?string $githubToken = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        return $source instanceof GithubSource;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof GithubSource) {
            throw new \InvalidArgumentException('Source must be an instance of GithubSource');
        }

        if ($this->httpClient === null || $this->requestFactory === null || $this->uriFactory === null) {
            throw new \RuntimeException('To use Github source you need to install PSR-18 HTTP client');
        }

        $content = '';
        $filePaths = [];

        // Process each source path
        foreach ((array) $source->sourcePaths as $sourcePath) {
            // Fetch directory structure and file contents recursively
            $fetched = $this->fetchContentsRecursively($source, $sourcePath);
            $filePaths = [...$filePaths, ...$fetched];
        }

        // Filter out files that don't match the pattern or are excluded
        $filteredPaths = $this->filterPaths($filePaths, $source);

        // Add tree view if requested
        if ($source->showTreeView && !empty($filteredPaths)) {
            $content .= $this->generateTreeView($filteredPaths, $source);
        }

        // Fetch and add the content of each file
        foreach ($filteredPaths as $path) {
            $fileContent = $this->fetchFileContent($source, $path);

            // Apply modifiers if available
            if (!empty($source->modifiers)) {
                foreach ($source->modifiers as $modifierId) {
                    if ($this->modifiers->has($modifierId)) {
                        $modifier = $this->modifiers->get($modifierId);

                        if ($modifier->supports($path)) {
                            $context = [
                                'path' => $path,
                                'source' => $source,
                            ];

                            $fileContent = $modifier->modify($fileContent, $context);
                        }
                    }
                }
            }

            $content .= '```' . PHP_EOL;
            $content .= "// Path: {$path}" . PHP_EOL;
            $content .= "// Url: " . $source->getRawContentUrl($path) . PHP_EOL;
            $content .= \trim($fileContent) . PHP_EOL . PHP_EOL;
            $content .= '```' . PHP_EOL;
        }

        return $content;
    }

    /**
     * Fetch repository contents recursively
     *
     * @param GithubSource $source Github source
     * @param string $path Path within the repository
     * @return array<string> List of file paths
     */
    private function fetchContentsRecursively(GithubSource $source, string $path): array
    {
        $filePaths = [];
        $contentsUrl = $source->getContentsUrl($path);

        // Create and send the request
        $request = $this->requestFactory->createRequest('GET', $this->uriFactory->createUri($contentsUrl));

        // Add headers
        foreach ($source->getAuthHeaders($this->githubToken) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Send the request
        $response = $this->httpClient->sendRequest($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(
                sprintf('GitHub API request failed with status code %d: %s', $statusCode, $response->getBody()),
            );
        }

        $contents = \json_decode((string) $response->getBody(), true);

        if (!\is_array($contents)) {
            throw new \RuntimeException('Invalid response from GitHub API');
        }

        // check if contents is file
        if (isset($contents['type']) && $contents['type'] === 'file') {
            return [$contents['path']];
        }

        foreach ($contents as $item) {
            $itemPath = $item['path'] ?? '';

            if ($item['type'] === 'file') {
                $filePaths[] = $itemPath;
            } elseif ($item['type'] === 'dir') {
                // Recursively fetch directory contents
                $subPaths = $this->fetchContentsRecursively($source, $itemPath);
                $filePaths = [...$filePaths, ...$subPaths];
            }
        }

        return $filePaths;
    }

    /**
     * Filter paths based on file pattern and exclusion patterns
     *
     * @param array<string> $paths List of file paths
     * @param GithubSource $source Github source config
     * @return array<string> Filtered list of file paths
     */
    private function filterPaths(array $paths, GithubSource $source): array
    {
        // Convert glob pattern to regex
        $patternRegex = Glob::toRegex($source->filePattern);

        return \array_filter($paths, function (string $path) use ($patternRegex, $source) {
            // Check if path matches file pattern
            if (!\preg_match($patternRegex, \basename($path))) {
                return false;
            }

            // Check if path matches any exclusion pattern
            foreach ($source->excludePatterns as $excludePattern) {
                if (\str_contains($path, (string) $excludePattern)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Generate tree view for file paths
     *
     * @param array<string> $filePaths List of file paths
     * @param GithubSource $source Github source
     * @return string Tree view representation
     */
    private function generateTreeView(array $filePaths, GithubSource $source): string
    {
        return $this->treeBuilder->buildTree($filePaths, '') . PHP_EOL;
    }

    /**
     * Fetch file content from GitHub
     *
     * @param GithubSource $source Github source
     * @param string $path File path within the repository
     * @return string File content
     */
    private function fetchFileContent(GithubSource $source, string $path): string
    {
        $rawUrl = $source->getRawContentUrl($path);

        // Create and send the request
        $request = $this->requestFactory->createRequest('GET', $this->uriFactory->createUri($rawUrl));

        // Add headers
        foreach ($source->getAuthHeaders($this->githubToken) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Send the request
        $response = $this->httpClient->sendRequest($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(
                \sprintf('Failed to fetch file content with status code %d: %s', $statusCode, $path),
            );
        }

        return (string) $response->getBody();
    }
}
