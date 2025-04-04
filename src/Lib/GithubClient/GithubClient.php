<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GithubClient;

use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;

final class GithubClient implements GithubClientInterface
{
    /** GitHub API base URL */
    private const string API_BASE_URL = 'https://api.github.com';

    /**
     * @param HttpClientInterface $httpClient HTTP client for API requests
     * @param string|null $token GitHub API token
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private ?string $token = null,
    ) {}

    public function getContents(string $owner, string $repo, string $path = '', string $branch = 'main'): array
    {
        $url = \sprintf(
            '/repos/%s/%s/contents/%s?ref=%s',
            \urlencode($owner),
            \urlencode($repo),
            $path ? \urlencode($path) : '',
            \urlencode($branch),
        );

        $response = $this->sendRequest('GET', $url);

        /**
         * Check if we got a single file or a directory
         */
        if (isset($response['type']) && $response['type'] === 'file') {
            return [$response];
        }

        return $response;
    }

    public function getFileContent(string $owner, string $repo, string $path, string $branch = 'main'): string
    {
        $url = \sprintf(
            '/repos/%s/%s/contents/%s?ref=%s',
            \urlencode($owner),
            \urlencode($repo),
            \urlencode($path),
            \urlencode($branch),
        );

        $response = $this->sendRequest('GET', $url);

        if (!isset($response['content'])) {
            throw new \RuntimeException("Could not get content for file: $path");
        }

        // GitHub API returns base64 encoded content
        return (string) \base64_decode((string) $response['content'], true);
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getReleaseManager(GithubRepository $repository): ReleaseManager
    {
        return new ReleaseManager(
            $this->httpClient,
            $repository,
            $this->token,
        );
    }

    /**
     * Send an HTTP request to the GitHub API
     *
     * @param string $method HTTP method
     * @param string $path API path
     * @return array<string, mixed> JSON response data
     * @throws \RuntimeException If the request fails
     */
    private function sendRequest(string $method, string $path): array
    {
        $url = self::API_BASE_URL . $path;

        // Add headers
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'ContextGenerator',
        ];

        // Add authentication if token is provided
        if ($this->token) {
            $headers['Authorization'] = 'token ' . $this->token;
        }

        // Send the request
        try {
            $response = $this->httpClient->get($url, $headers);

            // Check for success status code
            if (!$response->isSuccess()) {
                throw new \RuntimeException(
                    "GitHub API request failed with status code " . $response->getStatusCode(),
                );
            }

            // Parse JSON response
            return $response->getJson();
        } catch (\Throwable $e) {
            throw new \RuntimeException('GitHub API request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
