<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Url;

use Butschster\ContextGenerator\Application\Bootloader\HttpClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class UrlSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [HttpClientBootloader::class];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            UrlSourceFetcher::class => static fn(
                ContentBuilderFactory $builderFactory,
                HttpClientInterface $httpClient,
                VariableResolver $variables,
                HasPrefixLoggerInterface $logger,
            ): UrlSourceFetcher => new UrlSourceFetcher(
                httpClient: $httpClient,
                variableResolver: $variables,
                builderFactory: $builderFactory,
                logger: $logger->withPrefix('url-source'),
            ),
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        UrlSourceFactory $factory,
    ): void {
        $registry->register(UrlSourceFetcher::class);
        $sourceRegistry->register($factory);
    }
}
