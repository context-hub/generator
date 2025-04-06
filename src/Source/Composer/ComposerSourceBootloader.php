<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer;

use Butschster\ContextGenerator\Application\Bootloader\ComposerClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\DirectoriesInterface;
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
        $registry->register(ComposerSourceFetcher::class);
        $sourceRegistry->register($factory);
    }
}
