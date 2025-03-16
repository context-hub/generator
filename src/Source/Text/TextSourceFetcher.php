<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Text;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetcher for text sources
 * @implements SourceFetcherInterface<TextSource>
 */
final readonly class TextSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof TextSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof TextSource) {
            $errorMessage = 'Source must be an instance of TextSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger?->info('Fetching text source content', [
            'description' => $source->getDescription(),
            'tag' => $source->tag,
            'contentLength' => \strlen($source->content),
        ]);

        // Create builder
        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory
            ->create()
            ->addDescription($source->getDescription());

        $this->logger?->debug('Adding text content with tags', [
            'tag' => $source->tag,
        ]);

        $builder
            ->addText(\sprintf('<%s>', $source->tag))
            ->addText($modifiersApplier->apply($source->content, 'file.txt'))
            ->addText(\sprintf('</%s>', $source->tag))
            ->addSeparator();

        $content = $builder->build();
        $this->logger?->info('Text source content fetched successfully', [
            'contentLength' => \strlen($content),
        ]);

        // Return built content
        return $content;
    }
}
