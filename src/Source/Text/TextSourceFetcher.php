<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Text;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\SourceInterface;

/**
 * Fetcher for text sources
 * @implements SourceFetcherInterface<TextSource>
 */
final readonly class TextSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
    ) {}

    public function supports(SourceInterface $source): bool
    {
        return $source instanceof TextSource;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof TextSource) {
            throw new \InvalidArgumentException('Source must be an instance of TextSource');
        }

        // Create builder
        $builder = $this->builderFactory
            ->create()
            ->addDescription($source->getDescription())
            ->addText(\sprintf('<%s>', $source->tag))
            ->addText($source->content)
            ->addText(\sprintf('</%s>', $source->tag))
            ->addSeparator();

        // Return built content
        return $builder->build();
    }
}
