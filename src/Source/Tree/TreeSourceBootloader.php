<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Tree;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class TreeSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            TreeSourceFetcher::class => static fn(
                Directories $dirs,
                ContentBuilderFactory $builderFactory,
                HasPrefixLoggerInterface $logger,
            ): TreeSourceFetcher => new TreeSourceFetcher(
                basePath: $dirs->rootPath,
                builderFactory: $builderFactory,
                logger: $logger->withPrefix('tree-source'),
            ),
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        TreeSourceFactory $factory,
    ): void {
        $registry->register(TreeSourceFetcher::class);
        $sourceRegistry->register($factory);
    }
}
