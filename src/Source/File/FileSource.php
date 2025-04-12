<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\File;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Source\SourceWithModifiers;

/**
 * Enhanced source for files and directories with extended Symfony Finder features
 */
final class FileSource extends SourceWithModifiers implements FilterableSourceInterface
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
     * @param non-negative-int $maxFiles Maximum number of files to include (0 for no limit)
     * @param array<Modifier> $modifiers Identifiers for content modifiers to apply
     * @param array<non-empty-string> $tags
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
        public readonly TreeViewConfig $treeView = new TreeViewConfig(),
        public readonly int $maxFiles = 0,
        array $modifiers = [],
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags, modifiers: $modifiers);
    }

    public function name(): string|array|null
    {
        return $this->filePattern;
    }

    public function path(): string|array|null
    {
        return $this->path;
    }

    public function notPath(): string|array|null
    {
        return $this->notPath;
    }

    public function contains(): string|array|null
    {
        return $this->contains;
    }

    public function notContains(): string|array|null
    {
        return $this->notContains;
    }

    public function size(): string|array|null
    {
        return $this->size;
    }

    public function date(): string|array|null
    {
        return $this->date;
    }

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

    public function ignoreUnreadableDirs(): bool
    {
        return $this->ignoreUnreadableDirs;
    }

    public function maxFiles(): int
    {
        return $this->maxFiles;
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        $result = [
            'type' => 'file',
            ...parent::jsonSerialize(),
            'sourcePaths' => $this->sourcePaths,
            'filePattern' => $this->filePattern,
            'notPath' => $this->notPath,
            'treeView' => $this->treeView,
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

        // Add maxFiles only if it's set and not zero
        if ($this->maxFiles !== null && $this->maxFiles > 0) {
            $result['maxFiles'] = $this->maxFiles;
        }

        return \array_filter($result);
    }
}
