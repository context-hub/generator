<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

/**
 * Source for GitHub repositories
 */
final class GithubSource extends BaseSource
{
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

        return new self(
            repository: $data['repository'],
            sourcePaths: $sourcePaths,
            branch: $data['branch'] ?? 'main',
            description: $data['description'] ?? '',
            filePattern: $data['filePattern'] ?? '*.php',
            excludePatterns: $data['excludePatterns'] ?? [],
            showTreeView: $data['showTreeView'] ?? true,
            githubToken: $data['githubToken'] ?? null,
            modifiers: $data['modifiers'] ?? [],
        );
    }

    /**
     * @param string $repository GitHub repository in format "owner/repo"
     * @param string $branch Branch or tag to fetch from (default: main)
     * @param string $description Human-readable description
     * @param string $filePattern Pattern to match files
     * @param array<string> $excludePatterns Patterns to exclude files
     * @param bool $showTreeView Whether to show directory tree
     * @param string|null $githubToken GitHub API token for private repositories
     * @param array<string> $modifiers Identifiers for content modifiers to apply
     */
    public function __construct(
        public readonly string $repository,
        public readonly string|array $sourcePaths,
        public readonly string $branch = 'main',
        string $description = '',
        public readonly string $filePattern = '*.php',
        public readonly array $excludePatterns = [],
        public readonly bool $showTreeView = true,
        public readonly ?string $githubToken = null,
        public readonly array $modifiers = [],
    ) {
        parent::__construct($description);
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
            $url .= '/' . ltrim($path, '/');
        }

        // Add ref parameter for branch or tag
        $url .= '?ref=' . urlencode($this->branch);

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
            ltrim($path, '/'),
        );
    }

    /**
     * Get authentication headers for GitHub API
     *
     * @return array<string, string>
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