<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab;

use Symfony\Component\Finder\SplFileInfo;

/**
 * GitLab file information wrapper
 *
 * Extends SplFileInfo to provide GitLab-specific metadata
 */
final class GitlabFileInfo extends SplFileInfo
{
    private ?string $fetchedContent = null;

    /**
     * Create a new GitLab file info instance
     *
     * @param string $relativePath Relative path
     * @param string $relativePathname Relative pathname
     * @param array<string, mixed> $metadata GitLab file metadata
     */
    public function __construct(
        string $relativePath,
        string $relativePathname,
        private readonly array $metadata,
        private readonly \Closure $content,
    ) {
        parent::__construct($relativePath, $relativePath, $relativePathname);
    }

    #[\Override]
    public function getContents(): string
    {
        if ($this->fetchedContent !== null) {
            return $this->fetchedContent;
        }

        return $this->fetchedContent = \call_user_func($this->content);
    }

    public function getSize(): int
    {
        return $this->metadata['size'] ?? 0;
    }

    public function getType(): string
    {
        return $this->metadata['type'] ?? 'blob';
    }

    public function isDir(): bool
    {
        return $this->getType() === 'tree';
    }

    public function getGitlabUrl(): string
    {
        return $this->metadata['web_url'] ?? '';
    }

    public function getPath(): string
    {
        return $this->metadata['path'] ?? '';
    }

    public function getName(): string
    {
        return $this->metadata['name'] ?? '';
    }

    public function getId(): string
    {
        return $this->metadata['id'] ?? '';
    }

    public function getMode(): string
    {
        return $this->metadata['mode'] ?? '';
    }
}
