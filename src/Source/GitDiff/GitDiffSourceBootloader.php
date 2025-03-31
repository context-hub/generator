<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class GitDiffSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            GitDiffSourceFetcher::class => static fn(
                ContentBuilderFactory $builderFactory,
                HasPrefixLoggerInterface $logger,
            ): GitDiffSourceFetcher => new GitDiffSourceFetcher(
                builderFactory: $builderFactory,
                logger: $logger->withPrefix('commit-diff-source'),
            ),
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        GitDiffSourceFactory $factory,
    ): void {
        $registry->register(GitDiffSourceFetcher::class);
        $sourceRegistry->register($factory);
    }
}
