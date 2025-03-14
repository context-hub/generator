<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient;

use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpRequestException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final readonly class Psr18Client implements HttpClientInterface
{
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private UriFactoryInterface $uriFactory,
    ) {}

    public function get(string $url, array $headers = []): HttpResponse
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
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
}
