<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\JsonSchema\Combinator\OneOf;
use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\BooleanProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\FactoryInterface;

final class FileSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            FileSourceFetcher::class => static fn(
                FactoryInterface $factory,
                DirectoriesInterface $dirs,
                ContentBuilderFactory $builderFactory,
                HasPrefixLoggerInterface $logger,
            ): FileSourceFetcher => $factory->make(FileSourceFetcher::class, [
                'basePath' => (string) $dirs->getRootPath(),
                'finder' => $factory->make(SymfonyFinder::class),
            ]),
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        FileSourceFactory $factory,
        SchemaDefinitionRegistry $schemaRegistry,
    ): void {
        $registry->register(FileSourceFetcher::class);
        $sourceRegistry->register($factory);
        $this->registerSchema($schemaRegistry);
    }

    private function registerSchema(SchemaDefinitionRegistry $registry): void
    {
        // Register filePattern if not exists
        if (!$registry->has('filePattern')) {
            $registry->register(
                \Butschster\ContextGenerator\JsonSchema\Definition\OneOfDefinition::create(
                    'filePattern',
                    \Butschster\ContextGenerator\JsonSchema\Property\StringProperty::new()
                        ->description('Pattern to match files (e.g., *.php)'),
                    ArrayProperty::strings()
                        ->description('List of patterns to match files'),
                ),
            );
        }

        if ($registry->has('fileSource')) {
            return;
        }

        $registry->define('fileSource')
            ->required('sourcePaths')
            ->property('sourcePaths', RefProperty::to('sourcePaths'))
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
            ->property('size', RefProperty::to('patternConstraint'))
            ->property('date', RefProperty::to('patternConstraint'))
            ->property('ignoreUnreadableDirs', BooleanProperty::new()
                ->description('Whether to ignore unreadable directories')
                ->default(false))
            ->property('showTreeView', BooleanProperty::new()
                ->description('Whether to show directory tree')
                ->default(true))
            ->property('treeView', OneOf::create(
                BooleanProperty::new()->description('Whether to show the tree view'),
                RefProperty::to('treeViewConfig'),
            )->description('Tree view configuration'))
            ->property('modifiers', RefProperty::to('modifiers'));
    }
}
