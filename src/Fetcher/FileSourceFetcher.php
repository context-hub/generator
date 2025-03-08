<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\SourceInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Fetcher for file sources
 * @implements SourceFetcherInterface<FileSource>
 */
final readonly class FileSourceFetcher implements SourceFetcherInterface
{
    /**
     * @param string $basePath Base path for relative file references
     */
    public function __construct(
        private string $basePath,
        private FileTreeBuilder $treeBuilder = new FileTreeBuilder(),
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
        if ($source->showTreeView) {
            $finder = $this->createFinder($source);
            $filePaths = [];
            foreach ($finder as $file) {
                $filePaths[] = $file->getPathname();
            }

            // Generate tree view
            $content = $this->treeBuilder->buildTree($filePaths, $this->basePath);
        }

        $finder = $this->createFinder($source);
        foreach ($finder as $file) {
            $relativePath = \trim(\str_replace($this->basePath, '', $file->getPath()));
            $fileName = $file->getFilename();
            $filePath = empty($relativePath) ? $fileName : "$relativePath/$fileName";

            // Remove PHP tags and strict_types declaration
            $fileContent = \str_replace(
                ['<?php', 'declare(strict_types=1);'],
                '',
                $file->getContents(),
            );

            $content .= "// FILE: {$filePath}" . PHP_EOL;
            $content .= \trim($fileContent) . PHP_EOL . PHP_EOL;
            $content .= "// END OF FILE: {$filePath}" . PHP_EOL;
            $content .= '----------------------------------------------------------' . PHP_EOL;
        }

        return $content;
    }

    /**
     * Create a configured Finder instance for a file source
     */
    private function createFinder(FileSource $source): Finder
    {
        $files = [];
        $directories = [];

        // Separate files and directories
        foreach ((array) $source->sourcePaths as $path) {
            match (true) {
                \is_file($path) => $files[] = $path,
                \is_dir($path) => $directories[] = $path,
                default => null,
            };
        }

        // Create finder and configure it
        $finder = new Finder();
        if (!empty($directories)) {
            $finder->name($source->filePattern)->in($directories);
        }

        // Add individual files
        foreach ($files as $file) {
            $finder->append([new SplFileInfo($file, $file, $file)]);
        }

        // Apply exclusion filter if patterns exist
        if (!empty($source->excludePatterns)) {
            $finder->filter(
                fn(SplFileInfo $file): bool => !$this->shouldExcludeFile(
                    file: $file,
                    excludePatterns: $source->excludePatterns,
                ),
            );
        }

        return $finder;
    }

    /**
     * Determine if a file should be excluded based on patterns
     */
    private function shouldExcludeFile(SplFileInfo $file, array $excludePatterns): bool
    {
        foreach ($excludePatterns as $pattern) {
            if (\str_contains($file->getPathname(), (string) $pattern)) {
                return true;
            }
        }

        return false;
    }
}