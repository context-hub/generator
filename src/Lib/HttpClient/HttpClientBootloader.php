<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class HttpClientBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            HttpClientInterface::class => static fn(
                Client $httpClient,
                RequestFactoryInterface $requestFactory,
                StreamFactoryInterface $streamFactory,
            ) => new Psr18Client(httpClient: $httpClient, requestFactory: $requestFactory, streamFactory: $streamFactory),

            HttpFactory::class => HttpFactory::class,
            RequestFactoryInterface::class => HttpFactory::class,
            ResponseFactoryInterface::class => HttpFactory::class,
            ServerRequestFactoryInterface::class => HttpFactory::class,
            StreamFactoryInterface::class => HttpFactory::class,
            UploadedFileFactoryInterface::class => HttpFactory::class,
            UriFactoryInterface::class => HttpFactory::class,
        ];
    }
}
