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
use Spiral\Core\FactoryInterface;

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
                FactoryInterface $factory,
                ContentBuilderFactory $builderFactory,
                HttpClientInterface $httpClient,
                VariableResolver $variables,
                HasPrefixLoggerInterface $logger,
            ): UrlSourceFetcher => $factory->make(UrlSourceFetcher::class, [
                'defaultHeaders' => [
                    'User-Agent' => 'Context Generator Bot',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ]),
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
