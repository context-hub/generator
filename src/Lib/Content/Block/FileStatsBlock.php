<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Block for file statistics (size, line count, etc.)
 */
final readonly class FileStatsBlock extends AbstractBlock
{
    public function __construct(
        string $content,
        private int $fileSize,
        private int $lineCount,
        private ?string $filePath = null,
    ) {
        parent::__construct($content);
    }

    public function render(RendererInterface $renderer): string
    {
        return $renderer->renderFileStatsBlock($this);
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getLineCount(): int
    {
        return $this->lineCount;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < \count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        // Ensure $i is within bounds
        $i = \min($i, \count($units) - 1);

        return \round($bytes, 2) . ' ' . $units[$i];
    }
}
