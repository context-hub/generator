<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Config\RenderConfig;
use Butschster\ContextGenerator\Source\SourceWithModifiers;

/**
 * Source for git commit diffs with simplified commit range support
 */
final class GitDiffSource extends SourceWithModifiers implements FilterableSourceInterface, \JsonSerializable
{
    /**
     * @param string $repository Path to the git repository
     * @param string $description Human-readable description
     * @param string $commit Git commit range (e.g., 'HEAD~5..HEAD', specific commit hash, preset alias)
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $notPath Patterns to exclude files
     * @param string|array<string> $path Patterns to include only specific paths
     * @param string|array<string> $contains Patterns to include files containing specific content
     * @param string|array<string> $notContains Patterns to exclude files containing specific content
     * @param RenderConfig|null $renderConfig Configuration for rendering diffs
     * @param array<Modifier> $modifiers Identifiers for content modifiers to apply
     * @param array<non-empty-string> $tags
     */
    public function __construct(
        public readonly string $repository = '.',
        string $description = '',
        public readonly string $commit = 'staged',
        public readonly string|array $filePattern = '*.*',
        public readonly array $notPath = [],
        public readonly string|array $path = [],
        public readonly string|array $contains = [],
        public readonly string|array $notContains = [],
        public readonly ?RenderConfig $renderConfig = null,
        array $modifiers = [],
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags, modifiers: $modifiers);
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function name(): string|array|null
    {
        return $this->filePattern;
    }

    public function path(): string|array|null
    {
        return $this->path;
    }

    public function notPath(): string|array|null
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
        return null;
    }

    public function files(): array|null
    {
        return null;
    }

    public function ignoreUnreadableDirs(): bool
    {
        return false;
    }

    public function maxFiles(): int
    {
        return 0; //todo Add support for max files
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'git_diff',
            ...parent::jsonSerialize(),
            'repository' => $this->repository,
            'commitRange' => $this->commit,
            'filePattern' => $this->filePattern,
            'notPath' => $this->notPath,
            'path' => $this->path,
            'contains' => $this->contains,
            'notContains' => $this->notContains,
            'render' => $this->renderConfig,
        ], static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
