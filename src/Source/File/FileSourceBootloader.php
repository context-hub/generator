<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Spiral\Boot\Bootloader\Bootloader;

final class FileSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            FileSourceFetcher::class => static fn(
                Directories $dirs,
                ContentBuilderFactory $builderFactory,
                HasPrefixLoggerInterface $logger,
            ): FileSourceFetcher => new FileSourceFetcher(
                basePath: $dirs->rootPath,
                builderFactory: $builderFactory,
                logger: $logger->withPrefix('file-source'),
            ),
        ];
    }

    public function init(SourceFetcherBootloader $registry): void
    {
        $registry->register(FileSourceFetcher::class);
    }
}
