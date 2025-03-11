<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Modifier\Modifier;

/**
 * Enhanced source for files and directories with extended Symfony Finder features
 */
class FileSource extends BaseSource implements FilterableSourceInterface
{
    /**
     * @param string|array<string> $sourcePaths Paths to source files or directories
     * @param string $description Human-readable description
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $notPath Patterns to exclude files (formerly excludePatterns)
     * @param string|array<string> $path Patterns to include only specific paths
     * @param string|array<string> $contains Patterns to include files containing specific content
     * @param string|array<string> $notContains Patterns to exclude files containing specific content
     * @param string|array<string> $size Size constraints for files (e.g., '> 10K', '< 1M')
     * @param string|array<string> $date Date constraints for files (e.g., 'since yesterday', '> 2023-01-01')
     * @param bool $ignoreUnreadableDirs Whether to ignore unreadable directories
     * @param bool $showTreeView Whether to show tree view in output
     * @param array<Modifier> $modifiers Identifiers for content modifiers to apply
     */
    public function __construct(
        public readonly string|array $sourcePaths,
        string $description = '',
        public readonly string|array $filePattern = '*.*',
        public readonly array $notPath = [],
        public readonly string|array $path = [],
        public readonly string|array $contains = [],
        public readonly string|array $notContains = [],
        public readonly string|array $size = [],
        public readonly string|array $date = [],
        public readonly bool $ignoreUnreadableDirs = false,
        public readonly bool $showTreeView = true,
        public readonly array $modifiers = [],
    ) {
        parent::__construct($description);
    }

    /**
     * Create a FileSource from an array configuration
     */
    public static function fromArray(array $data, string $rootPath = ''): self
    {
        if (!isset($data['sourcePaths'])) {
            throw new \RuntimeException('File source must have a "sourcePaths" property');
        }

        $sourcePaths = $data['sourcePaths'];
        if (!\is_string($sourcePaths) && !\is_array($sourcePaths)) {
            throw new \RuntimeException('"sourcePaths" must be a string or array in source');
        }

        $sourcePaths = \is_string($sourcePaths) ? [$sourcePaths] : $sourcePaths;
        $sourcePaths = \array_map(
            static fn(string $sourcePath): string => $rootPath . '/' . \trim($sourcePath, '/'),
            $sourcePaths,
        );

        // Validate filePattern if present
        if (isset($data['filePattern'])) {
            if (!\is_string($data['filePattern']) && !\is_array($data['filePattern'])) {
                throw new \RuntimeException('filePattern must be a string or an array of strings');
            }

            // If it's an array, make sure all elements are strings
            if (\is_array($data['filePattern'])) {
                foreach ($data['filePattern'] as $pattern) {
                    if (!\is_string($pattern)) {
                        throw new \RuntimeException('All elements in filePattern must be strings');
                    }
                }
            }
        }

        // Handle filePattern parameter, allowing both string and array formats
        $filePattern = $data['filePattern'] ?? '*.*';

        // Convert notPath to match Symfony Finder's naming convention
        $notPath = $data['excludePatterns'] ?? $data['notPath'] ?? [];

        return new self(
            sourcePaths: $sourcePaths,
            description: $data['description'] ?? '',
            filePattern: $filePattern,
            notPath: $notPath,
            path: $data['path'] ?? [],
            contains: $data['contains'] ?? [],
            notContains: $data['notContains'] ?? [],
            size: $data['size'] ?? [],
            date: $data['date'] ?? [],
            ignoreUnreadableDirs: $data['ignoreUnreadableDirs'] ?? false,
            showTreeView: $data['showTreeView'] ?? true,
            modifiers: $data['modifiers'] ?? [],
        );
    }

    /**
     * Get file name pattern(s)
     *
     * @return string|string[] Pattern(s) to match file names against
     *
     * @psalm-return array<string>|string
     */
    public function name(): string|array|null
    {
        return $this->filePattern;
    }

    /**
     * Get file path pattern(s)
     *
     * @return string|string[] Pattern(s) to match file paths against
     *
     * @psalm-return array<string>|string
     */
    public function path(): string|array|null
    {
        return $this->path;
    }

    /**
     * Get excluded path pattern(s)
     *
     * @return string[] Pattern(s) to exclude file paths
     *
     * @psalm-return array<string>
     */
    public function notPath(): string|array|null
    {
        return $this->notPath;
    }

    /**
     * Get content pattern(s)
     *
     * @return string|string[] Pattern(s) to match file content against
     *
     * @psalm-return array<string>|string
     */
    public function contains(): string|array|null
    {
        return $this->contains;
    }

    /**
     * Get excluded content pattern(s)
     *
     * @return string|string[] Pattern(s) to exclude file content
     *
     * @psalm-return array<string>|string
     */
    public function notContains(): string|array|null
    {
        return $this->notContains;
    }

    /**
     * Get size constraint(s)
     *
     * @return string|string[] Size constraint(s)
     *
     * @psalm-return array<string>|string
     */
    public function size(): string|array|null
    {
        return $this->size;
    }

    /**
     * Get date constraint(s)
     *
     * @return string|string[] Date constraint(s)
     *
     * @psalm-return array<string>|string
     */
    public function date(): string|array|null
    {
        return $this->date;
    }

    /**
     * Get directories to search in
     *
     * @return null|string[] Directories to search in
     *
     * @psalm-return non-empty-list<string>|null
     */
    public function in(): array|null
    {
        $directories = [];

        // Extract directories from sourcePaths
        foreach ((array) $this->sourcePaths as $path) {
            if (\is_dir($path)) {
                $directories[] = $path;
            }
        }

        return empty($directories) ? null : $directories;
    }

    /**
     * Get individual files to include
     *
     * @return null|string[] Individual files to include
     *
     * @psalm-return non-empty-list<string>|null
     */
    public function files(): array|null
    {
        $files = [];

        // Extract files from sourcePaths
        foreach ((array) $this->sourcePaths as $path) {
            if (\is_file($path)) {
                $files[] = $path;
            }
        }

        return empty($files) ? null : $files;
    }

    /**
     * Check if unreadable directories should be ignored
     */
    public function ignoreUnreadableDirs(): bool
    {
        return $this->ignoreUnreadableDirs;
    }

    /**
     * @return (string|string[]|true)[]
     *
     * @psalm-return array{type: 'file', description?: string, sourcePaths?: array<string>|string, filePattern?: array<string>|string, notPath?: array<string>, showTreeView?: true, modifiers?: array<string>, path?: array<string>|string, contains?: array<string>|string, notContains?: array<string>|string, size?: array<string>|string, date?: array<string>|string, ignoreUnreadableDirs?: true}
     */
    public function jsonSerialize(): array
    {
        $result = [
            'type' => 'file',
            'description' => $this->description,
            'sourcePaths' => $this->sourcePaths,
            'filePattern' => $this->filePattern,
            'notPath' => $this->notPath,
            'showTreeView' => $this->showTreeView,
            'modifiers' => $this->modifiers,
        ];

        // Add optional properties only if they're non-empty
        if (!empty($this->path)) {
            $result['path'] = $this->path;
        }

        if (!empty($this->contains)) {
            $result['contains'] = $this->contains;
        }

        if (!empty($this->notContains)) {
            $result['notContains'] = $this->notContains;
        }

        if (!empty($this->size)) {
            $result['size'] = $this->size;
        }

        if (!empty($this->date)) {
            $result['date'] = $this->date;
        }

        if ($this->ignoreUnreadableDirs) {
            $result['ignoreUnreadableDirs'] = true;
        }

        return \array_filter($result);
    }
}
