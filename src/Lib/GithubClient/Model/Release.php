<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient\Model;

/**
 * Represents a GitHub release.
 */
final readonly class Release
{
    /**
     * @param string $tagName Release tag name (e.g. "v1.0.0" or "1.0.0")
     * @param string $name Release name
     * @param string $body Release description
     * @param string $htmlUrl HTML URL to view the release
     * @param array<string, string> $assets Release assets (filename => download URL)
     */
    public function __construct(
        public string $tagName,
        public string $name,
        public string $body,
        public string $htmlUrl,
        public array $assets = [],
    ) {}

    /**
     * Create a Release instance from GitHub API response data.
     *
     * @param array<string, mixed> $data GitHub API release data
     */
    public static function fromApiResponse(array $data): self
    {
        $assets = [];

        // Process assets to create a map of filename => download URL
        if (isset($data['assets']) && \is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['name'], $asset['browser_download_url'])) {
                    $assets[(string) $asset['name']] = (string) $asset['browser_download_url'];
                }
            }
        }

        return new self(
            tagName: (string) ($data['tag_name'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            htmlUrl: (string) ($data['html_url'] ?? ''),
            assets: $assets,
        );
    }

    /**
     * Get the version number without the "v" prefix.
     */
    public function getVersion(): string
    {
        return \ltrim($this->tagName, 'v');
    }

    /**
     * Check if this release is newer than the provided version.
     */
    public function isNewerThan(string $currentVersion): bool
    {
        // Clean up versions for comparison
        $currentVersion = \ltrim($currentVersion, 'v');
        $releaseVersion = $this->getVersion();

        return \version_compare($currentVersion, $releaseVersion, '<');
    }

    /**
     * Get the download URL for a specific asset.
     */
    public function getAssetUrl(string $fileName): ?string
    {
        return $this->assets[$fileName] ?? null;
    }
}
