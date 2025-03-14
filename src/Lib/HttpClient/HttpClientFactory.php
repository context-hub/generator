<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final readonly class HttpClientFactory
{
    /**
     * Create an appropriate HTTP client implementation based on available dependencies
     */
    public static function create(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?UriFactoryInterface $uriFactory = null,
    ): HttpClientInterface {
        // If all required dependencies are available, use the PSR-18 client
        if ($httpClient !== null && $requestFactory !== null && $uriFactory !== null) {
            return new Psr18Client($httpClient, $requestFactory, $uriFactory);
        }

        // Otherwise, return the null implementation
        return new NullHttpClient();
    }
}
