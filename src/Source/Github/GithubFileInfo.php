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
    /**
     * GitHub file metadata
     *
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * Create a new GitHub file info instance
     *
     * @param string $file File path
     * @param string $relativePath Relative path
     * @param string $relativePathname Relative pathname
     * @param array<string, mixed> $metadata GitHub file metadata
     */
    public function __construct(
        string $file,
        string $relativePath,
        string $relativePathname,
        array $metadata = [],
    ) {
        parent::__construct($file, $relativePath, $relativePathname);
        $this->metadata = $metadata;
    }

    /**
     * Get the file content
     */
    public function getContents(): string
    {
        return $this->metadata['content'] ?? '';
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

    /**
     * Get all metadata
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value
     *
     * @param string $key Metadata key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
