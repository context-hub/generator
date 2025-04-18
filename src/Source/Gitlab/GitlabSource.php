<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab;

use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Source\Gitlab\Config\ServerConfig;
use Butschster\ContextGenerator\Source\SourceWithModifiers;

/**
 * Source for GitLab repositories
 */
final class GitlabSource extends SourceWithModifiers implements FilterableSourceInterface
{
    /**
     * @param string $repository GitLab repository in format "group/project"
     * @param string|array<string> $sourcePaths Path(s) within the repository to include
     * @param string $branch Branch or tag to fetch from (default: main)
     * @param string $description Human-readable description
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $notPath Patterns to exclude files
     * @param string|array|null $path Pattern(s) to include only files in specific paths
     * @param string|array|null $contains Pattern(s) to include only files containing specific content
     * @param string|array|null $notContains Pattern(s) to exclude files containing specific content
     * @param bool $showTreeView Whether to show directory tree
     * @param string|ServerConfig|null $server Reference to a server configuration
     * @param array<Modifier> $modifiers Identifiers for content modifiers to apply
     * @param array<non-empty-string> $tags
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
        public readonly string|ServerConfig|null $server = null,
        array $modifiers = [],
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags, modifiers: $modifiers);
    }

    public function name(): string|array|null
    {
        return $this->filePattern;
    }

    public function path(): string|array|null
    {
        return $this->path;
    }

    public function notPath(): array|null
    {
        return $this->notPath;
    }

    public function contains(): string|array|null
    {
        return $this->contains;
    }

    public function notContains(): string|array|null
    {
        return $this->notContains;
    }

    public function size(): string|array|null
    {
        return null;
    }

    public function date(): string|array|null
    {
        return null;
    }

    public function in(): array|null
    {
        return (array) $this->sourcePaths;
    }

    public function files(): array|null
    {
        return null; // GitLab source treats all sourcePaths as directories
    }

    public function ignoreUnreadableDirs(): bool
    {
        return false; // Not applicable for GitLab sources
    }

    public function maxFiles(): int
    {
        return 0; // Unlimited files
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'gitlab',
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
            'server' => $this->server,
        ], static fn($value) => $value !== null && (!\is_array($value) || !empty($value)));
    }
}
