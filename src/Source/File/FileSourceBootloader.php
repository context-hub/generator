<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\FactoryInterface;

final class FileSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            FileSourceFetcher::class => static fn(
                FactoryInterface $factory,
                DirectoriesInterface $dirs,
                ContentBuilderFactory $builderFactory,
                HasPrefixLoggerInterface $logger,
            ): FileSourceFetcher => $factory->make(FileSourceFetcher::class, [
                'basePath' => (string) $dirs->getRootPath(),
                'finder' => $factory->make(SymfonyFinder::class),
            ]),
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        FileSourceFactory $factory,
    ): void {
        $registry->register(FileSourceFetcher::class);
        $sourceRegistry->register($factory);
    }
}
