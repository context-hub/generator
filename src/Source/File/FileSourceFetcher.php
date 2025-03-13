<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
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
     */
    public function __construct(
        private string $basePath,
        private SourceModifierRegistry $modifiers,
        private FinderInterface $finder = new SymfonyFinder(),
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

        $content = '';

        // Execute find operation and get the result
        $finderResult = $this->finder->find($source, $this->basePath);

        // Generate tree view if requested
        if ($source->showTreeView) {
            $content .= "```\n";
            $content .= $finderResult->treeView;
            $content .= "```\n\n";
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

            if (!$file instanceof SplFileInfo) {
                throw new \RuntimeException('Expected SplFileInfo objects in finder results');
            }

            $relativePath = \trim(\str_replace($this->basePath, '', $file->getPath()));
            $fileName = $file->getFilename();
            $filePath = empty($relativePath) ? $fileName : "$relativePath/$fileName";

            $content .= "// Path: {$filePath}\n";
            $content .= $this->getContent($file, $source) . "\n\n";
        }

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
}
