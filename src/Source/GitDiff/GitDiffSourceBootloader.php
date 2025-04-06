<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\GitDiff\Git\GitClient;
use Butschster\ContextGenerator\Source\GitDiff\Git\GitClientInterface;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class GitDiffSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            GitClientInterface::class => GitClient::class,
            GitDiffSourceFetcher::class => GitDiffSourceFetcher::class,
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
