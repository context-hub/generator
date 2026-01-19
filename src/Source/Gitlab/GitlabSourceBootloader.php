<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab;

use Butschster\ContextGenerator\Config\ConfigLoaderBootloader;
use Butschster\ContextGenerator\JsonSchema\Combinator\OneOf;
use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\BooleanProperty;
use Butschster\ContextGenerator\JsonSchema\Property\ObjectProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\Gitlab\Config\GitlabServerParserPlugin;
use Butschster\ContextGenerator\Source\Gitlab\Config\ServerRegistry;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class GitlabSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            GitlabSourceFetcher::class => GitlabSourceFetcher::class,
            ServerRegistry::class => ServerRegistry::class,
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        GitlabSourceFactory $factory,
        SchemaDefinitionRegistry $schemaRegistry,
    ): void {
        $registry->register(GitlabSourceFetcher::class);
        $sourceRegistry->register($factory);
        $this->registerSchema($schemaRegistry);
    }

    public function boot(
        ConfigLoaderBootloader $parserRegistry,
        GitlabServerParserPlugin $plugin,
    ): void {
        $parserRegistry->registerParserPlugin($plugin);
    }

    private function registerSchema(SchemaDefinitionRegistry $registry): void
    {
        $this->registerGitlabServerConfig($registry);
        $this->registerGitlabSource($registry);
    }

    private function registerGitlabServerConfig(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('gitlabServerConfig')) {
            return;
        }

        $registry->define('gitlabServerConfig')
            ->required('url')
            ->property('url', StringProperty::new()
                ->description('GitLab server URL'))
            ->property('token', StringProperty::new()
                ->description('GitLab API token for authentication (can use env var pattern \${TOKEN_NAME})'))
            ->property('headers', ObjectProperty::new()
                ->description('Custom HTTP headers to send with requests')
                ->additionalProperties(StringProperty::new()));
    }

    private function registerGitlabSource(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('gitlabSource')) {
            return;
        }

        $registry->define('gitlabSource')
            ->required('repository', 'sourcePaths')
            ->property('repository', StringProperty::new()
                ->description('GitLab repository in format group/project')
                ->pattern('^[\\w.-]+/[\\w.-]+$'))
            ->property('sourcePaths', RefProperty::to('sourcePaths'))
            ->property('branch', StringProperty::new()
                ->description('Branch or tag to fetch from')
                ->default('main'))
            ->property('filePattern', RefProperty::to('filePattern'))
            ->property('excludePatterns', ArrayProperty::strings()
                ->description('Patterns to exclude files (alias for notPath)')
                ->default([]))
            ->property('notPath', ArrayProperty::strings()
                ->description('Patterns to exclude files by path')
                ->default([]))
            ->property('path', RefProperty::to('patternConstraint'))
            ->property('contains', RefProperty::to('patternConstraint'))
            ->property('notContains', RefProperty::to('patternConstraint'))
            ->property('showTreeView', BooleanProperty::new()
                ->description('Whether to show directory tree')
                ->default(true))
            ->property('treeView', OneOf::create(
                BooleanProperty::new()->description('Whether to show the tree view'),
                RefProperty::to('treeViewConfig'),
            )->description('Tree view configuration'))
            ->property('server', OneOf::create(
                StringProperty::new()->description('Reference to a pre-defined server in settings.gitlab.servers'),
                RefProperty::to('gitlabServerConfig'),
            )->description('GitLab server configuration or reference to a pre-defined server'))
            ->property('modifiers', RefProperty::to('modifiers'));
    }
}
