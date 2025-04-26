<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Docs;

use Butschster\ContextGenerator\Application\Bootloader\HttpClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
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
                ContentBuilderFactory $builderFactory,
                HttpClientInterface $httpClient,
                VariableResolver $variables,
                HasPrefixLoggerInterface $logger,
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
