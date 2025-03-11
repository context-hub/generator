<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;

/**
 * Source for git commit diffs with enhanced commit range support
 */
class CommitDiffSource extends BaseSource implements FilterableSourceInterface, \JsonSerializable
{
    /**
     * @param string $repository Path to the git repository
     * @param string $description Human-readable description
     * @param string|array<string> $commit Git commit range (e.g., 'HEAD~5..HEAD', specific commit hash, preset alias, or array of commits)
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $notPath Patterns to exclude files
     * @param string|array<string> $path Patterns to include only specific paths
     * @param string|array<string> $contains Patterns to include files containing specific content
     * @param string|array<string> $notContains Patterns to exclude files containing specific content
     * @param bool $showStats Whether to show commit stats in output
     */
    public function __construct(
        public readonly string $repository = '.',
        string $description = '',
        public readonly string|array $commit = 'staged',
        public readonly string|array $filePattern = '*.*',
        public readonly array $notPath = [],
        public readonly string|array $path = [],
        public readonly string|array $contains = [],
        public readonly string|array $notContains = [],
        public readonly bool $showStats = true,
    ) {
        parent::__construct($description);
    }

    /**
     * Create a GitCommitDiffSource from an array configuration
     */
    public static function fromArray(array $data, string $rootPath = ''): self
    {
        $repository = $data['repository'] ?? '.';
        if (!\is_string($repository)) {
            throw new \RuntimeException('"repository" must be a string in source');
        }

        // Prepend root path if repository is relative
        $repository = \str_starts_with($repository, '/')
            ? $repository
            : $rootPath . '/' . \trim($repository, '/');

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

        return new self(
            repository: $repository,
            description: $data['description'] ?? '',
            commit: $data['commit'] ?? 'staged',
            filePattern: $data['filePattern'] ?? '*.*',
            notPath: $data['notPath'] ?? [],
            path: $data['path'] ?? [],
            contains: $data['contains'] ?? [],
            notContains: $data['notContains'] ?? [],
            showStats: $data['showStats'] ?? true,
        );
    }

    /**
     * Get the commit parameter as specified (without resolving)
     *
     * This is needed when serializing the source to avoid resolving aliases
     */
    public function getCommit(): string|array
    {
        return $this->commit;
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
     * @return string|string[] Pattern(s) to match file paths against
     *
     * @psalm-return array<string>|string
     */
    public function path(): string|array|null
    {
        return $this->path;
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
        return $this->notPath;
    }

    /**
     * Get content pattern(s)
     *
     * @return string|string[] Pattern(s) to match file content against
     *
     * @psalm-return array<string>|string
     */
    public function contains(): string|array|null
    {
        return $this->contains;
    }

    /**
     * Get excluded content pattern(s)
     *
     * @return string|string[] Pattern(s) to exclude file content
     *
     * @psalm-return array<string>|string
     */
    public function notContains(): string|array|null
    {
        return $this->notContains;
    }

    /**
     * Get size constraint(s) - not applicable for git diffs
     */
    public function size(): string|array|null
    {
        return null;
    }

    /**
     * Get date constraint(s) - not applicable for git diffs
     */
    public function date(): string|array|null
    {
        return null;
    }

    /**
     * Get directories to search in - not applicable for git diffs
     */
    public function in(): array|null
    {
        return null;
    }

    /**
     * Get individual files to include - not applicable for git diffs
     */
    public function files(): array|null
    {
        return null;
    }

    /**
     * Check if unreadable directories should be ignored - not applicable for git diffs
     */
    public function ignoreUnreadableDirs(): bool
    {
        return false;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'git_diff',
            'repository' => $this->repository,
            'description' => $this->description,
            'commitRange' => $this->commit,
            'filePattern' => $this->filePattern,
            'notPath' => $this->notPath,
            'path' => $this->path,
            'contains' => $this->contains,
            'notContains' => $this->notContains,
            'showStats' => $this->showStats,
        ];
    }
}
