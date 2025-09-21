<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

use Butschster\ContextGenerator\Config\Exception\ReaderException;
use Psr\Log\LoggerInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Symfony\Component\Finder\Finder;

/**
 * Reader for scanning directories and processing markdown files
 * Scans a directory for .md files and processes each with MarkdownMetadataReader
 */
final readonly class MarkdownDirectoryReader implements ReaderInterface
{
    public function __construct(
        private MarkdownMetadataReader $markdownReader,
        private ExceptionReporterInterface $reporter,
        private ?LoggerInterface $logger = null,
    ) {}

    public function read(string $path): array
    {
        $this->logger?->debug('Scanning directory for markdown files', [
            'path' => $path,
        ]);

        if (!\is_dir($path)) {
            throw new ReaderException("Directory does not exist: {$path}");
        }

        try {
            $finder = new Finder();
            $finder
                ->files()
                ->in($path)
                ->name('*.md')
                ->name('*.markdown')
                ->followLinks()
                ->sortByName();

            $markdownFiles = [];
            foreach ($finder as $file) {
                $filePath = $file->getRealPath();
                $this->logger?->debug('Processing markdown file', ['file' => $filePath]);

                try {
                    // Read and parse the markdown file
                    $parsedContent = $this->markdownReader->read($filePath);

                    // Add file information to the parsed content
                    $markdownFiles[] = [
                        'file' => $filePath,
                        'relativePath' => $file->getRelativePathname(),
                        'name' => $file->getBasename('.md') ?: $file->getBasename('.markdown'),
                        'metadata' => $parsedContent['metadata'] ?? [],
                        'content' => $parsedContent['content'] ?? '',
                    ];
                } catch (\Throwable $e) {
                    trap($e);
                    $this->logger?->warning('Failed to process markdown file', [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                    ]);
                    $this->reporter->report($e);
                    // Continue with other files instead of failing completely
                }
            }

            $this->logger?->debug('Directory scan completed', [
                'path' => $path,
                'filesFound' => \count($markdownFiles),
            ]);

            return [
                'markdownFiles' => $markdownFiles,
                'scannedPath' => $path,
                'totalFiles' => \count($markdownFiles),
            ];

        } catch (\Throwable $e) {
            $this->logger?->error('Directory scan failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw new ReaderException("Failed to scan directory: {$path}", previous: $e);
        }
    }

    public function supports(string $path): bool
    {
        // This reader supports directories
        return \is_dir($path);
    }

    public function getSupportedExtensions(): array
    {
        // This reader handles directories, not specific extensions
        return [];
    }
}
