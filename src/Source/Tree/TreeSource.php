<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Tree;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\BaseSource;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;

/**
 * Tree source for generating hierarchical visualizations of directory structures
 */
final class TreeSource extends BaseSource implements FilterableSourceInterface
{
    /**
     * @param string|array<string> $sourcePaths Path(s) to generate tree from
     * @param string $description Human-readable description
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $notPath Patterns to exclude paths
     * @param string|array<string> $path Patterns to include only specific paths
     * @param string|array<string> $contains Patterns to include files containing specific content
     * @param string|array<string> $notContains Patterns to exclude files containing specific content
     * @param string $renderFormat Output format for the tree (ascii, markdown, json)
     * @param TreeViewConfig|bool $treeView Tree view configuration
     * @param array<non-empty-string> $tags
     */
    public function __construct(
        public readonly string|array $sourcePaths,
        string $description = '',
        public readonly string|array $filePattern = '*',
        public readonly array $notPath = [],
        public readonly string|array $path = [],
        public readonly string|array $contains = [],
        public readonly string|array $notContains = [],
        public readonly string $renderFormat = 'ascii',
        public readonly TreeViewConfig|bool $treeView = true,
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags);
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
        return null;
    }

    public function date(): string|array|null
    {
        return null;
    }

    public function in(): array|null
    {
        $directories = [];

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

        foreach ((array) $this->sourcePaths as $path) {
            if (\is_file($path)) {
                $files[] = $path;
            }
        }

        return empty($files) ? null : $files;
    }

    public function ignoreUnreadableDirs(): bool
    {
        return true;
    }

    public function maxFiles(): int
    {
        return 0;
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        $result = [
            'type' => 'tree',
            ...parent::jsonSerialize(),
            'sourcePaths' => $this->sourcePaths,
            'filePattern' => $this->filePattern,
            'notPath' => $this->notPath,
            'renderFormat' => $this->renderFormat,
            ...$this->treeView->jsonSerialize(),
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

        if (!empty($this->dirContext)) {
            $result['dirContext'] = $this->dirContext;
        }

        return \array_filter($result);
    }
}
