<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\TreeBuilder;

/**
 * Configuration container for tree view rendering options
 */
final readonly class TreeViewConfig implements \JsonSerializable
{
    /**
     * @param bool $enabled Whether to show the tree view
     * @param bool $showSize Whether to show file/directory sizes
     * @param bool $showLastModified Whether to show last modified dates
     * @param bool $showCharCount Whether to show character counts
     * @param bool $includeFiles Whether to include files (true) or show only directories (false)
     * @param int $maxDepth Maximum tree depth to display (0 for unlimited)
     * @param array<string, string> $dirContext Optional descriptions for specific directories
     */
    public function __construct(
        public bool $enabled = true,
        public bool $showSize = false,
        public bool $showLastModified = false,
        public bool $showCharCount = false,
        public bool $includeFiles = true,
        public int $maxDepth = 0,
        public array $dirContext = [],
    ) {}

    /**
     * Create a TreeViewConfig from an array configuration
     */
    public static function fromArray(array $data): self
    {
        // backward compatibility for old config
        if (isset($data['showTreeView'])) {
            return new self(enabled: (bool) $data['showTreeView']);
        }

        // Handle boolean case (backward compatibility)
        if (isset($data['treeView']) && \is_bool(value: $data['treeView'])) {
            return new self(enabled: $data['treeView']);
        }

        // Handle object/array case
        $config = $data['treeView'] ?? [];
        if (!\is_array(value: $config)) {
            return new self(enabled: (bool) $config);
        }

        return new self(
            enabled: $config['enabled'] ?? true,
            showSize: $config['showSize'] ?? false,
            showLastModified: $config['showLastModified'] ?? false,
            showCharCount: $config['showCharCount'] ?? false,
            includeFiles: $config['includeFiles'] ?? true,
            maxDepth: $config['maxDepth'] ?? 0,
            dirContext: $config['dirContext'] ?? [],
        );
    }

    /**
     * Get tree view options as an array
     */
    public function getOptions(): array
    {
        return [
            'showSize' => $this->showSize,
            'showLastModified' => $this->showLastModified,
            'showCharCount' => $this->showCharCount,
            'includeFiles' => $this->includeFiles,
            'maxDepth' => $this->maxDepth,
            'dirContext' => $this->dirContext,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return \array_filter(array: [
            'enabled' => $this->enabled,
            'showSize' => $this->showSize,
            'showLastModified' => $this->showLastModified,
            'showCharCount' => $this->showCharCount,
            'includeFiles' => $this->includeFiles === false ? false : null,
            'maxDepth' => $this->maxDepth > 0 ? $this->maxDepth : null,
            'dirContext' => !empty($this->dirContext) ? $this->dirContext : null,
        ], callback: static fn($value) => $value !== null);
    }
}
