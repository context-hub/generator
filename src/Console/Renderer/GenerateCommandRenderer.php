<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

use Butschster\ContextGenerator\Config\Import\ImportRegistry;
use Butschster\ContextGenerator\Document\Compiler\CompiledDocument;
use Butschster\ContextGenerator\Document\Document;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Renderer for the generate command output
 */
final readonly class GenerateCommandRenderer
{
    /**
     * Maximum line width for consistent display
     */
    private const int MAX_LINE_WIDTH = 100;

    /**
     * Success indicator symbol
     */
    private const string SUCCESS_SYMBOL = '✓';

    /**
     * Warning indicator symbol
     */
    private const string WARNING_SYMBOL = '!';

    /**
     * Error indicator symbol
     */
    private const string ERROR_SYMBOL = '✗';

    public function __construct(
        private OutputInterface $output,
        private ?FilesInterface $files = null,
        private ?string $basePath = null,
    ) {}

    public function renderImports(ImportRegistry $imports): void
    {
        \assert(assertion: $this->output instanceof SymfonyStyle);
        foreach ($imports as $item) {
            $description = $item->getPath();

            if (\strlen(string: $description) > self::MAX_LINE_WIDTH - 40) {
                $halfLength = (self::MAX_LINE_WIDTH - 40) / 2;
                $description = \substr(string: $description, offset: 0, length: $halfLength) . '...' . \substr(string: $description, offset: -$halfLength);
            }

            // Calculate padding to align the document descriptions
            $padding = $this->calculatePadding(description: $item->getType(), outputPath: $description, additional: 12);

            $this->output->writeln(
                messages: \sprintf(
                    ' <fg=yellow>%s</> Import %s <fg=yellow>[%s]</><fg=gray>%s</>',
                    $this->padRight(text: self::SUCCESS_SYMBOL, length: 1),
                    $item->getType(),
                    $description,
                    $padding,
                ),
            );
        }

        $this->output->newLine();
    }

    /**
     * Render the compilation result for a document
     */
    public function renderCompilationResult(Document $document, CompiledDocument $compiledDocument): void
    {
        \assert(assertion: $this->output instanceof SymfonyStyle);
        $hasErrors = $compiledDocument->errors->hasErrors();
        $description = $document->description;
        $outputPath = $document->outputPath;

        // Calculate padding to align the document descriptions
        $stats = $this->getFileStatistics(outputPath: $outputPath);
        $padding = $this->calculatePadding(description: $description, outputPath: $outputPath);

        if ($hasErrors) {
            // Render warning line with document info
            $this->output->writeln(
                messages: \sprintf(
                    ' <fg=yellow>%s</> %s <fg=yellow>[%s]</><fg=gray>%s</>',
                    $this->padRight(text: self::WARNING_SYMBOL, length: 1),
                    $description,
                    $outputPath,
                    $padding,
                ),
            );

            // Render errors
            foreach ($compiledDocument->errors as $error) {
                $this->output->writeln(messages: \sprintf('    <fg=red>%s</> %s', self::ERROR_SYMBOL, $error));
            }

            $this->output->newLine();
        } else {
            // Render success line with document info and file statistics
            $this->renderSuccessWithStats(description: $description, outputPath: $outputPath, stats: $stats, padding: $padding);
        }
    }

    /**
     * Render success message with file statistics
     */
    private function renderSuccessWithStats(string $description, string $outputPath, ?array $stats, string $padding): void
    {
        $statsInfo = '';
        if ($stats !== null) {
            $statsInfo = \sprintf(' <fg=gray>(%s, %d lines)</>', $stats['size'], $stats['lines']);
        }

        $this->output->writeln(
            \sprintf(
                ' <fg=green>%s</> %s <fg=cyan>[%s]</><fg=gray>%s</>%s',
                $this->padRight(text: self::SUCCESS_SYMBOL, length: 2),
                $description,
                $outputPath,
                $padding,
                $statsInfo,
            ),
        );
    }

    /**
     * Get file statistics for a given output path
     */
    private function getFileStatistics(string $outputPath): ?array
    {
        if ($this->files === null || $this->basePath === null) {
            return null;
        }

        try {
            $fullPath = $this->basePath . '/' . $outputPath;
            $fullPath = \str_replace(search: '//', replace: '/', subject: $fullPath);

            if (!$this->files->exists($fullPath)) {
                return null;
            }

            $fileSize = $this->files->size($fullPath);
            $fileContent = $this->files->read($fullPath);
            $lineCount = \substr_count(haystack: $fileContent, needle: "\n") + 1;

            return [
                'size' => $this->formatSize(bytes: $fileSize),
                'lines' => $lineCount,
            ];
        } catch (\Throwable $e) {
            // If we can't read the file, return null to avoid errors
            $this->output->writeln(\sprintf(
                '<fg=yellow>%s Warning:</> Could not read file statistics for %s: %s',
                self::WARNING_SYMBOL,
                $outputPath,
                $e->getMessage(),
            ));
            return null;
        }
    }

    /**
     * Format file size in human-readable format
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < \count(value: $units) - 1) {
            $bytes = (float) $bytes / 1024.0;
            $i++;
        }

        // Ensure $i is within bounds
        $i = \min($i, \count(value: $units) - 1);

        return (string) \round(num: $bytes, precision: 1) . ' ' . $units[$i];
    }

    /**
     * Calculate padding to align the document information
     */
    private function calculatePadding(string $description, string $outputPath, int $additional = 5): string
    {
        $totalLength = \strlen(string: $description) + \strlen(string: $outputPath) + $additional;
        $padding = \max(0, self::MAX_LINE_WIDTH - $totalLength);

        return \str_repeat(string: '.', times: $padding);
    }

    /**
     * Pad a string on the right with spaces
     */
    private function padRight(string $text, int $length): string
    {
        return \str_pad(string: $text, length: $length, pad_type: \STR_PAD_RIGHT);
    }
}
