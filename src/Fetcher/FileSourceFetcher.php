<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Fetcher\Finder\SymfonyFinder;
use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\Source\SourceModifierRegistry;
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
            if (!$file instanceof SplFileInfo) {
                throw new \RuntimeException('Expected SplFileInfo objects in finder results');
            }

            $relativePath = \trim(\str_replace($this->basePath, '', $file->getPath()));
            $fileName = $file->getFilename();
            $filePath = empty($relativePath) ? $fileName : "$relativePath/$fileName";

            $content .= "```\n";
            $content .= "// Path: {$filePath}\n";
            $content .= $this->getContent($file, $source) . "\n\n";
            $content .= "```\n";
        }

        return $content;
    }

    /**
     * Get and optionally modify the content of a file
     *
     * @param SplFileInfo $file The file info object
     * @param SourceInterface $source The source containing modifiers
     * @return string The file content, possibly modified
     */
    protected function getContent(SplFileInfo $file, SourceInterface $source): string
    {
        $content = $file->getContents();

        // Apply modifiers if available and the source is a FileSource
        if ($source instanceof FileSource && !empty($source->modifiers)) {
            foreach ($source->modifiers as $modifierId) {
                if ($this->modifiers->has($modifierId)) {
                    $modifier = $this->modifiers->get($modifierId);
                    if ($modifier->supports($file->getFilename())) {
                        $context = [
                            'file' => $file,
                            'source' => $source,
                        ];
                        $content = $modifier->modify($content, $context);
                    }
                }
            }
        }

        return $content;
    }
}
