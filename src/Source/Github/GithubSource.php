<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github;

use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\SourceWithModifiers;

/**
 * Source for GitHub repositories
 */
final class GithubSource extends SourceWithModifiers implements FilterableSourceInterface
{
    /**
     * @param string $repository GitHub repository in format "owner/repo"
     * @param string $branch Branch or tag to fetch from (default: main)
     * @param string $description Human-readable description
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $notPath Patterns to exclude files
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
        public readonly array $notPath = [],
        public readonly string|array|null $path = null,
        public readonly string|array|null $contains = null,
        public readonly string|array|null $notContains = null,
        public readonly bool $showTreeView = true,
        public readonly ?string $githubToken = null,
        array $modifiers = [],
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags, modifiers: $modifiers);
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

        // Convert notPath to match Symfony Finder's naming convention
        $notPath = $data['excludePatterns'] ?? $data['notPath'] ?? [];

        return new self(
            repository: $data['repository'],
            sourcePaths: $sourcePaths,
            branch: $data['branch'] ?? 'main',
            description: $data['description'] ?? '',
            filePattern: $data['filePattern'] ?? '*.*',
            notPath: $notPath,
            path: $data['path'] ?? null,
            contains: $data['contains'] ?? null,
            notContains: $data['notContains'] ?? null,
            showTreeView: $data['showTreeView'] ?? true,
            githubToken: $data['githubToken'] ?? null,
            modifiers: $data['modifiers'] ?? [],
            tags: $data['tags'] ?? [],
        );
    }

    /**
     * Get file name pattern(s)
     *
     * @return string|array<string>|null Pattern(s) to match file names against
     */
    public function name(): string|array|null
    {
        return $this->filePattern;
    }

    /**
     * Get file path pattern(s)
     *
     * @return string|array<string>|null Pattern(s) to match file paths against
     */
    public function path(): string|array|null
    {
        return $this->path;
    }

    /**
     * Get excluded path pattern(s)
     *
     * @return array<string>|null Pattern(s) to exclude file paths
     */
    public function notPath(): array|null
    {
        return $this->notPath;
    }

    /**
     * Get content pattern(s)
     *
     * @return string|array<string>|null Pattern(s) to match file content against
     */
    public function contains(): string|array|null
    {
        return $this->contains;
    }

    /**
     * Get excluded content pattern(s)
     *
     * @return string|array<string>|null Pattern(s) to exclude file content
     */
    public function notContains(): string|array|null
    {
        return $this->notContains;
    }

    /**
     * Get size constraint(s)
     *
     * @return string|array<string>|null Size constraint(s)
     */
    public function size(): string|array|null
    {
        return null;
    }

    /**
     * Get date constraint(s)
     *
     * @return string|array<string>|null Date constraint(s)
     */
    public function date(): string|array|null
    {
        return null;
    }

    /**
     * Get directories to search in
     *
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
     * @return false Always false for GitHub sources
     */
    public function ignoreUnreadableDirs(): bool
    {
        return false; // Not applicable for GitHub sources
    }

    /**
     * Serialize to JSON
     *
     * @return array<string, mixed> JSON serializable array
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'github',
            ...parent::jsonSerialize(),
            'repository' => $this->repository,
            'sourcePaths' => $this->sourcePaths,
            'branch' => $this->branch,
            'filePattern' => $this->filePattern,
            'notPath' => $this->notPath,
            'path' => $this->path,
            'contains' => $this->contains,
            'notContains' => $this->notContains,
            'showTreeView' => $this->showTreeView,
            'githubToken' => $this->githubToken,
        ], static fn($value) => $value !== null && (!\is_array($value) || !empty($value)));
    }
}
