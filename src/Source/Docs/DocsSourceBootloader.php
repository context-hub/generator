<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Docs;

use Butschster\ContextGenerator\Lib\HttpClient\HttpClientBootloader;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\FactoryInterface;

final class DocsSourceBootloader extends Bootloader
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
            DocsSourceFetcher::class => static fn(
                FactoryInterface $factory,
            ): DocsSourceFetcher => $factory->make(DocsSourceFetcher::class, [
                'defaultHeaders' => [
                    'User-Agent' => 'CTX Bot',
                    'Accept' => 'text/plain',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ]),
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        DocsSourceFactory $factory,
    ): void {
        $registry->register(DocsSourceFetcher::class);
        $sourceRegistry->register($factory);
    }
}
