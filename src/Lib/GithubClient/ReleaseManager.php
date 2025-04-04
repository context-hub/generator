<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Lib\GithubClient\Model\Release;
use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages GitHub releases for a repository.
 */
final readonly class ReleaseManager
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private GithubRepository $repository,
        private ?string $token = null,
        private BinaryNameBuilder $binaryNameBuilder = new BinaryNameBuilder(),
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get the latest release from GitHub.
     *
     * @throws \RuntimeException If the request fails
     */
    public function getLatestRelease(): Release
    {
        $url = \sprintf(
            'https://api.github.com/repos/%s/releases/latest',
            $this->repository->repository,
        );

        $response = $this->httpClient->get(
            $url,
            $this->getHeaders(),
        );

        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                "Failed to fetch latest release. Server returned status code {$response->getStatusCode()}",
            );
        }

        try {
            $data = $response->getJson();
            return Release::fromApiResponse($data);
        } catch (HttpException $e) {
            throw new \RuntimeException("Failed to parse GitHub response: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Attempts to download the appropriate binary for the current platform.
     * First tries the platform-specific binary, then falls back to the generic binary.
     */
    public function downloadBinary(string $version, string $binaryName, string $type, string $destinationPath): bool
    {
        // First try platform-specific binary
        try {
            $fileName = $this->binaryNameBuilder->buildPlatformSpecificName($binaryName, $version, $type);
            $this->logger?->info("Attempting to download platform-specific binary: {$fileName}");

            $assetUrl = $this->getAssetUrlOrFail($fileName);
            $this->downloadAsset($assetUrl, $destinationPath);

            $this->logger?->info("Successfully downloaded platform-specific binary: {$fileName}");
            return true;
        } catch (\Throwable $e) {
            $this->logger?->warning("Failed to download platform-specific binary: {$e->getMessage()}");

            // Fall back to generic binary
            try {
                $fileName = $this->binaryNameBuilder->buildGenericName($binaryName, $type);
                $this->logger?->info("Falling back to generic binary: {$fileName}");

                $assetUrl = $this->getAssetUrlOrFail($fileName);
                $this->downloadAsset($assetUrl, $destinationPath);

                $this->logger?->info("Successfully downloaded generic binary: {$fileName}");
                return true;
            } catch (\Throwable $e2) {
                $this->logger?->error("Failed to download generic binary: {$e2->getMessage()}");
                throw new \RuntimeException("Failed to download binary: {$e2->getMessage()}", 0, $e2);
            }
        }
    }

    /**
     * Download a release asset to a local file.
     */
    private function downloadAsset(string $assetUrl, string $destinationPath): void
    {
        $response = $this->httpClient->getWithRedirects(
            $assetUrl,
            $this->getHeaders(),
        );

        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                "Failed to download asset. Server returned status code {$response->getStatusCode()}",
            );
        }

        // Write the file
        if (!\file_put_contents($destinationPath, $response->getBody())) {
            throw new \RuntimeException("Failed to write file: {$destinationPath}");
        }

        // Make the file executable if it's not a Windows system
        if (\PHP_OS_FAMILY !== 'Windows') {
            if (!\chmod($destinationPath, 0755)) {
                throw new \RuntimeException("Failed to set executable permissions on the file: {$destinationPath}");
            }
        }
    }

    /**
     * Gets the asset URL for a specific filename or throws an exception if not found.
     *
     * @param string $fileName Name of the file
     * @return string The asset URL
     * @throws \RuntimeException If the asset is not found
     */
    private function getAssetUrlOrFail(string $fileName): string
    {
        $release = $this->getLatestRelease();
        $assetUrl = $release->getAssetUrl($fileName);

        if ($assetUrl === null) {
            throw new \RuntimeException("Could not find asset '{$fileName}' in release {$release->getVersion()}");
        }

        return $assetUrl;
    }

    /**
     * Generate standard headers for GitHub API requests.
     *
     * @return array<string, string> Headers
     */
    private function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'ContextGenerator',
        ];

        if ($this->token) {
            $headers['Authorization'] = 'token ' . $this->token;
        }

        return $headers;
    }
}
