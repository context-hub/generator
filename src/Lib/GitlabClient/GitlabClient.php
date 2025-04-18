<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\GitlabClient;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\GitlabClient\Model\GitlabRepository;
use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * GitLab API client implementation
 */
final class GitlabClient implements GitlabClientInterface
{
    private string $serverUrl = 'https://gitlab.com';
    private ?string $token = null;

    /**
     * Custom HTTP headers
     *
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Create a new GitLab client
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[LoggerPrefix(prefix: 'gitlab-client')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function getContents(GitlabRepository $repository, string $path = ''): array
    {
        $this->logger?->debug('Getting repository contents', [
            'repository' => $repository->repository,
            'branch' => $repository->branch,
            'path' => $path,
        ]);

        // Format URL for GitLab API v4
        $url = $this->buildApiUrl("/projects/{$repository->projectId}/repository/tree", [
            'ref' => $repository->branch,
            'path' => $path,
            'recursive' => 'false',
        ]);

        $this->logger?->debug('Making GitLab API request', [
            'url' => $url,
        ]);

        $response = $this->makeApiRequest($url);
        $items = \json_decode($response, true);

        if (!\is_array($items)) {
            $errorMessage = "Failed to parse GitLab API response for path: $path";
            $this->logger?->error($errorMessage, [
                'response' => $response,
            ]);
            throw new \RuntimeException($errorMessage);
        }

        $this->logger?->debug('Got repository contents', [
            'itemCount' => \count($items),
        ]);

        return $items;
    }

    public function getFileContent(GitlabRepository $repository, string $path): string
    {
        $this->logger?->debug('Getting file content', [
            'repository' => $repository->repository,
            'branch' => $repository->branch,
            'path' => $path,
        ]);

        // Format URL for GitLab API v4
        $url = $this->buildApiUrl("/projects/{$repository->projectId}/repository/files/" . \rawurlencode($path), [
            'ref' => $repository->branch,
        ]);

        $this->logger?->debug('Making GitLab API request', [
            'url' => $url,
        ]);

        $response = $this->makeApiRequest($url);
        $data = \json_decode($response, true);

        if (!\is_array($data) || !isset($data['content'])) {
            $errorMessage = "Failed to get file content for path: $path";
            $this->logger?->error($errorMessage, [
                'response' => $response,
            ]);
            throw new \RuntimeException($errorMessage);
        }

        // GitLab returns file content as base64 encoded
        $content = \base64_decode((string) $data['content']);

        if ($content === false) {
            $errorMessage = "Failed to decode base64 content for path: $path";
            $this->logger?->error($errorMessage);
            throw new \RuntimeException($errorMessage);
        }

        $this->logger?->debug('Got file content', [
            'contentLength' => \strlen($content),
        ]);

        return $content;
    }

    public function setToken(?string $token): void
    {
        $this->logger?->debug('Setting GitLab API token', [
            'hasToken' => $token !== null,
        ]);
        $this->token = $token;
    }

    public function setServerUrl(string $serverUrl): void
    {
        $this->logger?->debug('Setting GitLab server URL', [
            'serverUrl' => $serverUrl,
        ]);
        $this->serverUrl = \rtrim($serverUrl, '/');
    }

    public function setHeaders(array $headers): void
    {
        $this->logger?->debug('Setting custom HTTP headers', [
            'headerCount' => \count($headers),
            'headers' => \array_keys($headers),
        ]);
        $this->headers = $headers;
    }

    /**
     * Build a GitLab API URL
     *
     * @param string $path API endpoint path
     * @param array<string, string> $queryParams Query parameters
     * @return string Full API URL
     */
    private function buildApiUrl(string $path, array $queryParams = []): string
    {
        $url = "{$this->serverUrl}/api/v4{$path}";

        if (!empty($queryParams)) {
            $url .= '?' . \http_build_query($queryParams);
        }

        return $url;
    }

    /**
     * Make a GET request to the GitLab API
     *
     * @param string $url API URL
     * @return string API response
     * @throws \RuntimeException If the request fails
     */
    private function makeApiRequest(string $url): string
    {
        // Prepare headers
        $headers = $this->prepareHeaders();

        try {
            $response = $this->httpClient->get($url, $headers);

            if (!$response->isSuccess()) {
                $errorMessage = "GitLab API request failed with HTTP code {$response->getStatusCode()}";
                $this->logger?->error($errorMessage, [
                    'url' => $url,
                    'statusCode' => $response->getStatusCode(),
                    'response' => $response->getBody(),
                ]);
                throw new \RuntimeException($errorMessage);
            }

            return $response->getBody();
        } catch (HttpException $e) {
            $errorMessage = "GitLab API request failed: {$e->getMessage()}";
            $this->logger?->error($errorMessage, [
                'url' => $url,
                'exception' => $e,
            ]);
            throw new \RuntimeException($errorMessage, 0, $e);
        }
    }

    /**
     * Prepare request headers for GitLab API
     *
     * @return array<string, string> Headers to send with the request
     */
    private function prepareHeaders(): array
    {
        $headers = $this->headers;

        // Add authorization header if token is set
        if ($this->token !== null) {
            $headers['PRIVATE-TOKEN'] = $this->token;
        }

        return $headers;
    }
}
