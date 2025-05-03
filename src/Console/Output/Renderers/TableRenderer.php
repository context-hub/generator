<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output\Renderers;

use Butschster\ContextGenerator\Console\Output\Style\StyleInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Specialized renderer for table output
 */
final readonly class TableRenderer
{
    public function __construct(
        private OutputInterface $output,
        private StyleInterface $style,
    ) {}

    /**
     * Render a styled table with custom formatting
     *
     * @param array<int, string> $headers Table headers
     * @param array<int, array<int, mixed>> $rows Table rows
     * @param array<int, int|null>|null $columnWidths Optional column widths
     */
    public function render(
        array $headers,
        array $rows,
        ?array $columnWidths = null,
        bool $addSeparatorAfterEachRow = false,
    ): void {
        $table = new Table($this->output);
        $table->setHeaders($headers);

        if ($columnWidths !== null) {
            $table->setColumnWidths($columnWidths);
        }

        foreach ($rows as $index => $row) {
            $table->addRow($row);

            if ($addSeparatorAfterEachRow && $index < \count($rows) - 1) {
                $table->addRow(new TableSeparator());
            }
        }

        $table->render();
    }

    /**
     * Create a styled table cell
     */
    public function createStyledCell(string $content, string $color, bool $bold = false): TableCell
    {
        return new TableCell(
            $content,
            [
                'style' => new TableCellStyle([
                    'fg' => $color,
                ]),
            ],
        );
    }

    /**
     * Create a styled header row
     *
     * @param array<int, string> $headers
     * @return array<int, TableCell>
     */
    public function createStyledHeaderRow(array $headers): array
    {
        $row = [];

        foreach ($headers as $header) {
            $row[] = $this->createStyledCell(
                $header,
                $this->style->getLabelColor(),
                true,
            );
        }

        return $row;
    }

    /**
     * Create a property cell
     */
    public function createPropertyCell(string $content): TableCell
    {
        return $this->createStyledCell($content, $this->style->getPropertyColor());
    }

    /**
     * Create a status cell with appropriate coloring
     */
    public function createStatusCell(string $status): TableCell
    {
        $color = match (\strtolower($status)) {
            'success', 'ok', 'completed' => $this->style->getSuccessColor(),
            'warning', 'pending' => $this->style->getWarningColor(),
            'error', 'failed' => $this->style->getErrorColor(),
            default => $this->style->getInfoColor(),
        };

        return $this->createStyledCell($status, $color);
    }
}
