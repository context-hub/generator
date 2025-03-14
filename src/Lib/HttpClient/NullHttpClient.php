<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient;

/**
 * A null implementation of HttpClientInterface that returns predictable failure responses.
 * This is used when no proper HTTP client implementation is available.
 */
final readonly class NullHttpClient implements HttpClientInterface
{
    /**
     * Always throws an exception as this client cannot perform actual requests
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        throw new Exception\HttpClientNotAvailableException();
    }

    /**
     * Always throws an exception as this client cannot perform actual requests
     */
    public function getWithRedirects(string $url, array $headers = []): HttpResponse
    {
        throw new Exception\HttpClientNotAvailableException();
    }
}
