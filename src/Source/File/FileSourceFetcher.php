<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Finder\FinderInterface;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\SourceInterface;
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
     */
    public function __construct(
        private string $basePath,
        private SourceModifierRegistry $modifiers,
        private FinderInterface $finder = new SymfonyFinder(),
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
    ) {}

    public function supports(SourceInterface $source): bool
    {
        return $source instanceof FileSource;
    }

    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof FileSource) {
            throw new \InvalidArgumentException('Source must be an instance of FileSource');
        }

        $builder = $this->builderFactory
            ->create()
            ->addTitle($source->getDescription());

        // Execute find operation and get the result
        try {
            $finderResult = $this->finder->find($source, $this->basePath);
        } catch (\Throwable $e) {
            if (\str_contains($e->getMessage(), 'must call one of in() or append() methods')) {
                throw new \RuntimeException(
                    \sprintf(
                        'Some directories or files contain invalid paths: %s',
                        \implode(
                            ', ',
                            [
                                ...(array) $source->in(),
                                ...(array) $source->files(),
                            ],
                        ),
                    ),
                );
            }

            throw new \RuntimeException(
                \sprintf('Error while finding files: %s', $e->getMessage()),
            );
        }

        // Generate tree view if requested
        if ($source->showTreeView) {
            $builder->addTreeView($finderResult->treeView);
        }

        // Process each file
        foreach ($finderResult->files as $file) {
            if (!$file instanceof SplFileInfo && $file instanceof \SplFileInfo) {
                $file = new SplFileInfo(
                    file: $file->getPathname(),
                    relativePath: $file->getPath(),
                    relativePathname: $file->getPathname(),
                );
            }

            $relativePath = \trim(\str_replace($this->basePath, '', $file->getPath()));
            $fileName = $file->getFilename();
            $filePath = empty($relativePath) ? $fileName : "$relativePath/$fileName";

            $language = $this->detectLanguage($filePath);

            $builder->addCodeBlock(
                code: $this->getContent($file, $source),
                language: $language,
                path: $filePath,
            );
        }

        // Return built content
        return $builder->build();
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

        $content = $file->getContents();

        // Apply modifiers if available and the source is a FileSource
        foreach ($source->modifiers as $modifierId) {
            if ($this->modifiers->has($modifierId)) {
                $modifier = $this->modifiers->get($modifierId);
                if ($modifier->supports($file->getFilename())) {
                    $content = $modifier->modify($content, $modifierId->context);
                }
            }
        }

        return $content;
    }

    private function detectLanguage(string $filePath): ?string
    {
        $extension = \pathinfo($filePath, PATHINFO_EXTENSION);

        if (empty($extension)) {
            return null;
        }

        return $extension;
    }
}
