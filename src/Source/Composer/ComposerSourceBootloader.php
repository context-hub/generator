<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\ComposerClient\FileSystemComposerClient;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Source\Composer\Provider\ComposerProviderInterface;
use Butschster\ContextGenerator\Source\Composer\Provider\CompositeComposerProvider;
use Butschster\ContextGenerator\Source\Composer\Provider\LocalComposerProvider;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class ComposerSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ComposerProviderInterface::class => static fn(
                HasPrefixLoggerInterface $logger,
            ) => new CompositeComposerProvider(
                logger: $logger,
                localProvider: new LocalComposerProvider(
                    client: new FileSystemComposerClient(logger: $logger),
                    logger: $logger,
                ),
            ),

            ComposerSourceFetcher::class => static fn(
                DirectoriesInterface $dirs,
                ContentBuilderFactory $builderFactory,
                VariableResolver $variables,
                HasPrefixLoggerInterface $logger,
                ComposerProviderInterface $composerProvider,
            ): ComposerSourceFetcher => new ComposerSourceFetcher(
                provider: $composerProvider,
                basePath: (string) $dirs->getRootPath(),
                builderFactory: $builderFactory,
                variableResolver: $variables,
                logger: $logger->withPrefix('composer-source'),
            ),
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
