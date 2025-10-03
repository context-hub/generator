<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Context7Client;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Context7Client\Exception\Context7ClientException;
use Butschster\ContextGenerator\Lib\Context7Client\Model\LibrarySearchResult;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final readonly class Context7Client implements Context7ClientInterface
{
    private const string API_BASE_URL = 'https://context7.com/api/v1';
    private const string DEFAULT_TYPE = 'txt';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[LoggerPrefix(prefix: 'context7-client')]
        private LoggerInterface $logger,
    ) {}

    public function searchLibraries(string $query, int $maxResults = 2): LibrarySearchResult
    {
        if (empty(\trim(string: $query))) {
            throw new \InvalidArgumentException(message: 'Search query cannot be empty');
        }

        $url = self::API_BASE_URL . '/search?' . \http_build_query(data: ['query' => $query]);

        $this->logger->debug('Sending request to Context7 search API', [
            'url' => $url,
            'maxResults' => $maxResults,
        ]);

        try {
            $response = $this->httpClient->get($url, [
                'User-Agent' => 'CTX Bot',
                'Accept' => 'application/json',
                'X-Context7-Source' => 'mcp-server',
            ]);

            if (!$response->isSuccess()) {
                $this->handleErrorResponse(statusCode: $response->getStatusCode(), query: $query);
            }

            $data = $response->getJson();

            $this->logger->info('Documentation libraries found', [
                'count' => \count(value: $data['results'] ?? []),
                'query' => $query,
            ]);

            return LibrarySearchResult::fromArray(data: $data, maxResults: $maxResults);
        } catch (Context7ClientException $e) {
            // Re-throw our own exceptions
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Error searching documentation libraries', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw Context7ClientException::searchFailed(query: $query, statusCode: 0);
        }
    }

    public function fetchLibraryDocumentation(string $libraryId, ?int $tokens = null, ?string $topic = null): string
    {
        if (empty(\trim(string: $libraryId))) {
            throw new \InvalidArgumentException(message: 'Library ID cannot be empty');
        }

        // Remove leading slash from libraryId if present (as per JS reference)
        $libraryId = \ltrim(string: $libraryId, characters: '/');

        $url = $this->buildDocumentationUrl(libraryId: $libraryId, tokens: $tokens, topic: $topic);

        $this->logger->debug('Fetching library documentation from Context7 API', [
            'libraryId' => $libraryId,
            'tokens' => $tokens,
            'topic' => $topic,
            'url' => $url,
        ]);

        try {
            $response = $this->httpClient->get($url, [
                'Accept' => 'text/plain',
                'User-Agent' => 'CTX Bot',
                'X-Context7-Source' => 'mcp-server',
            ]);

            if (!$response->isSuccess()) {
                $this->handleDocumentationErrorResponse(statusCode: $response->getStatusCode(), libraryId: $libraryId);
            }

            $documentation = $response->getBody();

            if ($this->isEmptyDocumentation(documentation: $documentation)) {
                $this->logger->warning('No documentation found for library', [
                    'libraryId' => $libraryId,
                ]);
                throw Context7ClientException::noDocumentationFound(libraryId: $libraryId);
            }

            $this->logger->info('Library documentation fetched successfully', [
                'libraryId' => $libraryId,
                'documentationLength' => \strlen(string: $documentation),
            ]);

            return $documentation;
        } catch (Context7ClientException $e) {
            // Re-throw our own exceptions
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching library documentation', [
                'libraryId' => $libraryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw Context7ClientException::documentationFetchFailed(libraryId: $libraryId, statusCode: 0);
        }
    }

    private function buildDocumentationUrl(string $libraryId, ?int $tokens, ?string $topic): string
    {
        $url = self::API_BASE_URL . '/' . $libraryId;
        $params = ['type' => self::DEFAULT_TYPE];

        if ($tokens !== null) {
            $params['tokens'] = (string) $tokens;
        }

        if ($topic !== null && $topic !== '') {
            $params['topic'] = $topic;
        }

        return $url . '?' . \http_build_query(data: $params);
    }

    private function handleErrorResponse(int $statusCode, string $query): void
    {
        $this->logger->error('Context7 search request failed', [
            'statusCode' => $statusCode,
            'query' => $query,
        ]);

        match ($statusCode) {
            429 => throw Context7ClientException::rateLimited(),
            401 => throw Context7ClientException::unauthorized(),
            default => throw Context7ClientException::searchFailed(query: $query, statusCode: $statusCode),
        };
    }

    private function handleDocumentationErrorResponse(int $statusCode, string $libraryId): void
    {
        $this->logger->error('Context7 library documentation request failed', [
            'statusCode' => $statusCode,
            'libraryId' => $libraryId,
        ]);

        match ($statusCode) {
            429 => throw Context7ClientException::rateLimited(),
            401 => throw Context7ClientException::unauthorized(),
            404 => throw Context7ClientException::libraryNotFound(libraryId: $libraryId),
            default => throw Context7ClientException::documentationFetchFailed(libraryId: $libraryId, statusCode: $statusCode),
        };
    }

    private function isEmptyDocumentation(string $documentation): bool
    {
        return empty($documentation) ||
            $documentation === 'No content available' ||
            $documentation === 'No context data available';
    }
}
