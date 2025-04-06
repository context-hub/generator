<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\ComposerClient\ComposerClientInterface;
use Butschster\ContextGenerator\Lib\ComposerClient\FileSystemComposerClient;
use Butschster\ContextGenerator\Source\Composer\Provider\ComposerProviderInterface;
use Butschster\ContextGenerator\Source\Composer\Provider\CompositeComposerProvider;
use Butschster\ContextGenerator\Source\Composer\Provider\LocalComposerProvider;
use Spiral\Boot\Bootloader\Bootloader;

final class ComposerClientBootloader extends Bootloader
{
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

            ComposerClientInterface::class => FileSystemComposerClient::class,
        ];
    }
}
