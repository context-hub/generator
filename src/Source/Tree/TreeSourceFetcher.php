<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Tree;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
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
    public function __construct(
        private string $basePath,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private SymfonyFinder $finder = new SymfonyFinder(),
        #[LoggerPrefix(prefix: 'tree-source')]
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
            'sourcePaths' => $source->sourcePaths,
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
