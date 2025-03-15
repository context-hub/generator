<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Enhanced fetcher for file sources with extended Symfony Finder features
 * @implements SourceFetcherInterface<FileSource>
 */
readonly class FileSourceFetcher implements SourceFetcherInterface
{
    /**
     * @param string $basePath Base path for relative file references
     * @param SourceModifierRegistry $modifiers Registry of content modifiers
     * @param FinderInterface $finder Finder for locating files
     * @param ContentBuilderFactory $builderFactory Factory for creating ContentBuilder instances
     * @param LoggerInterface|null $logger PSR Logger instance
     */
    public function __construct(
        private string $basePath,
        private SourceModifierRegistry $modifiers,
        private FinderInterface $finder = new SymfonyFinder(),
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof FileSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof FileSource) {
            $errorMessage = 'Source must be an instance of FileSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger?->info('Fetching file source content', [
            'description' => $source->getDescription(),
            'basePath' => $this->basePath,
            'hasModifiers' => !empty($source->modifiers),
            'showTreeView' => $source->showTreeView,
        ]);

        $this->logger?->debug('Creating content builder');
        $builder = $this->builderFactory
            ->create()
            ->addTitle($source->getDescription());

        // Execute find operation and get the result
        $this->logger?->debug('Finding files', [
            'in' => $source->in(),
            'files' => $source->files(),
        ]);

        try {
            $finderResult = $this->finder->find($source, $this->basePath);
            $fileCount = $finderResult->count();
            $this->logger?->debug('Files found', ['fileCount' => $fileCount]);
        } catch (\Throwable $e) {
            if (\str_contains($e->getMessage(), 'must call one of in() or append() methods')) {
                $errorMessage = \sprintf(
                    'Some directories or files contain invalid paths: %s',
                    \implode(
                        ', ',
                        [
                            ...(array) $source->in(),
                            ...(array) $source->files(),
                        ],
                    ),
                );
                $this->logger?->error($errorMessage, [
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException($errorMessage);
            }

            $errorMessage = \sprintf('Error while finding files: %s', $e->getMessage());
            $this->logger?->error($errorMessage, [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw new \RuntimeException($errorMessage);
        }

        // Generate tree view if requested
        if ($source->showTreeView) {
            $this->logger?->debug('Adding tree view to output');
            $builder->addTreeView($finderResult->treeView);
        }

        // Process each file
        $this->logger?->debug('Processing files');
        foreach ($finderResult->files as $index => $file) {
            if (!$file instanceof SplFileInfo && $file instanceof \SplFileInfo) {
                $this->logger?->debug('Converting SplFileInfo to Symfony SplFileInfo', [
                    'pathname' => $file->getPathname(),
                ]);
                $file = new SplFileInfo(
                    file: $file->getPathname(),
                    relativePath: $file->getPath(),
                    relativePathname: $file->getPathname(),
                );
            }

            $relativePath = \trim(\str_replace($this->basePath, '', $file->getPath()));
            $fileName = $file->getFilename();
            $filePath = empty($relativePath) ? $fileName : "$relativePath/$fileName";

            $this->logger?->debug('Processing file', [
                'file' => $filePath,
                'index' => $index + 1,
                'total' => $fileCount,
                'size' => $file->getSize(),
            ]);

            $language = $this->detectLanguage($filePath);
            $content = $this->getContent($file, $source);

            $this->logger?->debug('Adding file to content', [
                'file' => $filePath,
                'language' => $language,
                'contentLength' => \strlen($content),
            ]);

            $builder->addCodeBlock(
                code: $content,
                language: $language,
                path: $filePath,
            );
        }

        $content = $builder->build();
        $this->logger?->info('File source content fetched successfully', [
            'fileCount' => $fileCount,
            'contentLength' => \strlen($content),
        ]);

        // Return built content
        return $content;
    }

    /**
     * Get and optionally modify the content of a file
     *
     * @param SplFileInfo $file The file info object
     * @param FileSource $source The source containing modifiers
     * @return string The file content, possibly modified
     */
    protected function getContent(SplFileInfo $file, SourceInterface $source): string
    {
        \assert($source instanceof FileSource);

        $filePath = $file->getRelativePathname();
        $this->logger?->debug('Reading file content', ['file' => $filePath]);
        $content = $file->getContents();
        $originalLength = \strlen($content);

        // Apply modifiers if available and the source is a FileSource
        if (!empty($source->modifiers)) {
            $this->logger?->debug('Applying modifiers to file', [
                'file' => $filePath,
                'modifierCount' => \count($source->modifiers),
            ]);

            foreach ($source->modifiers as $modifierId) {
                if ($this->modifiers->has($modifierId)) {
                    $modifier = $this->modifiers->get($modifierId);
                    $modifierClass = $modifier::class;

                    if ($modifier->supports($file->getFilename())) {
                        $this->logger?->debug('Applying modifier', [
                            'file' => $filePath,
                            'modifier' => $modifierClass,
                            'modifierId' => (string) $modifierId,
                        ]);

                        $content = $modifier->modify($content, $modifierId->context);

                        $this->logger?->debug('Modifier applied', [
                            'file' => $filePath,
                            'modifier' => $modifierClass,
                            'originalLength' => $originalLength,
                            'modifiedLength' => \strlen($content),
                        ]);
                    } else {
                        $this->logger?->debug('Modifier not applicable to file', [
                            'file' => $filePath,
                            'modifier' => $modifierClass,
                        ]);
                    }
                } else {
                    $this->logger?->warning('Modifier not found', [
                        'modifierId' => (string) $modifierId,
                    ]);
                }
            }
        }

        return $content;
    }

    private function detectLanguage(string $filePath): ?string
    {
        $extension = \pathinfo($filePath, PATHINFO_EXTENSION);

        $this->logger?->debug('Detecting language for file', [
            'file' => $filePath,
            'extension' => $extension,
        ]);

        if (empty($extension)) {
            return null;
        }

        return $extension;
    }
}
