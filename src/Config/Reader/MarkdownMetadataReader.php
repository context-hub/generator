<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

use Butschster\ContextGenerator\Config\Exception\ReaderException;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Reader for markdown files with frontmatter metadata
 * Parses YAML frontmatter and markdown content from .md files
 */
final readonly class MarkdownMetadataReader implements ReaderInterface
{
    public function __construct(
        private FilesInterface $files,
        private ?LoggerInterface $logger = null,
    ) {}

    public function getSupportedExtensions(): array
    {
        return ['md', 'markdown'];
    }

    public function supports(string $path): bool
    {
        if (!$this->files->isFile($path)) {
            return false;
        }

        $extension = \pathinfo(path: $path, flags: PATHINFO_EXTENSION);
        $isSupported = \in_array(needle: $extension, haystack: $this->getSupportedExtensions(), strict: true);

        $this->logger?->debug('Checking if markdown file is supported', [
            'path' => $path,
            'extension' => $extension,
            'isSupported' => $isSupported,
            'reader' => self::class,
        ]);

        return $isSupported;
    }

    public function read(string $path): array
    {
        $this->logger?->debug('Reading markdown file', [
            'path' => $path,
            'reader' => self::class,
        ]);

        try {
            $content = $this->files->read($path);
        } catch (FilesException) {
            $errorMessage = \sprintf('Unable to read markdown file: %s', $path);
            $this->logger?->error($errorMessage);
            throw new ReaderException(message: $errorMessage);
        }

        $this->logger?->debug('Parsing markdown content', [
            'path' => $path,
            'contentLength' => \strlen(string: $content),
            'reader' => self::class,
        ]);

        try {
            $result = $this->parseContent(content: $content);
            $this->logger?->debug('Markdown content successfully parsed', [
                'path' => $path,
                'hasMetadata' => !empty($result['metadata']),
                'metadataKeys' => \array_keys(array: $result['metadata'] ?? []),
                'contentLength' => \strlen(string: $result['content'] ?? ''),
                'reader' => self::class,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $errorMessage = \sprintf('Failed to parse markdown file: %s', $path);
            $this->logger?->error($errorMessage, [
                'path' => $path,
                'error' => $e->getMessage(),
                'reader' => self::class,
            ]);
            throw new ReaderException($errorMessage, previous: $e);
        }
    }

    /**
     * Parse markdown content with frontmatter
     *
     * Expected format:
     * ---
     * title: "Example"
     * type: prompt
     * tags: [ai, helper]
     * ---
     * # Content here
     */
    private function parseContent(string $content): array
    {
        // Check if content starts with frontmatter delimiter
        if (\str_starts_with(haystack: \trim(string: $content), needle: '---')) {
            return $this->parseWithFrontmatter(content: $content);
        }

        // No frontmatter - check for title from first header
        $this->logger?->debug('No frontmatter found, checking for header title');

        return $this->parseWithoutFrontmatter(content: $content);
    }

    /**
     * Parse content that has YAML frontmatter
     */
    private function parseWithFrontmatter(string $content): array
    {
        // Split content by frontmatter delimiters
        $parts = \preg_split(pattern: '/^---\s*$/m', subject: $content, limit: 3);

        if (\count(value: $parts) < 3) {
            $this->logger?->debug('Invalid frontmatter format, falling back to header extraction');
            return $this->parseWithoutFrontmatter(content: $content);
        }

        // Extract frontmatter (second part, first part is empty)
        $frontmatter = \trim(string: $parts[1]);
        $markdownContent = \trim(string: $parts[2]);

        $result = [
            'metadata' => [],
            'content' => $markdownContent,
        ];

        // Parse YAML frontmatter
        try {
            if (!empty($frontmatter)) {
                $metadata = Yaml::parse(input: $frontmatter) ?: [];
                if (!\is_array(value: $metadata)) {
                    throw new \InvalidArgumentException(message: 'Frontmatter must be a YAML object');
                }
                $result['metadata'] = $metadata;
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to parse frontmatter YAML', [
                'error' => $e->getMessage(),
            ]);
            throw new ReaderException('Invalid YAML in frontmatter: ' . $e->getMessage(), previous: $e);
        }

        // If no title in metadata, try to extract from first header in content
        if (empty($result['metadata']['title'])) {
            $headerTitle = $this->extractTitleFromContent(content: $markdownContent);
            if ($headerTitle !== null) {
                $result['metadata']['title'] = $headerTitle;
                $this->logger?->debug('Extracted title from content header', [
                    'title' => $headerTitle,
                ]);
            }
        }

        $this->logger?->debug('Parsed markdown with frontmatter', [
            'metadataKeys' => \array_keys(array: $result['metadata']),
            'contentLength' => \strlen(string: $markdownContent),
            'hasTitle' => !empty($result['metadata']['title']),
        ]);

        return $result;
    }

    /**
     * Parse content without frontmatter, extracting title from first header
     */
    private function parseWithoutFrontmatter(string $content): array
    {
        $result = [
            'metadata' => [],
            'content' => $content,
        ];

        // Try to extract title from first header
        $title = $this->extractTitleFromContent(content: $content);
        if ($title !== null) {
            $result['metadata']['title'] = $title;
            $this->logger?->debug('Extracted title from header', [
                'title' => $title,
            ]);
        }

        $this->logger?->debug('Parsed markdown without frontmatter', [
            'contentLength' => \strlen(string: $content),
            'hasTitle' => $title !== null,
        ]);

        return $result;
    }

    /**
     * Extract title from the first header line in markdown content
     */
    private function extractTitleFromContent(string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        // Split content into lines
        $lines = \explode(separator: "\n", string: $content);

        foreach ($lines as $line) {
            $line = \trim(string: $line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check if line starts with # (header)
            if (\str_starts_with(haystack: $line, needle: '#')) {
                // Extract the title part after the # symbols
                $title = \preg_replace(pattern: '/^#+\s*/', replacement: '', subject: $line);
                $title = \trim(string: (string) $title);

                if (!empty($title)) {
                    return $title;
                }
            }

            // Stop at first non-empty, non-header line
            // This ensures we only check the beginning of the document
            break;
        }

        return null;
    }
}
