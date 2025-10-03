<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Text;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Content\Block\TextBlock;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetcher for text sources
 * @implements SourceFetcherInterface<TextSource>
 */
final readonly class TextSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private VariableResolver $variableResolver = new VariableResolver(),
        #[LoggerPrefix(prefix: 'text-source')]
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
            throw new \InvalidArgumentException(message: $errorMessage);
        }

        $description = $this->variableResolver->resolve(strings: $source->getDescription());

        $this->logger?->info('Fetching text source content', [
            'description' => $description,
            'tag' => $source->tag,
            'contentLength' => \strlen(string: $source->content),
        ]);

        // Create builder
        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory
            ->create()
            ->addDescription(description: $description);

        $this->logger?->debug('Adding text content with tags', [
            'tag' => $source->tag,
        ]);

        $content = $this->variableResolver->resolve(strings: $source->content);
        $tag = $this->variableResolver->resolve(strings: $source->tag);

        $builder
            ->addBlock(block: new TextBlock(content: $modifiersApplier->apply($content, 'file.txt'), tag: $tag))
            ->addSeparator();

        $content = $builder->build();
        $this->logger?->info('Text source content fetched successfully', [
            'contentLength' => \strlen(string: $content),
        ]);

        // Return built content
        return $content;
    }
}
