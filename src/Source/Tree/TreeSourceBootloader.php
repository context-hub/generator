<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Tree;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\FactoryInterface;

final class TreeSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            TreeSourceFetcher::class => static fn(
                FactoryInterface $factory,
                DirectoriesInterface $dirs,
            ): TreeSourceFetcher => $factory->make(TreeSourceFetcher::class, [
                'basePath' => (string) $dirs->getRootPath(),
            ]),
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
