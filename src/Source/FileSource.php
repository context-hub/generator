<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

/**
 * Source for files and directories
 */
class FileSource extends BaseSource
{
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
            if (!\is_string($data['filePattern']) && !is_array($data['filePattern'])) {
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

        return new self(
            sourcePaths: $sourcePaths,
            description: $data['description'] ?? '',
            filePattern: $filePattern,
            excludePatterns: $data['excludePatterns'] ?? [],
            showTreeView: $data['showTreeView'] ?? true,
            modifiers: $data['modifiers'] ?? [],
        );
    }

    /**
     * @param string $description Human-readable description
     * @param string|array<string> $sourcePaths Paths to source files or directories
     * @param string|array<string> $filePattern Pattern(s) to match files
     * @param array<string> $excludePatterns Patterns to exclude files
     * @param array<string> $modifiers Identifiers for content modifiers to apply
     */
    public function __construct(
        public readonly string|array $sourcePaths,
        string $description = '',
        public readonly string|array $filePattern = '*.*',
        public readonly array $excludePatterns = [],
        public readonly bool $showTreeView = true,
        public readonly array $modifiers = [],
    ) {
        parent::__construct($description);
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'file',
            'description' => $this->description,
            'sourcePaths' => $this->sourcePaths,
            'filePattern' => $this->filePattern,
            'excludePatterns' => $this->excludePatterns,
            'showTreeView' => $this->showTreeView,
            'modifiers' => $this->modifiers,
        ]);
    }
}
