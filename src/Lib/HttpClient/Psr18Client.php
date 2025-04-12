<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient;

use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpRequestException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class Psr18Client implements HttpClientInterface
{
    private const int MAX_REDIRECTS = 5;

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, $headers);
    }

    public function post(string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        return $this->request('POST', $url, $headers, $body);
    }

    public function getWithRedirects(string $url, array $headers = []): HttpResponse
    {
        $redirectCount = 0;
        $currentUrl = $url;

        while (true) {
            $response = $this->get($currentUrl, $headers);

            if (!$response->isRedirect() || $redirectCount >= self::MAX_REDIRECTS) {
                return $response;
            }

            $location = $response->getHeader('Location');
            if ($location === null) {
                throw HttpRequestException::missingRedirectLocation();
            }

            $currentUrl = $location;
            $redirectCount++;
        }
    }

    /**
     * Send a request with the specified method to the URL
     *
     * @param string $method The HTTP method
     * @param string $url The URL to request
     * @param array<string, string> $headers Optional request headers
     * @param string|null $body Optional request body (for POST, PUT, etc.)
     * @return HttpResponse The response object
     *
     * @throws HttpException If the request fails
     */
    private function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        $request = $this->requestFactory->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Add body if provided and stream factory is available
        if ($body !== null) {
            $bodyStream = $this->streamFactory->createStream($body);
            $request = $request->withBody($bodyStream);
        }

        try {
            $response = $this->httpClient->sendRequest($request);

            $responseHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $responseHeaders[$name] = \implode(', ', $values);
            }

            return new HttpResponse(
                statusCode: $response->getStatusCode(),
                body: $response->getBody()->getContents(),
                headers: $responseHeaders,
            );
        } catch (\Throwable $e) {
            throw new HttpException(\sprintf('Failed to request URL "%s": %s', $url, $e->getMessage()), 0, $e);
        }
    }
}
