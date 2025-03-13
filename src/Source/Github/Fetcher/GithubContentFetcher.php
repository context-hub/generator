<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github\Fetcher;

use Butschster\ContextGenerator\Source\Github\GithubSource;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Implementation of GitHub content fetcher using PSR HTTP clients
 */
final readonly class GithubContentFetcher implements GithubContentFetcherInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private UriFactoryInterface $uriFactory,
        private ?string $githubToken = null,
    ) {}

    /**
     * Fetch file content from GitHub
     *
     * @param GithubSource $source The GitHub source
     * @param string $path File path within the repository
     * @return string The file content
     * @throws \RuntimeException If fetching the content fails
     */
    public function fetchContent(GithubSource $source, string $path): string
    {
        try {
            $rawUrl = $source->getRawContentUrl($path);

            // Create and send the request
            $request = $this->requestFactory->createRequest('GET', $this->uriFactory->createUri($rawUrl));

            // Add headers
            foreach ($source->getAuthHeaders($this->githubToken) as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            // Send the request
            $response = $this->httpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(
                    \sprintf('Failed to fetch file content with status code %d: %s', $statusCode, $path),
                );
            }

            return (string) $response->getBody();
        } catch (ClientExceptionInterface $e) {
            throw new \RuntimeException(
                \sprintf('Error fetching file %s: %s', $path, $e->getMessage()),
                previous: $e,
            );
        }
    }
}
