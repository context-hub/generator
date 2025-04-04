<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Lib\GithubClient\Model\Release;
use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;

/**
 * Manages GitHub releases for a repository.
 */
final class ReleaseManager
{
    /**
     * @param HttpClientInterface $httpClient HTTP client for API requests
     * @param GithubRepository $repository Target repository
     * @param string|null $token GitHub API token
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GithubRepository $repository,
        private ?string $token = null,
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

        trap($url);

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
     * Download a release asset to a local file.
     *
     * @param string $assetUrl URL of the asset to download
     * @param string $destinationPath Local path to save the file
     * @throws \RuntimeException If the download fails
     */
    public function downloadAsset(string $assetUrl, string $destinationPath): void
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
