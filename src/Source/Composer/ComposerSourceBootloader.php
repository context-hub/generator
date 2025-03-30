<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Source\Composer\Client\FileSystemComposerClient;
use Butschster\ContextGenerator\Source\Composer\Provider\ComposerProviderInterface;
use Butschster\ContextGenerator\Source\Composer\Provider\CompositeComposerProvider;
use Butschster\ContextGenerator\Source\Composer\Provider\LocalComposerProvider;
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
                Directories $dirs,
                ContentBuilderFactory $builderFactory,
                VariableResolver $variables,
                HasPrefixLoggerInterface $logger,
                ComposerProviderInterface $composerProvider,
            ): ComposerSourceFetcher => new ComposerSourceFetcher(
                provider: $composerProvider,
                basePath: $dirs->rootPath,
                builderFactory: $builderFactory,
                variableResolver: $variables,
                logger: $logger->withPrefix('composer-source'),
            ),
        ];
    }

    public function init(SourceFetcherBootloader $registry): void
    {
        $registry->register(ComposerSourceFetcher::class);
    }
}
