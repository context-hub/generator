<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpResponse;

final class MockHttpClient implements HttpClientInterface
{
    /** @var array<string, HttpResponse> */
    private array $responses = [];

    /** @var array<string, array<string, string>> */
    private array $requestHeaders = [];

    public function get(string $url, array $headers = []): HttpResponse
    {
        $this->requestHeaders[$url] = $headers;

        // Default response
        return $this->responses[$url] ?? new HttpResponse(
            statusCode: 200,
            body: "Mock content for {$url}",
            headers: [],
        );
    }

    public function post(string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $this->requestHeaders[$url] = $headers;

        // Default response
        return $this->responses[$url] ?? new HttpResponse(
            statusCode: 200,
            body: "Mock POST response for {$url}",
            headers: [],
        );
    }

    public function getWithRedirects(string $url, array $headers = []): HttpResponse
    {
        return $this->get($url, $headers);
    }

    public function addResponse(string $url, HttpResponse $response): void
    {
        $this->responses[$url] = $response;
    }

    public function getRequestHeaders(string $url): array
    {
        return $this->requestHeaders[$url] ?? [];
    }
}
