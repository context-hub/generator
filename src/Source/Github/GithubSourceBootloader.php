<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Github;

use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\BooleanProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientBootloader;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class GithubSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [GithubClientBootloader::class];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            GithubSourceFetcher::class => GithubSourceFetcher::class,
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        GithubSourceFactory $factory,
        SchemaDefinitionRegistry $schemaRegistry,
    ): void {
        $registry->register(GithubSourceFetcher::class);
        $sourceRegistry->register($factory);
        $this->registerSchema($schemaRegistry);
    }

    private function registerSchema(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('githubSource')) {
            return;
        }

        $registry->define('githubSource')
            ->required('repository', 'sourcePaths')
            ->property('repository', StringProperty::new()
                ->description('GitHub repository in format owner/repo')
                ->pattern('^[\\w.-]+/[\\w.-]+$'))
            ->property('sourcePaths', RefProperty::to('sourcePaths'))
            ->property('branch', StringProperty::new()
                ->description('Branch or tag to fetch from')
                ->default('main'))
            ->property('filePattern', RefProperty::to('filePattern'))
            ->property('excludePatterns', ArrayProperty::strings()
                ->description('Patterns to exclude files')
                ->default([]))
            ->property('showTreeView', BooleanProperty::new()
                ->description('Whether to show directory tree')
                ->default(true))
            ->property('githubToken', StringProperty::new()
                ->description('GitHub API token for private repositories (can use env var pattern \${TOKEN_NAME})'));
    }
}
