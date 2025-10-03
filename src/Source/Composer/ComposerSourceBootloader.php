<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer;

use Butschster\ContextGenerator\Lib\ComposerClient\ComposerClientBootloader;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Source\Composer\Provider\ComposerProviderInterface;
use Butschster\ContextGenerator\Source\Composer\Provider\CompositeComposerProvider;
use Butschster\ContextGenerator\Source\Composer\Provider\LocalComposerProvider;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\FactoryInterface;

final class ComposerSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [ComposerClientBootloader::class];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ComposerProviderInterface::class => static fn(
                HasPrefixLoggerInterface $logger,
                LocalComposerProvider $localProvider,
            ) => new CompositeComposerProvider(
                logger: $logger->withPrefix('composer-provider'),
                localProvider: $localProvider,
            ),

            ComposerSourceFetcher::class => static fn(
                FactoryInterface $factory,
                DirectoriesInterface $dirs,
            ): ComposerSourceFetcher => $factory->make(ComposerSourceFetcher::class, [
                'basePath' => (string) $dirs->getRootPath(),
            ]),
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        ComposerSourceFactory $factory,
    ): void {
        $registry->register(fetcher: ComposerSourceFetcher::class);
        $sourceRegistry->register($factory);
    }
}
