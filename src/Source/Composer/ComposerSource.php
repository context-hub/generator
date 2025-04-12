<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\Fetcher\FilterableSourceInterface;
use Butschster\ContextGenerator\Source\SourceWithModifiers;

/**
 * Source for Composer packages
 */
final class ComposerSource extends SourceWithModifiers implements FilterableSourceInterface
{
    /**
     * @param string $composerPath Path to the composer.json file or directory containing it
     * @param string $description Human-readable description
     * @param string|array<string> $packages Patterns to match package names
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $notPath Patterns to exclude files
     * @param string|array<string> $path Patterns to include only specific paths
     * @param string|array<string> $contains Patterns to include files containing specific content
     * @param string|array<string> $notContains Patterns to exclude files containing specific content
     * @param bool $includeDevDependencies Whether to include dev dependencies
     * @param TreeViewConfig $treeView Tree view configuration or boolean flag
     * @param array<Modifier> $modifiers Identifiers for content modifiers to apply
     * @param array<string> $tags Tags for organization
     */
    public function __construct(
        public readonly string $composerPath = '.',
        string $description = 'Composer Packages',
        public readonly string|array $packages = [],
        public readonly string|array $filePattern = '*.php',
        public readonly array $notPath = ['tests', 'vendor', 'examples'],
        public readonly string|array $path = [],
        public readonly string|array $contains = [],
        public readonly string|array $notContains = [],
        public readonly bool $includeDevDependencies = false,
        public readonly TreeViewConfig $treeView = new TreeViewConfig(),
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
        return null;
    }

    public function date(): string|array|null
    {
        return null;
    }

    public function in(): array|null
    {
        return null;
    }

    public function files(): array|null
    {
        return null;
    }

    public function ignoreUnreadableDirs(): bool
    {
        return true;
    }

    public function maxFiles(): int
    {
        return 0; //todo Add support for max files
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'composer',
            ...parent::jsonSerialize(),
            'composerPath' => $this->composerPath,
            'packages' => $this->packages,
            'filePattern' => $this->filePattern,
            'notPath' => $this->notPath,
            'path' => $this->path,
            'contains' => $this->contains,
            'notContains' => $this->notContains,
            'includeDevDependencies' => $this->includeDevDependencies,
            'treeView' => $this->treeView,
        ], static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
