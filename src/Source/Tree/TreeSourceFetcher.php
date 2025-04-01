<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Tree;

use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\File\SymfonyFinder;
use Butschster\ContextGenerator\Source\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetcher for Tree sources that generates hierarchical visualizations
 * @implements SourceFetcherInterface<TreeSource>
 */
final readonly class TreeSourceFetcher implements SourceFetcherInterface
{
    /**
     * @param string $basePath Base path for relative file references
     * @param ContentBuilderFactory $builderFactory Factory for creating ContentBuilder instances
     * @param SymfonyFinder $finder Finder for filtering files
     * @param LoggerInterface|null $logger PSR Logger instance
     */
    public function __construct(
        private string $basePath,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private SymfonyFinder $finder = new SymfonyFinder(),
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof TreeSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof TreeSource) {
            $errorMessage = 'Source must be an instance of TreeSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger?->info('Fetching tree source content', [
            'description' => $source->getDescription(),
            'basePath' => $this->basePath,
            'renderFormat' => $source->renderFormat,
            'hasModifiers' => !empty($source->modifiers),
        ]);

        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory
            ->create()
            ->addTitle($source->getDescription());

        try {
            // Use SymfonyFinder to find files
            $finderResult = $this->finder->find($source, $this->basePath, $source->treeView->getOptions());

            // Add content to builder
            $builder->addCodeBlock($finderResult->treeView);
        } catch (\Throwable $e) {
            $errorMessage = \sprintf('Error while generating tree: %s', $e->getMessage());
            $this->logger?->error($errorMessage, [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw new \RuntimeException($errorMessage);
        }

        $content = $builder->build();
        $this->logger?->info('Tree source content fetched successfully', [
            'contentLength' => \strlen($content),
        ]);

        return $content;
    }
}
