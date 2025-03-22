<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Tree;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeRenderer;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeRendererInterface;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

/**
 * Fetcher for Tree sources that generates hierarchical visualizations
 * @implements SourceFetcherInterface<TreeSource>
 */
readonly class TreeSourceFetcher implements SourceFetcherInterface
{
    /**
     * @param string $basePath Base path for relative file references
     * @param ContentBuilderFactory $builderFactory Factory for creating ContentBuilder instances
     * @param LoggerInterface|null $logger PSR Logger instance
     */
    public function __construct(
        private string $basePath,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
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
            'maxDepth' => $source->maxDepth,
            'hasModifiers' => !empty($source->modifiers),
        ]);

        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory
            ->create()
            ->addTitle($source->getDescription());

        try {
            // Get appropriate renderer based on format
            $renderer = $this->getRenderer($source->renderFormat);

            // Create a new FileTreeBuilder with the selected renderer
            $treeBuilder = new FileTreeBuilder($renderer);

            // Find files using Symfony Finder
            $finder = new Finder();
            $finder->files();

            // Configure source paths
            $directories = [];
            $files = [];

            foreach ((array) $source->sourcePath as $path) {
                if (\is_dir($path)) {
                    $directories[] = $path;
                } elseif (\is_file($path)) {
                    $files[] = $path;
                }
            }

            if (!empty($directories)) {
                $finder->in($directories);
            }

            if (!empty($files)) {
                $finder->append($files);
            }

            // Apply filters
            $finder->name($source->filePattern);
            $finder->path($source->path());
            $finder->notPath($source->notPath());

            $containsPattern = $source->contains();
            if ($containsPattern !== null) {
                $finder->contains($containsPattern);
            }

            $notContainsPattern = $source->notContains();
            if ($notContainsPattern !== null) {
                $finder->notContains($notContainsPattern);
            }

            // Apply depth limit if set
            if ($source->maxDepth > 0) {
                $finder->depth("< {$source->maxDepth}");
            }

            $finder->sortByName();

            // Get file paths
            $filePaths = [];
            foreach ($finder as $file) {
                if ($source->includeFiles || \is_dir($file->getRealPath())) {
                    $filePaths[] = $file->getRealPath();
                }
            }

            $this->logger?->debug('Files found for tree', [
                'count' => \count($filePaths),
            ]);

            // Build options for the renderer
            $options = [
                'showSize' => $source->showSize,
                'showLastModified' => $source->showLastModified,
                'dirContext' => $source->dirContext,
                'showCharCount' => $source->showCharCount,
            ];

            // Generate the tree
            $renderedTree = $treeBuilder->buildTree($filePaths, $this->basePath, $options);

            // Apply modifiers
            $content = $modifiersApplier->apply($renderedTree, 'tree');

            // Add content to builder
            $builder->addCodeBlock($content);
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

    private function getRenderer(
        string $format,
    ): TreeRendererInterface {
        return new TreeRenderer\AsciiTreeRenderer();
    }
}
