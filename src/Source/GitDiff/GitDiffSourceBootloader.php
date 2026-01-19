<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff;

use Butschster\ContextGenerator\JsonSchema\Combinator\OneOf;
use Butschster\ContextGenerator\JsonSchema\Definition\OneOfDefinition;
use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\BooleanProperty;
use Butschster\ContextGenerator\JsonSchema\Property\IntegerProperty;
use Butschster\ContextGenerator\JsonSchema\Property\ObjectProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Butschster\ContextGenerator\Lib\Git\GitClientBootloader;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
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
    #[\Override]
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
        SchemaDefinitionRegistry $schemaRegistry,
    ): void {
        $registry->register(GitDiffSourceFetcher::class);
        $sourceRegistry->register($factory);
        $this->registerSchema($schemaRegistry);
    }

    private function registerSchema(SchemaDefinitionRegistry $registry): void
    {
        $this->registerGitDiffRender($registry);
        $this->registerGitDiffSource($registry);
    }

    private function registerGitDiffRender(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('gitDiffRender')) {
            return;
        }

        $registry->register(OneOfDefinition::create(
            'gitDiffRender',
            ObjectProperty::new()
                ->property('strategy', StringProperty::new()
                    ->enum(['raw', 'llm'])
                    ->description('The strategy to use for rendering diffs')
                    ->default('raw'))
                ->property('showStats', BooleanProperty::new()
                    ->description('Whether to show file stats in the output')
                    ->default(true))
                ->property('showLineNumbers', BooleanProperty::new()
                    ->description('Whether to show line numbers in diff output')
                    ->default(false))
                ->property('contextLines', IntegerProperty::new()
                    ->description('Number of context lines to show around changes')
                    ->minimum(0)
                    ->default(3)),
            StringProperty::new()
                ->enum(['raw', 'llm'])
                ->description('The strategy to use for rendering diffs (shorthand)'),
        ));
    }

    private function registerGitDiffSource(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('gitDiffSource')) {
            return;
        }

        $commitEnum = [
            'last', 'last-5', 'last-10', 'last-week', 'last-month',
            'unstaged', 'staged', 'wip', 'main-diff', 'master-diff',
            'develop-diff', 'today', 'last-24h', 'yesterday',
            'last-2weeks', 'last-quarter', 'last-year',
            'stash', 'stash-last', 'stash-1', 'stash-2', 'stash-3',
            'stash-all', 'stash-latest-2', 'stash-latest-3', 'stash-latest-5',
            'stash-before-pull', 'stash-wip', 'stash-untracked', 'stash-index',
        ];

        $registry->define('gitDiffSource')
            ->property('repository', StringProperty::new()
                ->description('Path to the git repository'))
            ->property('render', RefProperty::to('gitDiffRender')
                ->description('Configuration for rendering diffs'))
            ->property('commit', StringProperty::new()
                ->enum($commitEnum)
                ->description('Git commit range')
                ->default('staged'))
            ->property('filePattern', RefProperty::to('patternConstraint'))
            ->property('path', RefProperty::to('patternConstraint'))
            ->property('notPath', ArrayProperty::strings()
                ->description('Patterns to exclude files by path')
                ->default([]))
            ->property('contains', RefProperty::to('patternConstraint'))
            ->property('notContains', RefProperty::to('patternConstraint'))
            ->property('showStats', BooleanProperty::new()
                ->description('Whether to show commit stats in output')
                ->default(true))
            ->property('modifiers', RefProperty::to('modifiers'));
    }
}
