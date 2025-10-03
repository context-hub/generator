<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github;

use Symfony\Component\Finder\SplFileInfo;

/**
 * GitHub file information wrapper
 *
 * Extends SplFileInfo to provide GitHub-specific metadata
 */
final class GithubFileInfo extends SplFileInfo
{
    private ?string $fetchedContent = null;

    /**
     * Create a new GitHub file info instance
     *
     * @param string $relativePath Relative path
     * @param string $relativePathname Relative pathname
     * @param array<string, mixed> $metadata GitHub file metadata
     */
    public function __construct(
        string $relativePath,
        string $relativePathname,
        private readonly array $metadata,
        private readonly \Closure $content,
    ) {
        parent::__construct($relativePath, $relativePath, $relativePathname);
    }

    /**
     * Get the file content
     */
    #[\Override]
    public function getContents(): string
    {
        if ($this->fetchedContent) {
            return $this->fetchedContent;
        }

        return \call_user_func(callback: $this->content);
    }

    /**
     * Get the file size
     */
    public function getSize(): int
    {
        return $this->metadata['size'] ?? 0;
    }

    /**
     * Get the file type
     */
    public function getType(): string
    {
        return $this->metadata['type'] ?? 'file';
    }

    /**
     * Check if the file is a directory
     */
    public function isDir(): bool
    {
        return $this->getType() === 'dir';
    }

    /**
     * Get the file URL on GitHub
     */
    public function getGithubUrl(): string
    {
        return $this->metadata['html_url'] ?? '';
    }

    /**
     * Get the file API URL
     */
    public function getApiUrl(): string
    {
        return $this->metadata['url'] ?? '';
    }

    /**
     * Get the raw file URL
     */
    public function getRawUrl(): string
    {
        return $this->metadata['download_url'] ?? '';
    }

    /**
     * Get the file SHA
     */
    public function getSha(): string
    {
        return $this->metadata['sha'] ?? '';
    }
}
