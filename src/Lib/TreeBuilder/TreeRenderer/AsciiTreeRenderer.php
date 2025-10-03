<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\TreeBuilder\TreeRenderer;

use Butschster\ContextGenerator\Lib\TokenCounter\CharTokenCounter;
use Butschster\ContextGenerator\Lib\TokenCounter\TokenCounterInterface;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeRendererInterface;

/**
 * Renderer for ASCII tree format
 */
final readonly class AsciiTreeRenderer implements TreeRendererInterface
{
    public function __construct(
        private TokenCounterInterface $tokenCounter = new CharTokenCounter(),
    ) {}

    public function render(array $tree, array $options = []): string
    {
        $showSize = $options['showSize'] ?? false;
        $showLastModified = $options['showLastModified'] ?? false;
        $showCharCount = $options['showCharCount'] ?? false;
        $includeFiles = $options['includeFiles'] ?? true;
        $dirContext = $options['dirContext'] ?? [];

        return $this->renderNode(
            node: $tree,
            prefix: '',
            isLast: true,
            showSize: $showSize,
            showLastModified: $showLastModified,
            showCharCount: $showCharCount,
            includeFiles: $includeFiles,
            dirContext: $dirContext,
        );
    }

    private function renderNode(
        array $node,
        string $prefix = '',
        bool $isLast = true,
        bool $showSize = false,
        bool $showLastModified = false,
        bool $showCharCount = false,
        bool $includeFiles = true,
        array $dirContext = [],
        string $currentPath = '',
    ): string {
        if (empty($node)) {
            return '';
        }

        $result = '';
        $count = \count(value: $node);
        $i = 0;

        foreach ($node as $name => $children) {
            $i++;
            $isLastItem = ($i === $count);
            $isDirectory = \is_array(value: $children);

            // Skip files if includeFiles is false
            if (!$isDirectory && !$includeFiles) {
                continue;
            }

            $fullPath = $isDirectory ? $name : $children;

            // Build the relative path for directory context lookups
            $relativePath = $currentPath === '' ? $name : "$currentPath/$name";

            // Add name with directory indicator if applicable
            $displayName = $name . ($isDirectory ? '/' : '');

            // Add metadata if requested
            $metadata = '';
            if ($showSize || $showLastModified || $showCharCount) {
                if ($showSize) {
                    $size = $isDirectory
                        ? $this->calculateDirectorySize(directory: $children)
                        : (\file_exists(filename: $fullPath) ? \filesize(filename: $fullPath) : 0);
                    $metadata .= $this->formatSize(bytes: $size);
                }

                if ($showLastModified) {
                    $mtime = $isDirectory
                        ? $this->getLatestModificationTime(directory: $children)
                        : (\file_exists(filename: $fullPath) ? \filemtime(filename: $fullPath) : 0);
                    if ($showSize) {
                        $metadata .= ', ';
                    }
                    $metadata .= \date(format: 'Y-m-d', timestamp: $mtime ?: \time());
                }

                // Add character count if requested
                if ($showCharCount) {
                    $charCount = $isDirectory
                        ? $this->tokenCounter->calculateDirectoryCount($children)
                        : (\file_exists(filename: $fullPath) ? $this->tokenCounter->countFile($fullPath) : 0);
                    if ($showSize || $showLastModified) {
                        $metadata .= ', ';
                    }
                    if ($charCount > 0) {
                        $metadata .= \number_format(num: $charCount) . ' chars';
                    }
                }
            }

            if ($metadata !== '') {
                $metadata = ' [' . $metadata . ']';
            }

            // Add current node
            $result .= $prefix . ($isLast ? '└── ' : '├── ') . $displayName . $metadata;

            // Add context if available for directories using the full relative path
            if ($isDirectory && isset($dirContext[$relativePath])) {
                $result .= ' # ' . $dirContext[$relativePath];
            }

            // Add new line for the current node
            $result .= PHP_EOL;

            // Add children for directories
            if ($isDirectory) {
                $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $result .= $this->renderNode(
                    node: $children,
                    prefix: $newPrefix,
                    isLast: $isLastItem,
                    showSize: $showSize,
                    showLastModified: $showLastModified,
                    showCharCount: $showCharCount,
                    includeFiles: $includeFiles,
                    dirContext: $dirContext,
                    currentPath: $relativePath,  // Pass the updated relative path
                );
            }
        }

        return $result;
    }

    /**
     * Calculate the total size of a directory
     *
     * @param array<string, mixed> $directory Directory structure
     * @return int Total size in bytes
     */
    private function calculateDirectorySize(array $directory): int
    {
        $totalSize = 0;

        foreach ($directory as $children) {
            if (\is_array(value: $children)) {
                $totalSize += $this->calculateDirectorySize(directory: $children);
            } else {
                $totalSize += \file_exists(filename: $children) ? \filesize(filename: $children) : 0;
            }
        }

        return $totalSize;
    }

    /**
     * Find the latest modification time in a directory
     *
     * @param array<string, mixed> $directory Directory structure
     * @return int Latest modification timestamp
     */
    private function getLatestModificationTime(array $directory): int
    {
        $latestTime = 0;

        foreach ($directory as $children) {
            $time = \is_array(value: $children)
                ? $this->getLatestModificationTime(directory: $children)
                : (\file_exists(filename: $children) ? (int) \filemtime(filename: $children) : 0);

            if ($time > $latestTime) {
                $latestTime = $time;
            }
        }

        return $latestTime;
    }

    /**
     * Format file size in human-readable format
     *
     * @param int $bytes Size in bytes
     * @return string Formatted size (e.g., "4.2 MB")
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = \max($bytes, 0);
        /**
         * @psalm-suppress InvalidOperand
         */
        $pow = \floor(num: ($bytes ? \log(num: $bytes) : 0) / \log(num: 1024));
        $pow = \min($pow, \count(value: $units) - 1);

        /**
         * @psalm-suppress InvalidOperand
         */
        $bytes /= (1 << (10 * $pow));

        return \sprintf('%.1f %s', $bytes, $units[$pow]);
    }
}
