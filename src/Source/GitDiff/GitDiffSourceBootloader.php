<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\Application\Bootloader\GitClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceFactory;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\GitSourceInterface;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\CommitGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\FileAtCommitGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\StagedGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\StashGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\TimeRangeGitSource;
use Butschster\ContextGenerator\Source\GitDiff\Fetcher\Source\UnstagedGitSource;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\FactoryInterface;

final class GitDiffSourceBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [GitClientBootloader::class];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            GitDiffSourceFetcher::class => GitDiffSourceFetcher::class,
            GitSourceInterface::class => CommitGitSource::class,

            GitSourceFactory::class => static fn(
                FactoryInterface $factory,
                StashGitSource $stashGitSource,
                CommitGitSource $commitGitSource,
                StagedGitSource $stagedGitSource,
                UnstagedGitSource $unstagedGitSource,
                TimeRangeGitSource $timeRangeGitSource,
                FileAtCommitGitSource $fileAtCommitGitSource,
            ) => $factory->make(GitSourceFactory::class, [
                'sources' => [
                    $stashGitSource,
                    $commitGitSource,
                    $stagedGitSource,
                    $unstagedGitSource,
                    $timeRangeGitSource,
                    $fileAtCommitGitSource,
                ],
            ]),
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
