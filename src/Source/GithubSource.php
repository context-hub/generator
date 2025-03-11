<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Modifier\Modifier;

/**
 * Source for GitHub repositories
 */
final class GithubSource extends BaseSource implements FilterableSourceInterface
{
    /**
     * @param string $repository GitHub repository in format "owner/repo"
     * @param string $branch Branch or tag to fetch from (default: main)
     * @param string $description Human-readable description
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $excludePatterns Patterns to exclude files
     * @param bool $showTreeView Whether to show directory tree
     * @param string|null $githubToken GitHub API token for private repositories
     * @param array<Modifier> $modifiers Identifiers for content modifiers to apply
     */
    public function __construct(
        public readonly string $repository,
        public readonly string|array $sourcePaths,
        public readonly string $branch = 'main',
        string $description = '',
        public readonly string|array $filePattern = '*.*',
        public readonly array $excludePatterns = [],
        public readonly bool $showTreeView = true,
        public readonly ?string $githubToken = null,
        public readonly array $modifiers = [],
    ) {
        parent::__construct($description);
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['repository'])) {
            throw new \RuntimeException('GitHub source must have a "repository" property');
        }

        // Determine source paths (required)
        if (!isset($data['sourcePaths'])) {
            throw new \RuntimeException('GitHub source must have a "sourcePaths" property');
        }

        $sourcePaths = $data['sourcePaths'];
        if (!\is_string($sourcePaths) && !\is_array($sourcePaths)) {
            throw new \RuntimeException('"sourcePaths" must be a string or array in source');
        }

        // Convert to array if single string
        $sourcePaths = \is_string($sourcePaths) ? [$sourcePaths] : $sourcePaths;

        // Validate filePattern if present
        if (isset($data['filePattern'])) {
            if (!\is_string($data['filePattern']) && !\is_array($data['filePattern'])) {
                throw new \RuntimeException('filePattern must be a string or an array of strings');
            }

            // If it's an array, make sure all elements are strings
            if (\is_array($data['filePattern'])) {
                foreach ($data['filePattern'] as $pattern) {
                    if (!\is_string($pattern)) {
                        throw new \RuntimeException('All elements in filePattern must be strings');
                    }
                }
            }
        }

        // Handle filePattern parameter, allowing both string and array formats
        $filePattern = $data['filePattern'] ?? '*.*';

        return new self(
            repository: $data['repository'],
            sourcePaths: $sourcePaths,
            branch: $data['branch'] ?? 'main',
            description: $data['description'] ?? '',
            filePattern: $filePattern,
            excludePatterns: $data['excludePatterns'] ?? [],
            showTreeView: $data['showTreeView'] ?? true,
            githubToken: $data['githubToken'] ?? null,
            modifiers: $data['modifiers'] ?? [],
        );
    }

    /**
     * Get base GitHub API URL for this repository
     */
    public function getApiBaseUrl(): string
    {
        return \sprintf('https://api.github.com/repos/%s', $this->repository);
    }

    /**
     * Get contents API URL for a specific path
     */
    public function getContentsUrl(string $path = ''): string
    {
        $url = $this->getApiBaseUrl() . '/contents';

        if (!empty($path)) {
            $url .= '/' . \ltrim($path, '/');
        }

        // Add ref parameter for branch or tag
        $url .= '?ref=' . \urlencode($this->branch);

        return $url;
    }

    /**
     * Get raw content URL for a specific file
     */
    public function getRawContentUrl(string $path): string
    {
        return \sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s',
            $this->repository,
            $this->branch,
            \ltrim($path, '/'),
        );
    }

    /**
     * Get authentication headers for GitHub API
     *
     * @return string[]
     *
     * @psalm-return array{Accept: 'application/vnd.github.v3+json', 'User-Agent': 'Context-Generator-Bot', Authorization?: string}
     */
    public function getAuthHeaders(?string $githubToken = null): array
    {
        $githubToken = $this->githubToken ?? $githubToken;

        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Context-Generator-Bot',
        ];

        if ($githubToken !== null) {
            $headers['Authorization'] = 'token ' . $githubToken;
        }

        return $headers;
    }

    /**
     * Get file name pattern(s)
     *
     * @return string|string[] Pattern(s) to match file names against
     *
     * @psalm-return array<string>|string
     */
    public function name(): string|array|null
    {
        return $this->filePattern;
    }

    /**
     * Get file path pattern(s)
     *
     * @return null Pattern(s) to match file paths against
     */
    public function path(): string|array|null
    {
        return null; // GitHub source doesn't use path patterns directly
    }

    /**
     * Get excluded path pattern(s)
     *
     * @return string[] Pattern(s) to exclude file paths
     *
     * @psalm-return array<string>
     */
    public function notPath(): string|array|null
    {
        return $this->excludePatterns;
    }

    /**
     * Get content pattern(s)
     *
     * @return null Pattern(s) to match file content against
     */
    public function contains(): string|array|null
    {
        return null; // GitHub source doesn't support content filtering directly
    }

    /**
     * Get excluded content pattern(s)
     *
     * @return null Pattern(s) to exclude file content
     */
    public function notContains(): string|array|null
    {
        return null; // GitHub source doesn't support content exclusion filtering directly
    }

    /**
     * Get size constraint(s)
     *
     * @return null Size constraint(s)
     */
    public function size(): string|array|null
    {
        return null; // GitHub source doesn't support size filtering directly
    }

    /**
     * Get date constraint(s)
     *
     * @return null Date constraint(s)
     */
    public function date(): string|array|null
    {
        return null; // GitHub source doesn't support date filtering directly
    }

    /**
     * Get directories to search in
     * @return array<string>|null Directories to search in
     */
    public function in(): array|null
    {
        return (array) $this->sourcePaths;
    }

    /**
     * Get individual files to include
     *
     * @return null Individual files to include
     */
    public function files(): array|null
    {
        return null; // GitHub source treats all sourcePaths as directories
    }

    /**
     * Check if unreadable directories should be ignored
     *
     * @return false
     */
    public function ignoreUnreadableDirs(): bool
    {
        return false; // Not applicable for GitHub sources
    }

    /**
     * @return (string|string[]|true)[]
     *
     * @psalm-return array{type: 'github', repository?: string, branch?: string, description?: string, filePattern?: array<string>|string, excludePatterns?: array<string>, showTreeView?: true, githubToken?: string, modifiers?: array<string>}
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'github',
            'repository' => $this->repository,
            'branch' => $this->branch,
            'description' => $this->description,
            'filePattern' => $this->filePattern,
            'excludePatterns' => $this->excludePatterns,
            'showTreeView' => $this->showTreeView,
            'githubToken' => $this->githubToken,
            'modifiers' => $this->modifiers,
        ]);
    }
}
