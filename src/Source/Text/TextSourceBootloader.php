<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Text;

use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class TextSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            TextSourceFetcher::class => TextSourceFetcher::class,
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        TextSourceFactory $factory,
        SchemaDefinitionRegistry $schemaRegistry,
    ): void {
        $registry->register(TextSourceFetcher::class);
        $sourceRegistry->register($factory);
        $this->registerSchema($schemaRegistry);
    }

    private function registerSchema(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('textSource')) {
            return;
        }

        $registry->define('textSource')
            ->required('content')
            ->property('content', StringProperty::new()
                ->description('Text content'))
            ->property('tag', StringProperty::new()
                ->description('Tag to help LLM understand the content. By default, it is set to \'instruction\'.')
                ->default('instruction'));
    }
}
